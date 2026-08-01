<?php

namespace App\Services\Production;

use App\Models\Client;
use App\Models\Order;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\CustomerCreditExposureService;
use Illuminate\Support\Facades\DB;

/**
 * [BUG-A3-MTO-FIN-001] SOURCE UNIQUE de l'exigence financière avant production.
 *
 * L'anomalie n'était pas l'absence de garde : la garde existait et bloquait
 * correctement les clients à crédit. Le défaut venait de DEUX implémentations
 * concurrentes de la même règle — `Order::requiredBeforeProduction()` pour
 * l'écran, un `match` de littéraux dans `ProductionService::checkFinancialGate()`
 * pour le lancement — qui comparaient le mode de règlement à « comptant » et
 * « acompte » alors que la base stocke 'cash' et 'credit'. L'écran concluait
 * « Bloquant », le lancement concluait « autorisé », et le comptant — le seul
 * mode devant payer d'avance — passait sans encaissement.
 *
 * Ce service est désormais le seul endroit où la règle est écrite. Il ne
 * modifie RIEN : il lit et retourne un {@see ProductionFinancialRequirement}.
 * Cette pureté est la garantie recherchée — un écran qui affiche l'éligibilité
 * ne peut plus, ce faisant, inscrire une autorisation en base.
 *
 * FAIL-CLOSED. Tout mode de règlement non traité explicitement, toute
 * configuration incomplète et toute donnée manquante produisent
 * `TYPE_UNSUPPORTED`, donc un refus. L'ancienne version faisait l'inverse :
 * l'absence de branche correspondante menait à l'autorisation automatique. Un
 * mode ajouté plus tard sans toucher ici sera refusé, pas laissé passer.
 */
class ProductionFinancialEligibilityService
{
    public function __construct(
        private readonly CustomerCreditExposureService $exposure,
    ) {
    }

    /**
     * Évalue l'exigence financière d'une commande.
     *
     * @param  Order                $order            Commande de vente évaluée.
     * @param  ProductionOrder|null $productionOrder  OF concerné, s'il en existe un : il porte
     *                                                la dérogation DAF/DG de niveau OF.
     * @param  bool                 $lock             Lectures verrouillantes. Réservé à la garde de
     *                                                lancement, qui doit décider sur l'état committé
     *                                                le plus récent. Exige une transaction ouverte.
     */
    public function evaluate(Order $order, ?ProductionOrder $productionOrder = null, bool $lock = false): ProductionFinancialRequirement
    {
        $covered = (int) $order->confirmedReceipts();

        // ─── 1. Dérogation de niveau OF ──────────────────────────────────────
        // Priorité maximale : une décision humaine tracée lève l'exigence.
        // L'AUTEUR est exigé. Une autorisation sans auteur n'est pas une
        // décision, c'est la trace laissée par l'écriture automatique corrigée
        // ici — OF-2026-0007 en porte l'empreinte : `financial_authorized_at`
        // renseigné, `financial_authorized_by` NULL. La refuser est fail-closed.
        if ($productionOrder !== null) {
            $derogation = $this->derogationDeOf($productionOrder, $covered);
            if ($derogation !== null) {
                return $derogation;
            }
        }

        // ─── 2. Approbation gérant portée par la commande ────────────────────
        // Même nature : décision humaine, avec motif, auteur et validité datée.
        if ($order->hasValidProductionApproval()) {
            return new ProductionFinancialRequirement(
                type: ProductionFinancialRequirement::TYPE_MANUAL_OVERRIDE,
                requiredAmount: 0,
                coveredAmount: $covered,
                source: 'orders.production_approved (approbation gérant)',
                satisfied: true,
                reason: sprintf(
                    'Approbation gérant du %s%s%s.',
                    optional($order->production_approved_at)->format('d/m/Y') ?: '—',
                    $order->production_approval_expires_at ? ', valable jusqu\'au '.$order->production_approval_expires_at->format('d/m/Y') : '',
                    $order->production_approval_reason ? ' — '.$order->production_approval_reason : '',
                ),
            );
        }

        $totalTtc = (int) $order->total_ttc;

        // Une commande à zéro n'expose à aucun risque financier : rien à couvrir.
        if ($totalTtc <= 0) {
            return new ProductionFinancialRequirement(
                type: ProductionFinancialRequirement::TYPE_FULL_PAYMENT,
                requiredAmount: 0,
                coveredAmount: $covered,
                source: 'orders.total_ttc = 0',
                satisfied: true,
                reason: 'Commande sans montant : aucune exigence financière.',
            );
        }

        $client = $order->client;

        if (! $client) {
            return $this->refusConfiguration(
                $covered,
                'orders.client_id',
                'Commande sans client rattaché : le mode de règlement est indéterminable.',
            );
        }

        // ─── 3. Règle dictée par le mode de règlement ────────────────────────
        return match ($client->payment_mode) {
            Client::PAYMENT_CASH   => $this->exigenceComptant($order, $covered),
            Client::PAYMENT_CREDIT => $this->exigenceCredit($order, $client, $covered, $lock),
            default                => $this->refusConfiguration(
                $covered,
                'clients.payment_mode',
                sprintf(
                    'Mode de règlement « %s » non reconnu : aucun chemin financier automatique. Modes traités : %s.',
                    $client->payment_mode === null || $client->payment_mode === '' ? 'non renseigné' : $client->payment_mode,
                    implode(', ', Client::PAYMENT_MODES),
                ),
            ),
        };
    }

    /** Raccourci booléen — même règle, sans le détail. */
    public function isEligible(Order $order, ?ProductionOrder $productionOrder = null, bool $lock = false): bool
    {
        return $this->evaluate($order, $productionOrder, $lock)->satisfied;
    }

    /**
     * Montant à encaisser avant production, SANS consulter les encaissements.
     * `null` = aucun chemin par le paiement (crédit, mode inconnu, sans client).
     *
     * Cette méthode existe pour une raison précise : `Order::confirmedReceipts()`
     * interroge l'exigence de chaque commande SŒUR pour répartir les acomptes
     * libres. Si l'exigence était obtenue via `evaluate()`, laquelle commence par
     * appeler `confirmedReceipts()`, la sœur relancerait le même parcours sur ses
     * propres sœurs — récursion sans fond. La règle reste écrite ici une seule
     * fois : `evaluate()` s'appuie sur cette méthode pour la branche comptant.
     */
    public function requiredAmount(Order $order): ?int
    {
        $client = $order->client;

        if (! $client || $client->payment_mode !== Client::PAYMENT_CASH) {
            return null;
        }

        return (int) $order->total_ttc;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dérogation portée par l'OF, si elle est complète et non expirée.
     * Retourne null quand aucune dérogation exploitable n'existe : l'évaluation
     * poursuit alors sur le chemin calculé.
     */
    private function derogationDeOf(ProductionOrder $of, int $covered): ?ProductionFinancialRequirement
    {
        if (! in_array($of->financial_authorization, ['approved', 'bypassed'], true)) {
            return null;
        }

        // Auteur obligatoire — voir le commentaire d'appel.
        if (! $of->financial_authorized_by) {
            return null;
        }

        $expiration = $of->financial_authorization_expires_at ?? null;
        if ($expiration && $expiration->lt(today())) {
            return null;
        }

        return new ProductionFinancialRequirement(
            type: ProductionFinancialRequirement::TYPE_MANUAL_OVERRIDE,
            requiredAmount: (int) ($of->financial_authorization_unpaid ?? 0),
            coveredAmount: $covered,
            source: 'production_orders.financial_authorization (DAF/DG)',
            satisfied: true,
            reason: sprintf(
                'Dérogation %s accordée le %s par l\'utilisateur #%d%s%s.',
                $of->financial_authorization === 'bypassed' ? 'sans exigence financière' : 'financière',
                optional($of->financial_authorized_at)->format('d/m/Y') ?: '—',
                (int) $of->financial_authorized_by,
                $expiration ? ', valable jusqu\'au '.$expiration->format('d/m/Y') : '',
                $of->financial_notes ? ' — '.$of->financial_notes : '',
            ),
        );
    }

    /** Comptant : la totalité du TTC doit être encaissée avant production. */
    private function exigenceComptant(Order $order, int $covered): ProductionFinancialRequirement
    {
        $totalTtc = (int) $this->requiredAmount($order);
        $satisfait = $covered >= $totalTtc;

        return new ProductionFinancialRequirement(
            type: ProductionFinancialRequirement::TYPE_FULL_PAYMENT,
            requiredAmount: $totalTtc,
            coveredAmount: $covered,
            source: 'clients.payment_mode = cash',
            satisfied: $satisfait,
            reason: $satisfait
                ? sprintf('Paiement intégral confirmé : %s FCFA encaissés sur %s.', $this->fcfa($covered), $this->fcfa($totalTtc))
                : sprintf(
                    'Couverture insuffisante : %s FCFA encaissés sur %s requis. Manque %s FCFA.',
                    $this->fcfa($covered), $this->fcfa($totalTtc), $this->fcfa($totalTtc - $covered),
                ),
        );
    }

    /**
     * Crédit : aucune encaisse préalable exigée, mais l'exposition doit rester
     * dans le plafond accordé et le compte ne doit pas porter d'impayé échu.
     *
     * Un plafond nul n'est PAS un plafond illimité. `CustomerCreditExposureService`
     * pose `limited = false` quand `credit_limit = 0`, ce qui rend le disponible
     * infini pour l'affichage commercial ; transposer cela à une garde de
     * production ouvrirait la production à tout client crédit non paramétré.
     * Ici, plafond nul = aucun crédit accordé = refus.
     */
    private function exigenceCredit(Order $order, Client $client, int $covered, bool $lock): ProductionFinancialRequirement
    {
        $plafond = (int) $client->credit_limit;

        if ($plafond <= 0) {
            return new ProductionFinancialRequirement(
                type: ProductionFinancialRequirement::TYPE_CREDIT,
                requiredAmount: 0,
                coveredAmount: $covered,
                source: 'clients.credit_limit = 0',
                satisfied: false,
                reason: 'Aucun plafond de crédit accordé à ce client : production soumise à dérogation DAF/DG.',
            );
        }

        $retard = $this->impayesEchus($order, $lock);
        if ($retard > 0) {
            return new ProductionFinancialRequirement(
                type: ProductionFinancialRequirement::TYPE_CREDIT,
                requiredAmount: 0,
                coveredAmount: $covered,
                source: 'invoices (échues, reste dû)',
                satisfied: false,
                reason: sprintf('Impayés échus : %s FCFA. Le crédit est suspendu tant qu\'ils subsistent.', $this->fcfa($retard)),
            );
        }

        // L'exposition inclut la commande évaluée (`new_order`) et l'exclut des
        // commandes ouvertes : elle répond à « que devient l'encours si cette
        // commande part en production ? », pas à « où en est le client ? ».
        $exposition = $this->exposure->assess($order, $lock);
        $depasse = $exposition['projected'] > $exposition['limit'];

        return new ProductionFinancialRequirement(
            type: ProductionFinancialRequirement::TYPE_CREDIT,
            requiredAmount: $depasse ? $exposition['projected'] - $exposition['limit'] : 0,
            coveredAmount: $covered,
            source: 'clients.credit_limit + CustomerCreditExposureService',
            satisfied: ! $depasse,
            reason: sprintf(
                '%s : encours prévisionnel %s FCFA / plafond %s FCFA '
                .'(factures %s + commandes ouvertes %s + cette commande %s − acomptes %s).',
                $depasse ? 'Plafond de crédit dépassé' : 'Crédit disponible',
                $this->fcfa($exposition['projected']), $this->fcfa($exposition['limit']),
                $this->fcfa($exposition['outstanding']), $this->fcfa($exposition['open_orders']),
                $this->fcfa($exposition['new_order']), $this->fcfa($exposition['deposits']),
            ),
        );
    }

    /** Reste dû des factures dont l'échéance est passée. */
    private function impayesEchus(Order $order, bool $lock): int
    {
        $query = DB::table('invoices')
            ->where('company_id', $order->company_id)
            ->where('client_id', $order->client_id)
            ->whereNull('deleted_at')
            ->whereIn('status', CustomerCreditExposureService::INVOICE_STATUSES)
            ->where('remaining_amount', '>', 0)
            ->where(fn ($q) => $q->where('status', 'en_retard')->orWhereDate('due_at', '<', today()));

        if ($lock) {
            $query->lockForUpdate();
        }

        return (int) $query->sum('remaining_amount');
    }

    /** Refus pour configuration absente ou mode inconnu. */
    private function refusConfiguration(int $covered, string $source, string $motif): ProductionFinancialRequirement
    {
        return new ProductionFinancialRequirement(
            type: ProductionFinancialRequirement::TYPE_UNSUPPORTED,
            requiredAmount: 0,
            coveredAmount: $covered,
            source: $source,
            satisfied: false,
            reason: $motif,
        );
    }

    private function fcfa(int|float $montant): string
    {
        return number_format((float) $montant, 0, ',', ' ');
    }
}
