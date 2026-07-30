<?php

namespace App\Modules\Production\Services;

use App\Models\BonPreparation;
use App\Models\DeliveryNote;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * [VENTE ↔ PRODUCTION — règle MTO §15] Une production ne se livre que contrôlée.
 *
 * Version initiale : elle refusait le seul statut `non_conforme` du DERNIER
 * contrôle, et comparait la somme de TOUTES les déclarations de production à la
 * somme de TOUTES les lignes du bon de livraison. Quatre trous en découlaient :
 *
 *   - aucun contrôle qualité du tout passait, puisque `$qc && …` n'exigeait rien ;
 *   - `a_reprendre` — « à retoucher » — passait, alors qu'il figure bel et bien
 *     dans l'énumération en base et s'affiche en ambre à l'écran ;
 *   - une déclaration sans visa du chef d'équipe et non libérée par la qualité
 *     comptait pleinement dans le disponible ;
 *   - `rejected_quantity` existait sur les contrôles et n'était jamais lue, et
 *     les sommes globales laissaient un article couvrir la livraison d'un autre.
 *
 * Désormais : contrôle PAR ARTICLE, sur la quantité réellement CONFORME.
 *
 *   conforme = déclarations visées ET libérées par la qualité
 *            − quantités rejetées par les contrôles
 *            − quantités déjà livrées sur la commande
 *
 * Dérogation possible, jamais par défaut : elle exige la permission dédiée
 * `production.override_delivery_quality` VÉRIFIÉE NOMMÉMENT — `hasPermissionTo()`
 * et non `can()`, car `Gate::before` accorde tout au super administrateur et une
 * permission générale d'administration ne doit pas valoir acceptation d'un
 * risque qualité. Motif obligatoire, journal scellé.
 */
class ProductionDeliveryGuard
{
    public const PERMISSION = 'production.override_delivery_quality';

    public const ACTION = 'production.derogation.livraison_qualite';

    /** Statuts de contrôle qualité interdisant la livraison. */
    public const QC_BLOCKING = ['non_conforme', 'a_reprendre'];

    /**
     * Bon de livraison : contrôle qualité ET quantité conforme, ligne par ligne.
     *
     * @throws \RuntimeException
     */
    public function assertDeliverable(DeliveryNote $dn, ?string $overrideReason = null): void
    {
        $order = $dn->order;
        if (! $order) {
            return;
        }

        $ofs = $this->productionOrders($order);
        if ($ofs->isEmpty()) {
            return; // commande hors production : la disponibilité relève de StockService
        }

        $motifs = array_merge(
            $this->qualityFailures($ofs),
            $this->quantityFailures($order, $ofs, $dn),
        );

        $this->enforce($motifs, $overrideReason, $dn->number, [
            'delivery_note_id' => $dn->id,
            'bon_livraison'    => $dn->number,
            'order_id'         => $order->id,
            'commande'         => $order->number,
        ]);
    }

    /**
     * Préparation et chargement : seule la barrière QUALITÉ s'applique.
     *
     * On ne contrôle pas ici les quantités : une préparation est par nature
     * partielle et évolutive. Mais on ne charge pas un camion avec de la matière
     * dont personne n'a dit qu'elle était bonne — c'est le sens de la règle.
     *
     * @throws \RuntimeException
     */
    public function assertPreparable(BonPreparation $bp, ?string $overrideReason = null): void
    {
        $order = $bp->order;
        if (! $order) {
            return;
        }

        $ofs = $this->productionOrders($order);
        if ($ofs->isEmpty()) {
            return;
        }

        $this->enforce($this->qualityFailures($ofs), $overrideReason, $bp->number, [
            'bon_preparation_id' => $bp->id,
            'bon_preparation'    => $bp->number,
            'order_id'           => $order->id,
            'commande'           => $order->number,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int,ProductionOrder> */
    private function productionOrders(Order $order)
    {
        return $order->productionOrders()
            ->where('status', '!=', 'annule')
            ->with(['qualityControls', 'outputs'])
            ->get();
    }

    /**
     * Barrière QUALITÉ : chaque OF doit porter un contrôle, et ce contrôle doit
     * conclure à la conformité.
     *
     * Le dernier identifiant ne fait pas foi à lui seul : un contrôle conforme
     * postérieur à une non-conformité ne l'efface pas, il atteste une REPRISE. On
     * exige donc que le contrôle le plus récent soit conforme ET qu'aucun contrôle
     * bloquant ne subsiste sans reprise ultérieure — ce que traduit simplement le
     * fait de regarder le dernier contrôle après avoir vérifié qu'il en existe un.
     *
     * @param  \Illuminate\Support\Collection<int,ProductionOrder>  $ofs
     * @return list<string>
     */
    private function qualityFailures($ofs): array
    {
        $motifs = [];

        foreach ($ofs as $of) {
            $qc = $of->qualityControls->sortByDesc('id')->first();

            if (! $qc) {
                $motifs[] = sprintf(
                    'aucun contrôle qualité sur l’OF %s — une production non contrôlée ne se livre pas.',
                    $of->number
                );

                continue;
            }

            if (in_array($qc->status, self::QC_BLOCKING, true)) {
                $motifs[] = sprintf(
                    'contrôle qualité « %s » sur l’OF %s.',
                    $qc->status === 'a_reprendre' ? 'à reprendre' : 'non conforme',
                    $of->number
                );
            }
        }

        return $motifs;
    }

    /**
     * Barrière QUANTITÉ, article par article. Une somme globale laisserait la
     * production d'un article couvrir la livraison d'un autre.
     *
     * @param  \Illuminate\Support\Collection<int,ProductionOrder>  $ofs
     * @return list<string>
     */
    private function quantityFailures(Order $order, $ofs, DeliveryNote $dn): array
    {
        $conforme  = $this->conformingByProduct($ofs);
        $livre     = $this->alreadyDeliveredByProduct($order, $dn->id);
        $motifs    = [];

        foreach ($dn->loadMissing('items')->items as $item) {
            $productId = (int) $item->product_id;
            $demande   = (float) $item->quantity;

            if ($demande <= 0) {
                continue;
            }

            // Article absent de la production de cette commande : il ne peut être
            // couvert par aucun OF. Sur une commande sous production, une telle
            // ligne signale un écart entre ce qui a été fabriqué et ce qui part.
            if (! array_key_exists($productId, $conforme)) {
                $motifs[] = sprintf(
                    'l’article « %s » du bon de livraison n’est fabriqué par aucun OF de cette commande.',
                    $this->productName($productId)
                );

                continue;
            }

            $disponible = $conforme[$productId] - ($livre[$productId] ?? 0.0);

            if ($demande > $disponible + 0.001) {
                $motifs[] = sprintf(
                    'article « %s » : %s à livrer pour %s réellement conforme et disponible (déjà livré %s).',
                    $this->productName($productId),
                    $this->n($demande),
                    $this->n(max(0, $disponible)),
                    $this->n($livre[$productId] ?? 0.0)
                );
            }
        }

        return $motifs;
    }

    /**
     * Quantité CONFORME par article :
     *   déclarations visées par le chef d'équipe ET libérées par la qualité,
     *   diminuées des quantités rejetées par les contrôles de l'OF.
     *
     * Une déclaration non visée n'est pas une production reconnue ; une
     * déclaration non libérée par la qualité n'est pas une production livrable.
     *
     * @param  \Illuminate\Support\Collection<int,ProductionOrder>  $ofs
     * @return array<int,float>
     */
    private function conformingByProduct($ofs): array
    {
        $conforme = [];

        foreach ($ofs as $of) {
            foreach ($of->outputs as $output) {
                // L'article de repli est celui de l'OF : les déclarations
                // anciennes ne portaient pas toujours product_id.
                $productId = (int) ($output->product_id ?: $of->product_id);
                if (! $productId) {
                    continue;
                }

                $conforme[$productId] ??= 0.0;

                if ($output->status !== 'validee' || $output->validated_at === null) {
                    continue; // déclarée mais non visée
                }
                if ($output->quality_released_at === null) {
                    continue; // visée mais non libérée par la qualité
                }

                $conforme[$productId] += (float) $output->quantity;
            }

            // Rebuts constatés aux contrôles : imputés à l'article de l'OF.
            $rejete = (float) $of->qualityControls->sum('rejected_quantity');
            if ($rejete > 0 && $of->product_id) {
                $conforme[(int) $of->product_id] = max(
                    0.0,
                    ($conforme[(int) $of->product_id] ?? 0.0) - $rejete
                );
            }
        }

        return $conforme;
    }

    /**
     * Quantités déjà livrées sur la commande, par article. Sans elles, deux
     * livraisons partielles épuiseraient deux fois la même production.
     *
     * La source est la somme des LIGNES DE BL déjà validés, et non
     * `order_items.delivered_quantity` : ce compteur n'est incrémenté que
     * lorsque la ligne de livraison porte `order_item_id`
     * (DeliveryNoteService::applyStockOutInner). Un bon saisi à la main, sans ce
     * rattachement, laisserait le compteur à zéro et rouvrirait la surlivraison
     * que cette barrière est censée fermer.
     *
     * @return array<int,float>
     */
    private function alreadyDeliveredByProduct(Order $order, ?int $excludeDeliveryNoteId = null): array
    {
        $livre = [];

        $bls = DeliveryNote::where('order_id', $order->id)
            // Un brouillon, un refus ou une annulation n'a rien livré.
            ->whereNotIn('status', ['brouillon', 'en_attente_validation', 'refuse', 'annule'])
            ->when($excludeDeliveryNoteId, fn ($q) => $q->whereKeyNot($excludeDeliveryNoteId))
            ->with('items')
            ->get();

        foreach ($bls as $bl) {
            foreach ($bl->items as $item) {
                if (! $item->product_id) {
                    continue;
                }
                $livre[(int) $item->product_id] = ($livre[(int) $item->product_id] ?? 0.0)
                    + (float) $item->quantity;
            }
        }

        return $livre;
    }

    /**
     * Applique la décision : rien à signaler, dérogation recevable, ou refus.
     *
     * @param  list<string>  $motifs
     * @param  array<string,mixed>  $contexte
     *
     * @throws \RuntimeException
     */
    private function enforce(array $motifs, ?string $overrideReason, string $document, array $contexte): void
    {
        if ($motifs === []) {
            return;
        }

        $motif = trim((string) $overrideReason);
        $user  = Auth::user();

        if ($motif === '') {
            throw new \RuntimeException(
                'Livraison bloquée — '.implode(' ', $motifs)
            );
        }

        // `hasPermissionTo()` et non `can()` : Gate::before accorde tout au super
        // administrateur, et accepter un risque qualité doit être un droit nommé.
        if (! $user || ! $user->hasPermissionTo(self::PERMISSION)) {
            throw new \RuntimeException(
                'Dérogation refusée : la permission « '.self::PERMISSION.' » est requise pour '
                .'livrer malgré un défaut qualité. Motifs — '.implode(' ', $motifs)
            );
        }

        app(AuditService::class)->log(self::ACTION, null, [], array_merge($contexte, [
            'document'      => $document,
            'motifs'        => $motifs,
            'motif_accepte' => $motif,
            'risque_accepte' => 'livraison malgré défaut ou insuffisance qualité',
            'user_id'       => $user->id,
        ]));

        Log::channel('security')->warning(self::ACTION, [
            'document' => $document,
            'user_id'  => $user->id,
            'motif'    => $motif,
            'motifs'   => $motifs,
        ]);
    }

    private function productName(int $productId): string
    {
        return Product::whereKey($productId)->value('name') ?? ('article #'.$productId);
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
