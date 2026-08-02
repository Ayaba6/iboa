<?php

namespace App\Services\Sales;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * [D2] SOURCE UNIQUE de la stratégie d'approvisionnement d'un article vendable.
 *
 * Un article vendable doit dire COMMENT il est approvisionné : fabriqué sur
 * commande, fabriqué pour le stock, acheté-revendu, presté, ou consommé en
 * interne. Sans cette réponse, le module Ventes ne sait ni s'il doit chercher du
 * stock, ni s'il doit contrôler une couverture financière. Il ne doit alors
 * RIEN supposer : l'opération est refusée.
 *
 * Le vocabulaire existe déjà, complet, dans le référentiel :
 *
 *     item_categories.strategy = ENUM('mto','mts','achat_revente','service','conso_interne')
 *
 * Mais {@see \App\Services\CategoryDefaultsService} ne recopie sur l'article que
 * 'mto' et 'mts', et écrase le reste à NULL. C'est l'origine des 19 articles
 * sans mode en base : la catégorie connaît la stratégie, l'article l'oublie. La
 * colonne `products.production_mode` est de surcroît un `varchar(3)` — elle ne
 * peut PAS contenir 'achat_revente' ni 'service'.
 *
 * D'où la résolution en deux temps, plutôt qu'une valeur exigée sur l'article :
 *
 *   1. `products.production_mode`, s'il porte une valeur RECONNUE ;
 *   2. à défaut, `item_categories.strategy` de sa catégorie ;
 *   3. sinon `null` — et un article VENDABLE sans stratégie est bloqué.
 *
 * Une valeur présente mais inconnue ne se rabat PAS sur la catégorie : une
 * donnée explicitement fausse est une erreur de configuration, pas une absence.
 *
 * Les articles non vendables ne sont jamais bloqués : une bobine de matière
 * première n'a pas de stratégie commerciale à déclarer, et 13 des 19 articles
 * sans mode sont dans ce cas.
 */
class FulfillmentStrategyResolver
{
    /** Fabriqué à la commande — tôle bac. */
    public const MTO = 'mto';

    /** Fabriqué pour le stock — fer à béton. */
    public const MTS = 'mts';

    /** Acheté puis revendu sans transformation. */
    public const ACHAT_REVENTE = 'achat_revente';

    /** Prestation : ni stock, ni production. */
    public const SERVICE = 'service';

    /** Consommé en interne, non vendu. */
    public const CONSO_INTERNE = 'conso_interne';

    /** Stratégies reconnues. Toute autre valeur vaut absence de stratégie. */
    public const STRATEGIES = [
        self::MTO,
        self::MTS,
        self::ACHAT_REVENTE,
        self::SERVICE,
        self::CONSO_INTERNE,
    ];

    /** Stratégies qui passent par une fabrication interne. */
    public const FABRIQUEES = [self::MTO, self::MTS];

    /**
     * Stratégie effective de l'article, ou `null` si aucune n'est déterminable.
     */
    public function resolve(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        $mode = $product->production_mode;

        if ($mode !== null && $mode !== '') {
            // Valeur explicite : elle fait foi, reconnue ou non. Une valeur
            // inconnue ne se replie pas sur la catégorie — elle signale une
            // configuration erronée, qu'un repli silencieux masquerait.
            return in_array($mode, self::STRATEGIES, true) ? $mode : null;
        }

        $strategy = $product->relationLoaded('itemCategory')
            ? $product->itemCategory?->strategy
            : $product->itemCategory()->value('strategy');

        return in_array($strategy, self::STRATEGIES, true) ? $strategy : null;
    }

    /**
     * L'article doit-il bloquer une opération commerciale ?
     * Seuls les articles VENDABLES sont concernés.
     */
    public function isBlocked(?Product $product): bool
    {
        if (! $product || ! $product->is_sellable) {
            return false;
        }

        return $this->resolve($product) === null;
    }

    /** L'article est-il fabriqué en interne (MTO ou MTS) ? */
    public function isManufactured(?Product $product): bool
    {
        return in_array($this->resolve($product), self::FABRIQUEES, true);
    }

    /**
     * Refuse l'opération si l'article vendable n'a pas de stratégie.
     *
     * @throws ValidationException
     */
    public function assertSellable(?Product $product): void
    {
        if (! $this->isBlocked($product)) {
            return;
        }

        throw ValidationException::withMessages([
            'production_mode' => sprintf(
                'Impossible d\'ajouter « %s » à la vente : son mode d\'approvisionnement n\'est pas configuré. '
                .'Renseignez la stratégie de l\'article ou de sa catégorie (%s).',
                $product->name ?? 'cet article',
                implode(', ', self::STRATEGIES),
            ),
        ]);
    }

    /**
     * Contrôle défensif sur les lignes d'un document commercial.
     *
     * Le contrôle posé à la saisie ne dispense pas des suivants : un article
     * peut devenir incomplet APRÈS la création du document — reprise de
     * données, changement de catégorie. Le document reste alors consultable et
     * corrigeable, mais ne peut plus avancer.
     *
     * @param  iterable  $lignes  Lignes portant `product` ou `product_id`.
     *
     * @throws ValidationException
     */
    public function assertLines(iterable $lignes): void
    {
        $bloques = [];

        foreach ($lignes as $ligne) {
            $product = is_object($ligne) && isset($ligne->product)
                ? $ligne->product
                : $this->productDe($ligne);

            if ($this->isBlocked($product)) {
                $bloques[$product->id] = $product->name;
            }
        }

        if ($bloques === []) {
            return;
        }

        throw ValidationException::withMessages([
            'production_mode' => sprintf(
                'Document bloqué : %s n\'%s pas de mode d\'approvisionnement configuré. '
                .'Corrigez ou retirez %s avant de poursuivre.',
                implode(', ', array_map(fn ($n) => '« '.$n.' »', $bloques)),
                count($bloques) > 1 ? 'ont' : 'a',
                count($bloques) > 1 ? 'ces lignes' : 'cette ligne',
            ),
        ]);
    }

    /** Résout l'article d'une ligne quelle que soit sa forme (modèle ou tableau). */
    private function productDe(mixed $ligne): ?Product
    {
        $id = is_array($ligne)
            ? ($ligne['product_id'] ?? null)
            : (is_object($ligne) ? ($ligne->product_id ?? null) : null);

        return $id ? Product::find($id) : null;
    }

    /**
     * Lignes d'un document, chargées avec ce qu'il faut pour résoudre la
     * stratégie sans requête par ligne.
     */
    public function lignesDe(mixed $document): Collection
    {
        return $document->items()->with('product.itemCategory')->get();
    }
}
