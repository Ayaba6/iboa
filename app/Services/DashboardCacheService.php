<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache centralisé pour tous les dashboards ERP.
 *
 * TTL par défaut : 5 minutes (300s) — équilibre fraîcheur / performance.
 * Invalidation ciblée via flush() par module.
 *
 * Usage :
 *   DashboardCacheService::ventes($companyId)
 *   DashboardCacheService::invalidate('ventes', $companyId)
 */
class DashboardCacheService
{
    private const TTL = 300; // 5 minutes

    // ── Clé cache ──────────────────────────────────────────────────────
    private static function key(string $module, int $companyId, string $sub = ''): string
    {
        return "dash:{$module}:{$companyId}" . ($sub ? ":{$sub}" : '');
    }

    // ── Invalidation ────────────────────────────────────────────────────
    public static function invalidate(string $module, int $companyId): void
    {
        // Supprimer toutes les clés connues du module
        $keys = [
            self::key($module, $companyId),
            self::key($module, $companyId, 'kpis'),
            self::key($module, $companyId, 'chart'),
            self::key($module, $companyId, 'top'),
        ];
        foreach ($keys as $k) {
            Cache::forget($k);
        }
    }

    public static function invalidateAll(int $companyId): void
    {
        foreach (['ventes','achats','stocks','rh','compta','tresorerie','crm'] as $m) {
            self::invalidate($m, $companyId);
        }
    }

    // ── VENTES ──────────────────────────────────────────────────────────
    public static function ventes(int $companyId): array
    {
        return Cache::remember(self::key('ventes', $companyId), self::TTL, function () use ($companyId) {
            $now   = now();
            $month = $now->format('Y-m');

            $base = fn($model) => "SELECT COUNT(*) n, COALESCE(SUM(total_ttc),0) amt
                FROM {$model} WHERE company_id=? AND deleted_at IS NULL";

            return [
                'factures_mois'  => DB::selectOne(
                    "SELECT COUNT(*) n, COALESCE(SUM(total_ttc),0) amt FROM invoices
                     WHERE company_id=? AND status NOT IN ('brouillon','annulee')
                     AND DATE_FORMAT(issued_at,'%Y-%m')=? AND deleted_at IS NULL",
                    [$companyId, $month]
                ),
                'commandes_open' => DB::selectOne(
                    "SELECT COUNT(*) n FROM orders
                     WHERE company_id=? AND status NOT IN ('annulee','facture') AND deleted_at IS NULL",
                    [$companyId]
                )->n,
                'devis_open' => DB::selectOne(
                    "SELECT COUNT(*) n FROM quotes
                     WHERE company_id=? AND status IN ('brouillon','soumis','accepte') AND deleted_at IS NULL",
                    [$companyId]
                )->n,
                'ca_annee' => DB::selectOne(
                    "SELECT COALESCE(SUM(total_ttc),0) amt FROM invoices
                     WHERE company_id=? AND status='payee' AND issued_at >= ? AND issued_at < ? AND deleted_at IS NULL",
                    [$companyId, $now->copy()->startOfYear()->toDateTimeString(), $now->copy()->startOfYear()->addYear()->toDateTimeString()]
                )->amt,
                'impayees_count' => DB::selectOne(
                    "SELECT COUNT(*) n FROM invoices
                     WHERE company_id=? AND status='emise' AND remaining_amount>0 AND deleted_at IS NULL",
                    [$companyId]
                )->n,
                'impayees_amt' => DB::selectOne(
                    "SELECT COALESCE(SUM(remaining_amount),0) amt FROM invoices
                     WHERE company_id=? AND status='emise' AND deleted_at IS NULL",
                    [$companyId]
                )->amt,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    // ── ACHATS ──────────────────────────────────────────────────────────
    public static function achats(int $companyId): array
    {
        return Cache::remember(self::key('achats', $companyId), self::TTL, function () use ($companyId) {
            return [
                'commandes_open' => DB::selectOne(
                    "SELECT COUNT(*) n FROM purchase_orders
                     WHERE company_id=? AND status NOT IN ('recu','annule') AND deleted_at IS NULL",
                    [$companyId]
                )->n,
                'ff_impayees' => DB::selectOne(
                    "SELECT COUNT(*) n, COALESCE(SUM(total_ttc-COALESCE(paid_amount,0)),0) amt
                     FROM supplier_invoices
                     WHERE company_id=? AND status='validee' AND deleted_at IS NULL",
                    [$companyId]
                ),
                'fournisseurs_actifs' => DB::selectOne(
                    "SELECT COUNT(*) n FROM suppliers WHERE is_active=1 AND deleted_at IS NULL"
                )->n,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    // ── STOCKS ──────────────────────────────────────────────────────────
    public static function stocks(int $companyId): array
    {
        return Cache::remember(self::key('stocks', $companyId), self::TTL, function () use ($companyId) {
            return [
                'valeur_stock' => DB::selectOne(
                    "SELECT COALESCE(SUM(ps.quantity * COALESCE(ps.avg_cost, p.purchase_price, 0)),0) val
                     FROM product_stocks ps
                     JOIN products p ON p.id = ps.product_id
                     WHERE p.company_id=? AND p.deleted_at IS NULL",
                    [$companyId]
                )->val,
                'ruptures' => DB::selectOne(
                    "SELECT COUNT(*) n FROM product_stocks ps
                     JOIN products p ON p.id=ps.product_id
                     WHERE p.company_id=? AND ps.quantity<=0 AND p.is_stockable=1",
                    [$companyId]
                )->n,
                'sous_seuil' => DB::selectOne(
                    "SELECT COUNT(*) n FROM product_stocks ps
                     JOIN products p ON p.id=ps.product_id
                     WHERE p.company_id=? AND p.stock_min IS NOT NULL AND ps.quantity < p.stock_min",
                    [$companyId]
                )->n,
                'mouvements_7j' => DB::selectOne(
                    "SELECT COUNT(*) n FROM stock_movements sm
                     JOIN products p ON p.id=sm.product_id
                     WHERE p.company_id=? AND sm.occurred_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)",
                    [$companyId]
                )->n,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    // ── COMPTABILITÉ ─────────────────────────────────────────────────────
    public static function compta(int $companyId, int $fiscalYearId): array
    {
        return Cache::remember(
            self::key('compta', $companyId, "fy{$fiscalYearId}"),
            self::TTL,
            function () use ($companyId, $fiscalYearId) {
                return [
                    'ecritures_brouillon' => DB::selectOne(
                        "SELECT COUNT(*) n FROM journal_entries
                         WHERE company_id=? AND fiscal_year_id=? AND status='brouillon' AND deleted_at IS NULL",
                        [$companyId, $fiscalYearId]
                    )->n,
                    'tva_collectee' => DB::selectOne(
                        "SELECT COALESCE(SUM(jel.credit-jel.debit),0) amt
                         FROM journal_entry_lines jel
                         JOIN accounts a ON a.id=jel.account_id
                         JOIN journal_entries je ON je.id=jel.journal_entry_id
                         WHERE je.company_id=? AND je.fiscal_year_id=? AND je.status='valide'
                         AND a.code LIKE '4431%'",
                        [$companyId, $fiscalYearId]
                    )->amt,
                    'resultat_net' => DB::selectOne(
                        "SELECT COALESCE(SUM(
                             CASE WHEN a.code LIKE '7%' THEN jel.credit-jel.debit ELSE 0 END
                           - CASE WHEN a.code LIKE '6%' THEN jel.debit-jel.credit ELSE 0 END
                         ),0) net
                         FROM journal_entry_lines jel
                         JOIN accounts a ON a.id=jel.account_id
                         JOIN journal_entries je ON je.id=jel.journal_entry_id
                         WHERE je.company_id=? AND je.fiscal_year_id=? AND je.status='valide'",
                        [$companyId, $fiscalYearId]
                    )->net,
                    'cached_at' => now()->toIso8601String(),
                ];
            }
        );
    }

    // ── RH ───────────────────────────────────────────────────────────────
    public static function rh(int $companyId): array
    {
        return Cache::remember(self::key('rh', $companyId), self::TTL, function () use ($companyId) {
            return [
                'effectif_actif' => DB::selectOne(
                    "SELECT COUNT(*) n FROM employees WHERE company_id=? AND status='actif' AND deleted_at IS NULL",
                    [$companyId]
                )->n,
                'conges_en_attente' => DB::selectOne(
                    "SELECT COUNT(*) n FROM leave_requests lr
                     JOIN employees e ON e.id=lr.employee_id
                     WHERE e.company_id=? AND lr.status='pending'",
                    [$companyId]
                )->n ?? 0,
                'masse_salariale_mois' => DB::selectOne(
                    "SELECT COALESCE(SUM(net_salary),0) amt FROM payroll_items pi
                     JOIN employees e ON e.id=pi.employee_id
                     WHERE e.company_id=?
                     AND DATE_FORMAT(pi.created_at,'%Y-%m')=?",
                    [$companyId, now()->format('Y-m')]
                )->amt,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    // ── TRÉSORERIE ───────────────────────────────────────────────────────
    public static function tresorerie(int $companyId): array
    {
        return Cache::remember(self::key('tresorerie', $companyId), self::TTL, function () use ($companyId) {
            return [
                'solde_total' => DB::selectOne(
                    "SELECT COALESCE(SUM(current_balance),0) amt
                     FROM cash_accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL",
                    [$companyId]
                )->amt,
                'encaissements_mois' => DB::selectOne(
                    "SELECT COALESCE(SUM(amount),0) amt FROM client_payments
                     WHERE company_id=? AND deleted_at IS NULL
                     AND DATE_FORMAT(payment_date,'%Y-%m')=?",
                    [$companyId, now()->format('Y-m')]
                )->amt,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }
}
