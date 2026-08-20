<?php

namespace App\Services\Sales;

use App\Models\Currency;

/**
 * Règle unique du prix d'une ligne commerciale engageante.
 *
 * [BUG-A3-SALES-ZERO-PRICE-026] Une vente à zéro traversait devis, validation et
 * conversion sans le moindre refus, dès lors que l'article n'avait aucun coût
 * connu. La seule garde en place — le prix plancher — se calcule à partir du
 * coût ; sans coût, elle valait zéro, et zéro n'est pas inférieur à zéro.
 *
 * Deux règles distinctes s'appliquent, et elles ne se remplacent pas :
 *
 *   GARDE 1  net > 0            indépendante du coût, SANS dérogation
 *   GARDE 2  net >= plancher    seulement si un plancher existe, avec dérogation
 *
 * La distinction est métier. Vendre à 5 500 F un article dont le plancher est à
 * 6 000 F est une vente sous plancher : cela se négocie et s'approuve. Vendre à
 * 0 F est une GRATUITÉ — un acte d'une autre nature, qui appelle un motif et une
 * traçabilité propres. Tant que [FEATURE-A3-SALES-FREE-LINE-027] n'existe pas,
 * la gratuité est refusée, y compris à qui détient `sales_below_floor.approve` :
 * approuver une remise n'est pas offrir la marchandise.
 *
 * Le brouillon échappe aux deux règles — il sert à préparer un devis dont le
 * prix n'est pas encore arrêté.
 *
 * PRÉCISION MONÉTAIRE — aucune tolérance en dur. Le zéro se définit par la
 * DEVISE, et la devise se lit sur le document : `quotes.currency_code`,
 * `orders.currency_code`, `invoices.currency_code`. Le franc CFA n'ayant pas de
 * décimale, un seuil du type « un demi-centime » y inventerait une subdivision
 * que la monnaie ignore ; sur une devise à deux décimales, le même seuil
 * avalerait un centime bien réel. Le montant est donc arrondi à la précision de
 * SA devise, puis comparé à zéro.
 */
class CommercialLinePriceRule
{
    /** Décimales par code devise, mémorisées le temps de la requête. */
    private static array $decimalesParDevise = [];

    /**
     * Décimales d'une devise, ou de la devise par défaut si aucune n'est donnée.
     *
     * Le repli final sur zéro n'est pas arbitraire : c'est la valeur du XOF,
     * devise de l'installation. Une base sans devise configurée doit se
     * comporter comme la monnaie réelle, non comme un euro implicite.
     */
    public static function decimalesMonetaires(?string $codeDevise = null): int
    {
        $cle = $codeDevise ?: '__defaut__';

        if (array_key_exists($cle, self::$decimalesParDevise)) {
            return self::$decimalesParDevise[$cle];
        }

        try {
            $valeur = $codeDevise
                ? Currency::query()->where('code', $codeDevise)->value('decimal_places')
                : Currency::query()->where('is_default', true)->value('decimal_places');
        } catch (\Throwable) {
            $valeur = null;
        }

        // Une devise inconnue ne doit pas se voir attribuer la précision d'une
        // autre : on retombe sur la devise par défaut, jamais sur un chiffre
        // inventé.
        if ($valeur === null && $codeDevise) {
            return self::$decimalesParDevise[$cle] = self::decimalesMonetaires();
        }

        return self::$decimalesParDevise[$cle] = (int) ($valeur ?? 0);
    }

    /** Utilisé par les tests pour repartir d'un état propre. */
    public static function oublierDecimales(): void
    {
        self::$decimalesParDevise = [];
    }

    /** Arrondi à la précision de la devise — le seul zéro qui fasse foi. */
    public static function arrondiMonetaire(float $montant, ?string $codeDevise = null): float
    {
        return round($montant, self::decimalesMonetaires($codeDevise));
    }

    /**
     * Le montant est-il nul une fois exprimé dans sa devise ?
     *
     * En XOF, 0,4 F n'existe pas : arrondi, il vaut 0, et la ligne est gratuite.
     * En EUR, 0,01 € existe et ne l'est pas. Aucun seuil arbitraire n'intervient
     * — c'est la devise qui décide.
     */
    public static function estGratuit(float $montant, ?string $codeDevise = null): bool
    {
        return self::arrondiMonetaire($montant, $codeDevise) <= 0;
    }

    /**
     * MONTANT NET D'UNE LIGNE — quantité comprise, et non prix unitaire.
     *
     * Le nom le dit à dessein : la valeur rendue est le total de la ligne, pas
     * son prix unitaire net. Confondre les deux ferait juger une ligne de dix
     * pièces sur le prix d'une seule.
     *
     * `QuoteService`, `OrderService` et `InvoiceService` calculent tous le HT de
     * ligne ainsi : quantité × prix unitaire × (1 − remise de ligne), arrondi à
     * la devise. La quote-part de remise globale s'y ajoute quand elle est
     * connue — au niveau du document, pas de la saisie.
     *
     * Reproduire la formule ici plutôt que d'appeler ces services tient à leur
     * nature : ils CRÉENT des lignes en base, ils ne calculent pas à la demande.
     * Un test de non-divergence garantit l'égalité des deux.
     */
    public static function montantNetLigne(
        float $prixUnitaire,
        float $quantite = 1,
        float $remiseLignePct = 0,
        float $ratioRemiseGlobale = 0,
        ?string $codeDevise = null,
    ): float {
        $net = $quantite
            * $prixUnitaire
            * (1 - $remiseLignePct / 100)
            * (1 - $ratioRemiseGlobale);

        return self::arrondiMonetaire($net, $codeDevise);
    }

    /** Message unique — le même quel que soit le chemin qui refuse. */
    public static function messageGratuite(?string $designation = null): string
    {
        return sprintf(
            'Prix HT net nul%s : une vente engageante doit avoir un montant strictement '
            .'supérieur à zéro. Une ligne offerte relève de la gratuité commerciale, '
            .'qui n’est pas encore gérée — corrigez le prix ou la remise.',
            $designation ? ' pour « '.$designation.' »' : '',
        );
    }
}
