<?php

namespace App\Services\Production;

/**
 * [BUG-A3-MTO-FIN-001] Exigence financière d'une commande avant lancement de production.
 *
 * Objet de valeur immuable. Il porte la DÉCISION et sa JUSTIFICATION, jamais un
 * effet de bord : le calculer ne modifie rien en base. C'est ce qui permet à
 * l'écran et à la garde de lancement de consommer exactement la même règle sans
 * que l'affichage n'écrive une autorisation.
 *
 * Distinction structurante, à l'origine de l'anomalie corrigée :
 *
 *   ÉLIGIBILITÉ CALCULÉE  — déduite des encaissements confirmés, du plafond de
 *                           crédit et de l'exposition. Volatile : elle disparaît
 *                           si un paiement est annulé. Ne s'écrit PAS en base.
 *   DÉROGATION MANUELLE   — décision humaine tracée (auteur, motif, date,
 *                           validité, montant non couvert). Persistée, et elle
 *                           seule remplit `production_orders.financial_*`.
 *
 * L'ancienne garde écrivait `financial_authorization = 'approved'` sans auteur
 * dès que la couverture était acquise. L'écran lisait cette colonne et affichait
 * « ✔ Approuvée » : une piste d'audit indiquant une décision que personne
 * n'avait prise. Une couverture par paiement n'est pas une autorisation.
 */
final class ProductionFinancialRequirement
{
    /** Comptant : la totalité du TTC doit être encaissée. */
    public const TYPE_FULL_PAYMENT = 'full_payment';

    /**
     * Acompte : une fraction du TTC doit être encaissée.
     *
     * AUCUNE branche ne produit ce type aujourd'hui, et c'est délibéré. Le taux
     * exigible n'a pas de support structuré rattaché au client :
     *   - `clients.payment_mode` ne connaît que 'cash' et 'credit' (Form Request
     *     `in:cash,credit`, liste déroulante à deux options, ENUM d'origine) ;
     *   - `payment_terms.deposit_required` / `deposit_rate` existent mais aucune
     *     clé étrangère ne relie un client à cette table (`clients.payment_terms`
     *     est un varchar libre, ex. « 30 jours ») ;
     *   - `sales_settings.deposit_required_rate` est un taux GLOBAL, il ne
     *     désigne pas quels clients y sont soumis ;
     *   - `item_categories.deposit_required` n'est lu par aucun code.
     * Voir BUG-A3-SALES-DEPOSIT-004. Le type est déclaré pour que la branche
     * soit ajoutée le jour où la source structurée existe — pas pour laisser
     * croire qu'un mode « acompte » est exploitable.
     */
    public const TYPE_DEPOSIT = 'deposit';

    /** Crédit : aucune encaisse préalable, mais plafond et exposition à respecter. */
    public const TYPE_CREDIT = 'credit';

    /** Dérogation humaine tracée : couvre l'exigence quelle qu'elle soit. */
    public const TYPE_MANUAL_OVERRIDE = 'manual_override';

    /** Configuration absente, incohérente ou mode inconnu → refus (fail-closed). */
    public const TYPE_UNSUPPORTED = 'unsupported';

    /**
     * @param  string  $type          Une des constantes TYPE_*.
     * @param  int     $requiredAmount Montant à encaisser avant production (0 si non pertinent).
     * @param  int     $coveredAmount  Encaissements confirmés retenus.
     * @param  string  $source         Ce qui a produit la règle — colonne, table, paramètre, décideur.
     * @param  bool    $satisfied      Exigence remplie à l'instant de l'évaluation.
     * @param  string  $reason         Motif lisible, affichable et journalisable.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $requiredAmount,
        public readonly int $coveredAmount,
        public readonly string $source,
        public readonly bool $satisfied,
        public readonly string $reason,
    ) {
    }

    /** Reste à couvrir, jamais négatif. */
    public function uncoveredAmount(): int
    {
        return max(0, $this->requiredAmount - $this->coveredAmount);
    }

    /** L'exigence est-elle levée par une décision humaine plutôt que par un calcul ? */
    public function isManualOverride(): bool
    {
        return $this->type === self::TYPE_MANUAL_OVERRIDE;
    }

    /**
     * Libellé d'écran. Calculé à chaque affichage : aucune colonne ne le stocke,
     * donc aucun écran ne peut afficher une décision périmée.
     */
    public function label(): string
    {
        if (! $this->satisfied) {
            return 'Éligibilité non acquise';
        }

        return 'Éligibilité acquise — '.match ($this->type) {
            self::TYPE_FULL_PAYMENT    => 'Paiement intégral confirmé',
            self::TYPE_DEPOSIT         => 'Acompte minimum confirmé',
            self::TYPE_CREDIT          => 'Crédit disponible',
            self::TYPE_MANUAL_OVERRIDE => 'Dérogation DAF/DG',
            default                    => 'Origine indéterminée',
        };
    }

    /** Forme sérialisable pour la journalisation d'audit. */
    public function toArray(): array
    {
        return [
            'type'      => $this->type,
            'required'  => $this->requiredAmount,
            'covered'   => $this->coveredAmount,
            'uncovered' => $this->uncoveredAmount(),
            'source'    => $this->source,
            'satisfied' => $this->satisfied,
            'reason'    => $this->reason,
        ];
    }
}
