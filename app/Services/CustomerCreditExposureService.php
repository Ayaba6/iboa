<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * [Ventes §4] SOURCE UNIQUE de l'encours client.
 *
 * Formule unique, appliquée partout (contrôle de soumission, écrans, audits) :
 *
 *     encours prévisionnel
 *       = encours comptable (factures ouvertes)
 *       + commandes ouvertes éligibles (part non encore facturée)
 *       + nouvelle commande évaluée
 *       - acomptes confirmés et encore affectables
 *
 * Périmètre exact — ce qui ENTRE :
 *   - factures de statut emise / envoyee / partiellement_payee / en_retard,
 *     pour leur `remaining_amount` (donc net des règlements déjà lettrés) ;
 *   - commandes de statut en_attente_validation / confirme / en_preparation /
 *     partiellement_livre / livre, pour `total_ttc - invoiced_amount` (la part
 *     déjà facturée est comptée une seule fois, côté factures) ;
 *   - la commande évaluée, pour son `total_ttc` TTC (taxes incluses) ;
 *   - les acomptes `client_payments.is_acompte = 1`, statut `confirme`, pour
 *     leur `unallocated_amount` (part non encore affectée à une facture).
 *
 * Ce qui N'ENTRE PAS, et pourquoi :
 *   - devis : aucun engagement ferme du client ;
 *   - commandes brouillon : non soumises, pas d'engagement ;
 *   - commandes annulées / soldées : plus d'exposition ;
 *   - factures brouillon, annulées, payées : nulles ou sans reste dû ;
 *   - BL non facturés : déjà portés par la commande d'origine via
 *     `total_ttc - invoiced_amount` ; les compter en plus double l'exposition ;
 *   - avoirs : déjà déduits du `remaining_amount` des factures qu'ils soldent ;
 *   - règlements non lettrés hors acompte : leur affectation n'est pas décidée,
 *     ils ne réduisent donc pas un encours identifié ;
 *   - chèques impayés : le rejet remet la facture en reste dû, l'encours remonte
 *     mécaniquement sans traitement spécifique ;
 *   - factures litigieuses : restent dues tant qu'aucun avoir ne les solde.
 *
 * Taxes : tous les montants sont TTC (`total_ttc`, `remaining_amount`).
 * Devise : montants stockés en devise de la société (FCFA). Aucune conversion
 *          n'est appliquée ici ; une commande en devise étrangère doit être
 *          convertie en amont, sinon l'encours serait faux.
 *
 * Société : toutes les composantes sont filtrées sur `company_id`. La table
 *          `clients` n'a pas de `company_id` — un même client peut donc exister
 *          dans deux sociétés, mais son encours n'est JAMAIS partagé entre elles.
 */
class CustomerCreditExposureService
{
    /** Statuts de factures qui portent un reste dû exigible. */
    public const INVOICE_STATUSES = ['emise', 'envoyee', 'partiellement_payee', 'en_retard'];

    /** Statuts de commandes engageantes non encore intégralement facturées. */
    public const ORDER_STATUSES = ['en_attente_validation', 'confirme', 'en_preparation', 'partiellement_livre', 'livre'];

    /**
     * Calcul unique de l'encours. Aucun autre point du code ne doit refaire
     * cette somme : contrôleurs, tableaux de bord et audits passent par ici.
     *
     * @return array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int}
     */
    public function compute(
        int $companyId,
        int $clientId,
        int $limit,
        bool $isCredit,
        int $newOrderAmount = 0,
        ?int $excludeOrderId = null,
        bool $lockRows = false,
    ): array {
        $limit = max(0, $limit);
        $limited = $isCredit && $limit > 0;

        // [Ventes §3 — course A] Sous l'isolation MySQL par défaut (REPEATABLE
        // READ), une lecture ORDINAIRE sert la vue cohérente de la transaction :
        // une commande concurrente committée APRÈS le début de la transaction
        // reste invisible, même après l'obtention du verrou client. Deux
        // commandes pouvaient donc consommer le même reliquat de plafond.
        // Une lecture VERROUILLANTE (SELECT ... FOR UPDATE) lit toujours la
        // dernière version committée : c'est ce qui rend le contrôle sérialisant.
        $lock = fn ($query) => $lockRows ? $query->lockForUpdate() : $query;

        $outstanding = (int) $lock(DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->whereIn('status', self::INVOICE_STATUSES))
            ->sum('remaining_amount');

        $openOrders = (int) $lock(DB::table('orders')
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->when($excludeOrderId, fn ($q) => $q->where('id', '!=', $excludeOrderId))
            ->whereNull('deleted_at')
            ->whereIn('status', self::ORDER_STATUSES)
            ->selectRaw('COALESCE(SUM(CASE WHEN total_ttc > COALESCE(invoiced_amount, 0) THEN total_ttc - COALESCE(invoiced_amount, 0) ELSE 0 END), 0) AS amount'))
            ->value('amount');

        $deposits = (int) $lock(DB::table('client_payments')
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->where('status', 'confirme')
            ->where('is_acompte', true))
            ->sum('unallocated_amount');

        $newOrder = max(0, $newOrderAmount);
        $projected = max(0, $outstanding + $openOrders + $newOrder - $deposits);

        return [
            'limited' => $limited,
            'limit' => $limit,
            'outstanding' => $outstanding,
            'open_orders' => $openOrders,
            'new_order' => $newOrder,
            'deposits' => $deposits,
            'projected' => $projected,
            'available' => $limited ? max(0, $limit - $projected) : PHP_INT_MAX,
        ];
    }

    /**
     * Exposition induite par la soumission d'une commande donnée.
     *
     * @return array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int}
     */
    public function assess(Order $order, bool $lockClient = false): array
    {
        $clientQuery = Client::query()->whereKey($order->client_id);
        if ($lockClient) {
            $clientQuery->lockForUpdate();
        }

        $client = $clientQuery->firstOrFail();

        return $this->compute(
            companyId: (int) $order->company_id,
            clientId: (int) $client->id,
            limit: (int) $client->credit_limit,
            isCredit: $client->isCredit(),
            newOrderAmount: (int) $order->total_ttc,
            excludeOrderId: $order->id ? (int) $order->id : null,
            // Le verrou client seul ne suffit pas : les agrégats doivent être lus
            // en lecture verrouillante pour voir les commandes concurrentes déjà
            // committées. Voir compute().
            lockRows: $lockClient,
        );
    }

    /**
     * Exposition courante d'un client, sans nouvelle commande — la valeur à
     * afficher partout où un écran annonce « encours » ou « disponible ».
     *
     * @return array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int}
     */
    public function assessClient(Client $client, ?int $companyId = null): array
    {
        return $this->compute(
            companyId: (int) ($companyId ?? currentCompany()->id),
            clientId: (int) $client->id,
            limit: (int) $client->credit_limit,
            isCredit: $client->isCredit(),
        );
    }

    /**
     * Garde de soumission. Doit s'exécuter dans la transaction qui verrouille le
     * client, sinon deux commandes concurrentes liraient le même encours.
     *
     * @return array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int}
     */
    public function assertMaySubmit(Order $order): array
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Le contrôle de crédit doit être exécuté dans une transaction.');
        }

        $exposure = $this->assess($order, true);
        if ($exposure['limited'] && $exposure['projected'] > $exposure['limit']) {
            throw new RuntimeException(sprintf(
                'Commande bloquée : encours prévisionnel %s FCFA supérieur au plafond %s FCFA '
                .'(factures %s + commandes ouvertes %s + nouvelle commande %s - acomptes %s).',
                number_format($exposure['projected'], 0, ',', ' '),
                number_format($exposure['limit'], 0, ',', ' '),
                number_format($exposure['outstanding'], 0, ',', ' '),
                number_format($exposure['open_orders'], 0, ',', ' '),
                number_format($exposure['new_order'], 0, ',', ' '),
                number_format($exposure['deposits'], 0, ',', ' '),
            ));
        }

        return $exposure;
    }
}
