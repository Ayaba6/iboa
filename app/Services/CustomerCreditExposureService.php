<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contrôle transactionnel de l'exposition crédit d'un client.
 *
 * Exposition prévisionnelle = factures ouvertes + commandes ouvertes non facturées
 * + nouvelle commande - acomptes confirmés non affectés.
 */
class CustomerCreditExposureService
{
    /** @return array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int} */
    public function assess(Order $order, bool $lockClient = false): array
    {
        $companyId = (int) $order->company_id;
        $clientQuery = Client::query()->whereKey($order->client_id);
        if ($lockClient) {
            $clientQuery->lockForUpdate();
        }

        $client = $clientQuery->firstOrFail();
        $limit = max(0, (int) $client->credit_limit);
        $limited = $client->isCredit() && $limit > 0;

        $outstanding = (int) DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->sum('remaining_amount');

        $openOrders = (int) DB::table('orders')
            ->where('company_id', $companyId)
            ->where('client_id', $client->id)
            ->where('id', '!=', $order->id)
            ->whereNull('deleted_at')
            ->whereIn('status', ['en_attente_validation', 'confirme', 'en_preparation', 'partiellement_livre', 'livre'])
            ->selectRaw('COALESCE(SUM(CASE WHEN total_ttc > COALESCE(invoiced_amount, 0) THEN total_ttc - COALESCE(invoiced_amount, 0) ELSE 0 END), 0) AS amount')
            ->value('amount');

        $deposits = (int) DB::table('client_payments')
            ->where('company_id', $companyId)
            ->where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->where('status', 'confirme')
            ->where('is_acompte', true)
            ->sum('unallocated_amount');

        $newOrder = max(0, (int) $order->total_ttc);
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

    /** @return array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int} */
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