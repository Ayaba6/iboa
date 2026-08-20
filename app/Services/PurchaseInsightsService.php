<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;

/**
 * [ACHATS-PRO] Indicateurs avancés du module Achats :
 *   - Dashboard KPIs
 *   - 3-way matching (écarts PO ↔ Réception ↔ Facture)
 *   - Évaluation fournisseur (taux service, délai, retour, volume)
 */
class PurchaseInsightsService
{
    /**
     * KPIs synthétiques pour le tableau de bord achats.
     */
    public function dashboardKpis(): array
    {
        // POs en cours = brouillon + envoyé + confirmé (non terminés)
        $openPoCount = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereIn('status', PurchaseOrder::STATUSES_OPEN)
            ->count();

        $openPoValue = (int) DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereIn('status', PurchaseOrder::STATUSES_OPEN)
            ->sum('total_ttc');

        // À recevoir = PO confirmées avec qté commandée > qté reçue
        $awaitingReceipt = (int) DB::table('purchase_orders as po')
            ->join('purchase_order_items as poi', 'poi.purchase_order_id', '=', 'po.id')
            ->whereNull('po.deleted_at')
            ->whereIn('po.status', PurchaseOrder::STATUSES_AWAITING_RECEIPT)
            ->whereColumn('poi.received_quantity', '<', 'poi.quantity')
            ->distinct('po.id')
            ->count('po.id');

        // FF à payer (validée mais non payée totalement)
        $invoicesToPayCount = DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', ['validee', 'partiellement_payee', 'en_retard'])
            ->where('remaining_amount', '>', 0)
            ->count();

        $invoicesToPayAmount = (int) DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', ['validee', 'partiellement_payee', 'en_retard'])
            ->sum('remaining_amount');

        // Échéances proches (7 jours)
        $today  = now()->toDateString();
        $in7d   = now()->addDays(7)->toDateString();
        $dueSoon = DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['validee', 'partiellement_payee'])
            ->whereBetween('due_at', [$today, $in7d])
            ->count();

        // En retard
        $overdue = DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['validee', 'partiellement_payee', 'en_retard'])
            ->where('due_at', '<', $today)
            ->count();

        $overdueAmount = (int) DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['validee', 'partiellement_payee', 'en_retard'])
            ->where('due_at', '<', $today)
            ->sum('remaining_amount');

        // Demandes d'achat en attente
        $pendingRequests = DB::table('purchase_requests')
            ->whereNull('deleted_at')
            ->whereIn('status', ['soumise', 'submitted', 'en_attente'])
            ->count();

        // Volume mois courant (PO confirmées ou plus)
        $monthVolume = (int) DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereYear('ordered_at', now()->year)
            ->whereMonth('ordered_at', now()->month)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->sum('total_ttc');

        $prevMonthVolume = (int) DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereYear('ordered_at', now()->subMonth()->year)
            ->whereMonth('ordered_at', now()->subMonth()->month)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->sum('total_ttc');

        $volumeVariation = $prevMonthVolume > 0
            ? round(($monthVolume - $prevMonthVolume) / $prevMonthVolume * 100, 1)
            : null;

        // [DPO] Days Payable Outstanding — délai moyen de paiement des fournisseurs
        // (encours / achats 90 derniers jours) × 90
        $purchases90 = (int) DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon','annulee'])
            ->where('received_at', '>=', now()->subDays(90))
            ->sum('total_ttc');
        $dpo = $purchases90 > 0 ? round($invoicesToPayAmount / $purchases90 * 90, 0) : 0;

        return [
            'open_po_count'         => $openPoCount,
            'open_po_value'         => $openPoValue,
            'awaiting_receipt'      => $awaitingReceipt,
            'invoices_to_pay_count' => $invoicesToPayCount,
            'invoices_to_pay_amount'=> $invoicesToPayAmount,
            'due_soon'              => $dueSoon,
            'overdue'               => $overdue,
            'overdue_amount'        => $overdueAmount,
            'pending_requests'      => $pendingRequests,
            'month_volume'          => $monthVolume,
            'prev_month_volume'     => $prevMonthVolume,
            'volume_variation_pct'  => $volumeVariation,
            'dpo_days'              => $dpo,
        ];
    }

    /**
     * [ACHATS-PRO] Pipeline PO par statut — équivalent funnel Odoo achats.
     */
    public function purchaseOrdersPipeline(): array
    {
        $rows = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_ttc) as total'))
            ->groupBy('status')
            ->get();

        // Aucune de ces cles ne correspondait a l'enumeration reelle : le
        // regroupement par statut rendait donc des libelles vides.
        $labels = PurchaseOrder::STATUS_LABELS;

        $result = [];
        foreach ($labels as $k => $label) {
            $row = $rows->firstWhere('status', $k);
            $result[$k] = [
                'label' => $label,
                'count' => $row?->count ?? 0,
                'total' => (int) ($row?->total ?? 0),
            ];
        }
        return $result;
    }

    /**
     * [ACHATS-PRO] Top fournisseurs par CA achats (12 derniers mois).
     * Diffère du scorecard qui mesure la qualité — ici on mesure le VOLUME.
     */
    public function topSuppliers(int $limit = 5): Collection
    {
        $from = now()->subMonths(12)->startOfMonth();
        return DB::table('supplier_invoices as si')
            ->join('suppliers as s', 's.id', '=', 'si.supplier_id')
            ->whereNull('si.deleted_at')
            ->whereNotIn('si.status', ['brouillon', 'annulee'])
            ->where('si.received_at', '>=', $from)
            ->select('s.id', 's.name',
                DB::raw('COUNT(DISTINCT si.id) as invoices_count'),
                DB::raw('SUM(si.total_ttc) as total_ttc'),
                DB::raw('SUM(si.remaining_amount) as outstanding'))
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total_ttc')
            ->limit($limit)
            ->get();
    }

    /**
     * [ACHATS-PRO] Top articles achetés sur 12 mois (qté + montant).
     */
    public function topPurchasedProducts(int $limit = 5): Collection
    {
        $from = now()->subMonths(12)->startOfMonth();
        return DB::table('supplier_invoice_items as it')
            ->join('supplier_invoices as si', 'si.id', '=', 'it.supplier_invoice_id')
            ->join('products as p', 'p.id', '=', 'it.product_id')
            ->whereNull('si.deleted_at')
            ->whereNotIn('si.status', ['brouillon', 'annulee'])
            ->where('si.received_at', '>=', $from)
            ->select('p.id', 'p.reference', 'p.name',
                DB::raw('SUM(it.quantity) as qty_bought'),
                DB::raw('SUM(it.line_total_ht) as total_ht'))
            ->groupBy('p.id', 'p.reference', 'p.name')
            ->orderByDesc('total_ht')
            ->limit($limit)
            ->get();
    }

    /**
     * [ACHATS-PRO] Évolution mensuelle des achats (12 mois) — pour graphique.
     */
    public function monthlyPurchaseEvolution(int $months = 12): Collection
    {
        $from = now()->subMonths($months - 1)->startOfMonth();
        return DB::table('supplier_invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('received_at', '>=', $from)
            ->select(
                DB::raw('DATE_FORMAT(received_at, "%Y-%m") as month'),
                DB::raw('SUM(total_ttc) as total_ttc'),
                DB::raw('COUNT(*) as invoices_count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Factures fournisseur à échéance dans les N jours, et en retard.
     */
    public function upcomingDueInvoices(int $days = 30): Collection
    {
        $today = now()->toDateString();
        $limit = now()->addDays($days)->toDateString();

        return DB::table('supplier_invoices as i')
            ->join('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->whereNull('i.deleted_at')
            ->where('i.remaining_amount', '>', 0)
            ->whereIn('i.status', ['validee', 'partiellement_payee', 'en_retard'])
            ->where(function ($q) use ($limit) {
                $q->whereNull('i.due_at')->orWhere('i.due_at', '<=', $limit);
            })
            ->select(
                'i.id', 'i.number', 'i.supplier_invoice_number',
                'i.status', 'i.received_at', 'i.due_at',
                'i.total_ttc', 'i.paid_amount', 'i.remaining_amount',
                's.name as supplier_name',
                DB::raw("CASE WHEN i.due_at < '{$today}' THEN 1 ELSE 0 END as is_overdue"),
                DB::raw("DATEDIFF(i.due_at, NOW()) as days_to_due")
            )
            ->orderBy('i.due_at')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3-WAY MATCHING : compare PO ↔ Réception ↔ Facture
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Détecte les écarts entre :
     *   - Quantité commandée   (purchase_order_items.quantity)
     *   - Quantité reçue       (reception_items.received_quantity, par PO item)
     *   - Quantité facturée    (purchase_order_items.invoiced_quantity)
     *
     * Et entre montants HT du PO et de la facture associée.
     */
    public function threeWayMatchingDiscrepancies(): array
    {
        // 1. Écarts quantitatifs par ligne PO — utilise WHERE EXISTS plutôt qu'un HAVING
        // sur alias (MySQL ne supporte pas systématiquement les alias de subquery dans HAVING
        // sans GROUP BY).
        $itemDiscrepancies = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->leftJoin('products as p', 'p.id', '=', 'poi.product_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin(DB::raw('(SELECT purchase_order_item_id, SUM(received_quantity) AS recv FROM reception_items GROUP BY purchase_order_item_id) AS r'),
                'r.purchase_order_item_id', '=', 'poi.id')
            ->whereNull('po.deleted_at')
            ->whereIn('po.status', PurchaseOrder::STATUSES_RECEIVED)
            ->whereRaw('(ABS(COALESCE(r.recv, 0) - poi.quantity) > 0.0001 OR ABS(poi.invoiced_quantity - COALESCE(r.recv, 0)) > 0.0001)')
            ->select(
                'po.id as po_id', 'po.number as po_number', 'po.ordered_at',
                's.name as supplier_name',
                'p.reference as product_ref', 'p.name as product_name',
                'poi.quantity as ordered_qty',
                DB::raw('COALESCE(r.recv, 0) as received_qty'),
                'poi.invoiced_quantity as invoiced_qty',
                DB::raw('COALESCE(r.recv, 0) - poi.quantity as recv_minus_ordered'),
                DB::raw('poi.invoiced_quantity - COALESCE(r.recv, 0) as invoiced_minus_received')
            )
            ->orderByDesc('po.ordered_at')
            ->limit(200)
            ->get();

        // 2. Écarts de montants entre PO et facture liée
        $amountDiscrepancies = DB::table('supplier_invoices as i')
            ->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
            ->join('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->whereNull('i.deleted_at')->whereNull('po.deleted_at')
            ->whereRaw('ABS(i.subtotal_ht - po.subtotal_ht) > 1')
            ->select(
                'i.id as invoice_id', 'i.number as invoice_number', 'i.supplier_invoice_number',
                'po.id as po_id', 'po.number as po_number',
                's.name as supplier_name',
                'po.subtotal_ht as po_amount',
                'i.subtotal_ht as inv_amount',
                DB::raw('i.subtotal_ht - po.subtotal_ht as gap')
            )
            ->orderByDesc(DB::raw('ABS(i.subtotal_ht - po.subtotal_ht)'))
            ->limit(100)
            ->get();

        return [
            'qty_discrepancies'    => $itemDiscrepancies,
            'amount_discrepancies' => $amountDiscrepancies,
            'qty_count'            => $itemDiscrepancies->count(),
            'amount_count'         => $amountDiscrepancies->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // ÉVALUATION FOURNISSEUR
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Scorecard pour chaque fournisseur :
     *   - Nombre de PO + volume total (12 derniers mois)
     *   - Délai moyen RÉEL livraison (jours entre PO confirmée et réception)
     *   - Taux de service = qté reçue / qté commandée
     *   - Taux de retour = montant retours / montant achats
     *   - Solde dû actuel
     *   - Note finale (1-5 étoiles)
     */
    public function supplierScorecards(int $months = 12): Collection
    {
        $from = now()->subMonths($months);
        $today = now()->toDateString();

        return DB::table('suppliers as s')
            ->whereNull('s.deleted_at')
            // Volume PO
            ->leftJoin(DB::raw("(
                SELECT supplier_id,
                       COUNT(*) as po_count,
                       SUM(total_ttc) as po_volume,
                       AVG(DATEDIFF(expected_at, ordered_at)) as promised_delay
                FROM purchase_orders
                WHERE deleted_at IS NULL
                  AND status NOT IN ('brouillon','annulee')
                  AND ordered_at >= '{$from->toDateString()}'
                GROUP BY supplier_id
            ) as po"), 'po.supplier_id', '=', 's.id')

            // Délais réels via réceptions
            ->leftJoin(DB::raw("(
                SELECT po.supplier_id,
                       AVG(DATEDIFF(r.received_at, po.ordered_at)) as avg_real_delay
                FROM receptions r
                JOIN purchase_orders po ON po.id = r.purchase_order_id
                WHERE r.deleted_at IS NULL AND r.status = 'validee'
                  AND r.received_at >= '{$from->toDateString()}'
                GROUP BY po.supplier_id
            ) as rd"), 'rd.supplier_id', '=', 's.id')

            // Taux de service : qté reçue / qté commandée
            ->leftJoin(DB::raw("(
                SELECT po.supplier_id,
                       SUM(poi.quantity) as qty_ordered,
                       SUM(COALESCE((SELECT SUM(received_quantity) FROM reception_items WHERE purchase_order_item_id = poi.id),0)) as qty_received
                FROM purchase_order_items poi
                JOIN purchase_orders po ON po.id = poi.purchase_order_id
                WHERE po.deleted_at IS NULL
                  AND po.status IN ('confirme','partiellement_recu','recu')
                  AND po.ordered_at >= '{$from->toDateString()}'
                GROUP BY po.supplier_id
            ) as sv"), 'sv.supplier_id', '=', 's.id')

            // Volume retours
            ->leftJoin(DB::raw("(
                SELECT supplier_id, SUM(total_ttc) as return_amount
                FROM supplier_returns
                WHERE deleted_at IS NULL AND status = 'validee'
                  AND returned_at >= '{$from->toDateString()}'
                GROUP BY supplier_id
            ) as ret"), 'ret.supplier_id', '=', 's.id')

            // Dû actuel
            ->leftJoin(DB::raw("(
                SELECT supplier_id, SUM(remaining_amount) as outstanding
                FROM supplier_invoices
                WHERE deleted_at IS NULL AND remaining_amount > 0
                GROUP BY supplier_id
            ) as outd"), 'outd.supplier_id', '=', 's.id')

            // Retards
            ->leftJoin(DB::raw("(
                SELECT supplier_id, COUNT(*) as overdue_count
                FROM supplier_invoices
                WHERE deleted_at IS NULL
                  AND remaining_amount > 0
                  AND due_at < '{$today}'
                GROUP BY supplier_id
            ) as ovd"), 'ovd.supplier_id', '=', 's.id')

            ->select(
                's.id', 's.code', 's.name', 's.rating as catalog_rating',
                's.avg_delivery_days as promised_avg',
                'po.po_count', 'po.po_volume', 'po.promised_delay',
                'rd.avg_real_delay',
                'sv.qty_ordered', 'sv.qty_received',
                'ret.return_amount',
                'outd.outstanding',
                'ovd.overdue_count',
                DB::raw('CASE WHEN sv.qty_ordered > 0
                    THEN ROUND(sv.qty_received / sv.qty_ordered * 100, 1)
                    ELSE NULL END as service_rate'),
                DB::raw('CASE WHEN po.po_volume > 0
                    THEN ROUND(COALESCE(ret.return_amount,0) / po.po_volume * 100, 2)
                    ELSE 0 END as return_rate'),
                DB::raw('CASE WHEN rd.avg_real_delay IS NOT NULL AND po.promised_delay IS NOT NULL
                    THEN ROUND(rd.avg_real_delay - po.promised_delay, 1)
                    ELSE NULL END as delay_gap')
            )
            ->where('s.is_active', 1)
            ->orderByDesc('po.po_volume')
            ->get()
            ->map(function ($s) {
                // Score composite 0-100 sur trois axes
                $serviceScore = $s->service_rate !== null ? min(100, $s->service_rate) : 50;
                $delayScore   = match (true) {
                    $s->delay_gap === null   => 50,
                    $s->delay_gap <= 0       => 100,
                    $s->delay_gap <= 3       => 80,
                    $s->delay_gap <= 7       => 60,
                    $s->delay_gap <= 14      => 40,
                    default                  => 20,
                };
                $returnScore = match (true) {
                    $s->return_rate === 0    => 100,
                    $s->return_rate <= 1     => 90,
                    $s->return_rate <= 3     => 70,
                    $s->return_rate <= 5     => 50,
                    default                  => 30,
                };
                $s->score = round(($serviceScore * 0.5) + ($delayScore * 0.3) + ($returnScore * 0.2), 0);
                $s->grade = match (true) {
                    $s->score >= 90 => 'A',
                    $s->score >= 75 => 'B',
                    $s->score >= 60 => 'C',
                    $s->score >= 40 => 'D',
                    default         => 'E',
                };
                return $s;
            });
    }
}
