<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [BUG-A3-SALES-LINE-IMMUTABLE-012] Synchronisation des lignes de commande PAR
 * IDENTITÉ, en remplacement du remplacement intégral.
 *
 * `OrderService::update()` faisait :
 *
 *     $order->items()->delete();      // suppression PHYSIQUE
 *     $this->syncItems($order, $items);
 *
 * Chaque édition détruisait les lignes et les recréait avec de nouveaux
 * identifiants — y compris sur une commande `confirme` ou `en_preparation`.
 * Rien de ce qui référence une ligne ne pouvait survivre : ordre de
 * fabrication, affectation MTO, réservation, préparation, livraison, facture.
 * Une simple correction de prix rompait la traçabilité, sans le dire.
 *
 * L'identité est l'IDENTIFIANT PERSISTANT de la ligne, jamais sa position dans
 * le tableau, ni le code article, ni le couple produit/prix : deux lignes
 * peuvent porter le même article dans deux couleurs, deux longueurs ou deux
 * dépôts, et une réorganisation d'affichage ne doit rien renuméroter.
 *
 * Trois opérations, et une seule interdiction :
 *
 *   - ligne reçue AVEC identifiant  → mise à jour sur place ;
 *   - ligne reçue SANS identifiant  → création ;
 *   - ligne absente de la requête   → suppression LOGIQUE, et seulement si elle
 *     ne porte aucune activité aval.
 */
class OrderItemSynchronizer
{
    /**
     * Dépendances qui figent une ligne : leur existence interdit de la retirer.
     *
     * La clé est la table, la valeur la colonne qui pointe la ligne. Les
     * dépendances désignant la COMMANDE et non la ligne sont traitées à part —
     * une facture rattachée à la commande n'interdit pas de retirer une ligne
     * qu'elle ne facture pas.
     */
    private const DEPENDANCES_LIGNE = [
        'sales_picking_items' => ['order_item_id', 'préparation'],
        'delivery_note_items' => ['order_item_id', 'bon de livraison'],
        'invoice_items'       => ['order_item_id', 'facture'],
        // `production_orders.order_item_id` rejoindra cette liste avec le lot
        // BUG-A3-MTO-ALLOC-011 ; la garde ci-dessous le couvre entre-temps par
        // commande + article, faute de rattachement à la ligne.
    ];

    /**
     * Dépendances rattachées à la COMMANDE et à l'ARTICLE, faute de colonne
     * pointant la ligne. Moins précis — deux lignes du même article se
     * verrouillent mutuellement — mais un faux positif protège, là où un faux
     * négatif détruirait un lien réel.
     */
    private const DEPENDANCES_COMMANDE_PRODUIT = [
        'production_orders'  => ['ordre de fabrication', 'status', ['annule']],
        'stock_reservations' => ['réservation de stock', 'status', ['released', 'cancelled']],
    ];

    /** Seul état où les lignes se modifient directement. */
    public const STATUTS_MODIFIABLES = ['brouillon'];

    /**
     * Aligne les lignes de la commande sur celles reçues.
     *
     * @param  array  $recues  Lignes soumises, portant éventuellement un `id`.
     * @return array{creees:int,modifiees:int,retirees:int}
     *
     * @throws ValidationException
     */
    public function sync(Order $order, array $recues, callable $construire): array
    {
        // [§3] CONTRAT : ce service ne fabrique PAS son atomicité. L'appelant
        // libère les réservations avant et les repose après ; ouvrir ici une
        // transaction indépendante donnerait l'illusion d'une atomicité
        // complète alors qu'une exception postérieure laisserait les
        // réservations libérées sans contrepartie.
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('OrderItemSynchronizer doit être exécuté dans une transaction.');
        }

        $this->assertModifiable($order);

        {
            // Verrou sur la commande PUIS sur ses lignes : deux modifications
            // concurrentes doivent se sérialiser, sinon la seconde retire des
            // lignes que la première venait d'ajouter.
            $commande = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $existantes = OrderItem::where('order_id', $commande->id)
                ->lockForUpdate()->get()->keyBy('id');

            $vues = [];
            $journal = [];
            $compte = ['creees' => 0, 'modifiees' => 0, 'retirees' => 0];

            foreach ($recues as $position => $ligne) {
                $id = $this->identifiant($ligne);
                $valeurs = $construire($ligne, $position);

                // Ligne vide : ignorée sans effet. Elle ne compte ni comme
                // création, ni comme retrait — sinon un champ laissé blanc dans
                // le formulaire supprimerait la ligne correspondante.
                if ($valeurs === null) {
                    if ($id !== null && $existantes->has($id)) {
                        $vues[] = $id;
                    }

                    continue;
                }

                if ($id === null) {
                    $creee = OrderItem::create($valeurs + ['order_id' => $commande->id]);
                    $journal[] = ['order_item_id' => $creee->id, 'action' => 'created'];
                    $compte['creees']++;

                    continue;
                }

                // Un identifiant qui n'appartient pas à cette commande est un
                // détournement : il permettrait de réécrire la ligne d'un autre
                // client en postant simplement son `id`.
                if (! $existantes->has($id)) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Ligne %d : l\'identifiant %d n\'appartient pas à la commande %s.',
                            $position + 1, $id, $commande->number
                        ),
                    ]);
                }

                $avant = $existantes[$id]->getOriginal();
                $existantes[$id]->fill($valeurs);
                $diff = $this->diff($existantes[$id], $avant);

                if ($diff !== []) {
                    $existantes[$id]->save();
                    $journal[] = [
                        'order_item_id' => $id,
                        'action' => 'updated',
                        'changes' => $diff,
                    ];
                    $compte['modifiees']++;
                }

                $vues[] = $id;
            }

            foreach ($existantes as $id => $ligne) {
                if (in_array($id, $vues, true)) {
                    continue;
                }

                $this->assertRetirable($ligne);
                $ligne->delete();   // LOGIQUE : la ligne reste consultable en audit
                $journal[] = [
                    'order_item_id' => $id,
                    'action' => 'soft_deleted',
                    'reason' => "Ligne retirée d'une commande en brouillon",
                ];
                $compte['retirees']++;
            }

            $this->journaliser($commande, $compte, $journal);

            return $compte;
        }
    }

    /**
     * [§4] Le STATUT protège les lignes, indépendamment de leurs dépendances.
     *
     * Une commande soumise n'a pas encore d'activité aval : le contrôle de
     * dépendances la laisserait modifier librement. Or elle est déjà partie en
     * validation — son contenu ne doit plus bouger sans processus explicite.
     *
     * @throws ValidationException
     */
    public function assertModifiable(Order $order): void
    {
        if (in_array($order->status, self::STATUTS_MODIFIABLES, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => "Cette commande ne peut plus être modifiée directement dans son état actuel. "
                ."Utilisez le processus d'avenant ou d'annulation prévu.",
        ]);
    }

    /**
     * Refuse le retrait d'une ligne portant une activité métier.
     *
     * @throws ValidationException
     */
    public function assertRetirable(OrderItem $ligne): void
    {
        foreach (self::DEPENDANCES_LIGNE as $table => [$colonne, $libelle]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)
                || ! \Illuminate\Support\Facades\Schema::hasColumn($table, $colonne)) {
                continue;
            }

            if (DB::table($table)->where($colonne, $ligne->id)->exists()) {
                $this->refuser($libelle);
            }
        }

        foreach (self::DEPENDANCES_COMMANDE_PRODUIT as $table => [$libelle, $colonneStatut, $statutsInertes]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)
                || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'order_id')
                || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'product_id')) {
                continue;
            }

            $existe = DB::table($table)
                ->where('order_id', $ligne->order_id)
                ->where('product_id', $ligne->product_id)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn($table, $colonneStatut),
                    fn ($q) => $q->whereNotIn($colonneStatut, $statutsInertes)
                )
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at'),
                    fn ($q) => $q->whereNull('deleted_at')
                )
                ->exists();

            if ($existe) {
                $this->refuser($libelle);
            }
        }
    }

    /** @throws ValidationException */
    private function refuser(string $libelle): void
    {
        throw ValidationException::withMessages([
            'items' => sprintf(
                'Cette ligne ne peut plus être supprimée, car elle possède déjà une activité métier (%s). '
                .'Utilisez l\'annulation ou le processus d\'avenant.',
                $libelle
            ),
        ]);
    }

    /** Identifiant persistant de la ligne, ou null pour une création. */
    private function identifiant(mixed $ligne): ?int
    {
        $id = is_array($ligne) ? ($ligne['id'] ?? null) : ($ligne->id ?? null);

        return ($id === null || $id === '') ? null : (int) $id;
    }

    /**
     * Journal STRUCTURÉ, champ par champ.
     *
     * Un message général « commande modifiée » ne permet pas de savoir ce qui a
     * changé ni de reconstituer l'état antérieur. Seuls les champs réellement
     * différents sont consignés, avec leur ancienne et leur nouvelle valeur.
     */
    private function journaliser(Order $commande, array $compte, array $journal): void
    {
        if ($journal === []) {
            return;
        }

        app(\App\Services\AuditService::class)->log(
            'vente.commande.lignes_synchronisees',
            $commande,
            ['statut' => $commande->status],
            [
                'order_id' => $commande->id,
                'par'      => Auth::id(),
                'resume'   => $compte,
                'lignes'   => $journal,
            ],
        );
    }

    /**
     * Champs réellement modifiés : ancienne et nouvelle valeur.
     *
     * Les champs inchangés sont écartés — les consigner noierait le diff utile
     * dans une copie complète de la ligne à chaque enregistrement.
     */
    private function diff(OrderItem $ligne, array $avant): array
    {
        $diff = [];
        foreach ($ligne->getDirty() as $champ => $nouvelle) {
            if ($champ === 'updated_at') {
                continue;
            }
            $diff[$champ] = [
                'old' => $avant[$champ] ?? null,
                'new' => $nouvelle,
            ];
        }

        return $diff;
    }
}
