<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [STOCK-PRO] Agrège les indicateurs avancés du module stock :
 *   - Dashboard KPIs (valorisation, ruptures, dormants, etc.)
 *   - Alertes réapprovisionnement (qty < reorder_point)
 *   - Articles dormants (aucun mouvement depuis N jours)
 *   - DLC proches / lots périmés
 *
 * Volontairement lecture seule : aucune mutation, juste de l'analyse.
 */
class StockInsightsService
{
    /** Seuil par défaut (jours) pour considérer un article comme dormant. */
    public const DORMANT_DAYS_DEFAULT = 90;

    /** Fenêtre par défaut (jours) pour les alertes DLC. */
    public const EXPIRY_WINDOW_DEFAULT = 30;

    /**
     * Dépôts hors du calcul de rupture.
     *
     * Une quarantaine vide est l'état SAIN : on y range ce qui attend un contrôle,
     * pas ce qu'on vend. Compter son vide comme une rupture transforme un signal
     * d'alerte en bruit permanent. Même raisonnement pour rebuts et chutes, qui
     * n'ont ni seuil ni vocation à être réapprovisionnés.
     */
    public const DEPOTS_HORS_RUPTURE = ['quarantaine', 'rebuts', 'chutes'];

    /**
     * Base commune à tous les indicateurs de disponibilité.
     *
     * Part de `products` et NON de `product_stocks` : un article sans ligne de
     * stock n'est pas un article sans problème, c'est un article à zéro. Le
     * partir de la table des stocks rendait invisibles ceux qui n'en ont
     * jamais eu — précisément ceux qu'un écran de réapprovisionnement existe
     * pour montrer.
     */
    public function disponibiliteQuery()
    {
        return DB::table('products as p')
            ->leftJoin('product_stocks as ps', function ($j) {
                $j->on('ps.product_id', '=', 'p.id')
                    ->whereNotIn('ps.warehouse_id', function ($q) {
                        $q->select('id')->from('warehouses')
                            ->whereIn('type', self::DEPOTS_HORS_RUPTURE);
                    });
            })
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->where('p.is_stockable', 1)
            ->groupBy('p.id')
            ->select(
                'p.id', 'p.code_article', 'p.name',
                'p.stock_min', 'p.stock_max', 'p.reorder_point',
                DB::raw('COALESCE(SUM(ps.quantity), 0) - COALESCE(SUM(ps.reserved_quantity), 0) AS dispo')
            );
    }

    /**
     * Articles en rupture : disponible ≤ 0 SUR UN ARTICLE QU'ON TIENT EN STOCK.
     *
     * Compte des ARTICLES, jamais des lignes de stock : un même article présent
     * sur trois dépôts est un article en rupture, pas trois.
     *
     * La politique de stock — un minimum ou un point de commande — est ce qui
     * distingue une rupture d'une simple absence. Un sous-produit alimenté par
     * les seules déclarations de production est à zéro par nature ; le déclarer
     * en rupture noierait les vraies alertes sous des articles dont le zéro est
     * l'état normal. Même règle que le calcul des besoins, qui classe « non
     * paramétré » tout article sans cible ni seuil.
     *
     * Les articles stockables sans politique restent visibles par
     * {@see self::sansPolitiqueStockQuery()} : ils relèvent d'un défaut de
     * paramétrage, pas d'une alerte d'exploitation.
     */
    public function ruptureQuery()
    {
        return $this->disponibiliteQuery()
            ->havingRaw('dispo <= 0 AND (p.stock_min > 0 OR p.reorder_point > 0)');
    }

    /** Articles stockables dépourvus de toute politique de stock. */
    public function sansPolitiqueStockQuery()
    {
        return $this->disponibiliteQuery()
            ->havingRaw('COALESCE(p.stock_min, 0) = 0 AND COALESCE(p.reorder_point, 0) = 0');
    }

    /**
     * Articles sous leur minimum, rupture EXCLUE.
     *
     * Un article à zéro est déjà compté en rupture ; le compter aussi ici le
     * ferait apparaître deux fois dans un total censé partitionner la situation.
     */
    public function sousMinimumQuery()
    {
        return $this->disponibiliteQuery()
            ->havingRaw('p.stock_min > 0 AND dispo > 0 AND dispo < p.stock_min');
    }

    /**
     * Articles EN TENSION : sous leur minimum, rupture comprise.
     *
     * Les écrans stock séparent rupture et sous-minimum pour ne pas compter deux
     * fois le même article dans un total. Un filtre de liste, lui, veut les deux :
     * « montre-moi ce qui manque » n'exclut pas ce qui manque totalement.
     */
    public function enTensionQuery()
    {
        return $this->disponibiliteQuery()
            ->havingRaw('p.stock_min > 0 AND dispo < p.stock_min');
    }

    /** Articles ayant atteint leur point de commande. */
    public function pointDeCommandeQuery()
    {
        return $this->disponibiliteQuery()
            ->havingRaw('p.reorder_point > 0 AND dispo <= p.reorder_point');
    }

    /** Compte les articles d'une requête agrégée (GROUP BY + HAVING). */
    public function compter($query): int
    {
        return DB::query()->fromSub($query, 'x')->count();
    }

    /**
     * KPIs synthétiques pour la page d'accueil stock.
     */
    public function dashboardKpis(): array
    {
        // Valorisation totale = Σ(quantity × avg_cost) sur tous les stocks
        $totalValuation = (int) DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->selectRaw('COALESCE(SUM(ps.quantity * COALESCE(ps.avg_cost, p.weighted_avg_cost, p.purchase_price, 0)), 0) AS v')
            ->value('v');

        // Compteurs
        $activeProducts = (int) Product::where('is_active', 1)->whereNull('deleted_at')->count();

        // Trois compteurs, une seule définition de la disponibilité — sinon deux
        // écrans du même ERP répondent différemment à la même question.
        $ruptureCount  = $this->compter($this->ruptureQuery());
        $belowMinCount = $this->compter($this->sousMinimumQuery());
        $reorderCount  = $this->compter($this->pointDeCommandeQuery());

        $dormantCount = $this->dormantProductsQuery(self::DORMANT_DAYS_DEFAULT)->count();

        $expiringCount = $this->expiringLotsQuery(self::EXPIRY_WINDOW_DEFAULT)->count();
        $expiredCount  = $this->expiredLotsQuery()->count();

        // Réservations en cours (qté réservée totale) — même périmètre que la valorisation
        $reservedValue = (int) DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->selectRaw('COALESCE(SUM(ps.reserved_quantity * COALESCE(ps.avg_cost, p.weighted_avg_cost, p.purchase_price, 0)), 0) AS v')
            ->value('v');

        return [
            'total_valuation' => $totalValuation,
            'reserved_value'  => $reservedValue,
            'active_products' => $activeProducts,
            'rupture_count'   => $ruptureCount,
            'below_min_count' => $belowMinCount,
            'reorder_count'   => $reorderCount,
            'dormant_count'   => $dormantCount,
            'expiring_count'  => $expiringCount,
            'expired_count'   => $expiredCount,
        ];
    }

    /**
     * Top N articles par valorisation (qté × coût moyen pondéré).
     */
    public function topValuationProducts(int $limit = 10): Collection
    {
        return DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'ps.warehouse_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->select(
                'p.id', 'p.reference', 'p.name',
                'ps.warehouse_id', 'w.name as warehouse_name',
                'ps.quantity', 'ps.avg_cost',
                DB::raw('(ps.quantity * COALESCE(ps.avg_cost, p.weighted_avg_cost, p.purchase_price, 0)) AS valuation')
            )
            ->orderByDesc('valuation')
            ->limit($limit)
            ->get();
    }

    /**
     * Top N produits par volume de mouvements ce mois.
     */
    public function topMovedProductsThisMonth(int $limit = 10): Collection
    {
        return DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->whereMonth('m.occurred_at', now()->month)
            ->whereYear('m.occurred_at', now()->year)
            ->select(
                'p.id', 'p.reference', 'p.name',
                DB::raw("SUM(CASE WHEN m.type IN ('entree','retour_client') THEN m.quantity ELSE 0 END) AS qty_in"),
                DB::raw("SUM(CASE WHEN m.type IN ('sortie','retour_fournisseur') THEN m.quantity ELSE 0 END) AS qty_out"),
                DB::raw('SUM(m.quantity) AS total_volume')
            )
            ->groupBy('p.id', 'p.reference', 'p.name')
            ->orderByDesc('total_volume')
            ->limit($limit)
            ->get();
    }

    /**
     * Liste des alertes de réapprovisionnement : qté dispo ≤ reorder_point.
     */
    public function restockAlertsQuery()
    {
        // Une alerte par ARTICLE, pas par ligne de dépôt : on passe commande
        // d'un article. Le même article en tension sur trois dépôts est une
        // commande à passer, pas trois. Et le LEFT JOIN fait entrer les articles
        // dépourvus de toute ligne de stock — les plus urgents de tous.
        return DB::table('products as p')
            ->leftJoin('product_stocks as ps', function ($j) {
                $j->on('ps.product_id', '=', 'p.id')
                    ->whereNotIn('ps.warehouse_id', function ($q) {
                        $q->select('id')->from('warehouses')
                            ->whereIn('type', self::DEPOTS_HORS_RUPTURE);
                    });
            })
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.main_warehouse_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.default_supplier_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->where('p.is_stockable', 1)
            ->where('p.reorder_point', '>', 0)
            ->groupBy(
                'p.id', 'p.reference', 'p.code_article', 'p.name', 'p.barcode',
                'p.stock_min', 'p.stock_max', 'p.reorder_point',
                'p.purchase_price', 'p.last_purchase_price',
                'p.default_supplier_id', 's.name', 'p.main_warehouse_id', 'w.name'
            )
            ->havingRaw('(COALESCE(SUM(ps.quantity), 0) - COALESCE(SUM(ps.reserved_quantity), 0)) <= p.reorder_point')
            ->select(
                'p.id', 'p.reference', 'p.code_article', 'p.name', 'p.barcode',
                'p.stock_min', 'p.stock_max', 'p.reorder_point',
                'p.purchase_price', 'p.last_purchase_price',
                'p.default_supplier_id', 's.name as supplier_name',
                // Le dépôt affiché est le dépôt PRINCIPAL de l'article : c'est là
                // qu'on réceptionnera, et il reste défini même sans stock.
                'p.main_warehouse_id as warehouse_id', 'w.name as warehouse_name',
                DB::raw('COALESCE(SUM(ps.quantity), 0) AS quantity'),
                DB::raw('COALESCE(SUM(ps.reserved_quantity), 0) AS reserved_quantity'),
                DB::raw('COALESCE(SUM(ps.quantity), 0) - COALESCE(SUM(ps.reserved_quantity), 0) AS available_qty'),
                // Suggestion à commander : combler jusqu'au maximum, et au pire
                // franchir le point de commande.
                // CASE plutôt que GREATEST : cette fonction n'existe pas en
                // SQLite, moteur sur lequel tourne la moitié de la suite de tests.
                DB::raw(
                    'CASE WHEN (COALESCE(p.stock_max, 0) - (COALESCE(SUM(ps.quantity), 0) - COALESCE(SUM(ps.reserved_quantity), 0)))'
                    .' > (p.reorder_point + 1)'
                    .' THEN (COALESCE(p.stock_max, 0) - (COALESCE(SUM(ps.quantity), 0) - COALESCE(SUM(ps.reserved_quantity), 0)))'
                    .' ELSE (p.reorder_point + 1) END AS suggested_qty'
                )
            )
            ->orderBy('available_qty');
    }

    /**
     * Articles dormants : aucun mouvement depuis $days jours.
     */
    public function dormantProductsQuery(int $days = self::DORMANT_DAYS_DEFAULT)
    {
        $threshold = now()->subDays($days);

        return DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'ps.warehouse_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->where('ps.quantity', '>', 0)
            ->where(function ($q) use ($threshold) {
                $q->where('ps.last_movement_at', '<', $threshold)
                  ->orWhereNull('ps.last_movement_at');
            })
            ->select(
                'p.id', 'p.reference', 'p.name',
                'ps.warehouse_id', 'w.name as warehouse_name',
                'ps.quantity', 'ps.avg_cost', 'ps.last_movement_at',
                DB::raw('(ps.quantity * COALESCE(ps.avg_cost, p.weighted_avg_cost, p.purchase_price, 0)) AS immobilized_value'),
                DB::raw('IF(ps.last_movement_at IS NULL, NULL, DATEDIFF(NOW(), ps.last_movement_at)) AS days_idle')
            )
            ->orderByDesc('immobilized_value');
    }

    /**
     * Lots dont la DLC arrive dans les $days jours (mais pas encore périmés).
     */
    public function expiringLotsQuery(int $days = self::EXPIRY_WINDOW_DEFAULT)
    {
        $now    = now()->toDateString();
        $limit  = now()->addDays($days)->toDateString();

        return DB::table('stock_lots as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->where('l.quantity', '>', 0)
            ->whereNotNull('l.expiry_date')
            ->whereBetween('l.expiry_date', [$now, $limit])
            ->select(
                'l.id', 'l.lot_number', 'l.serial_number', 'l.expiry_date',
                'l.quantity', 'l.unit_cost',
                'p.id as product_id', 'p.reference', 'p.name',
                'w.name as warehouse_name',
                DB::raw('DATEDIFF(l.expiry_date, NOW()) AS days_left')
            )
            ->orderBy('l.expiry_date');
    }

    /**
     * [STOCK-PRO] Analyse ABC (Pareto 80/15/5).
     *
     * Classe chaque article en A/B/C selon un critère :
     *   - 'valuation' : valorisation actuelle (qté × CMP)
     *   - 'rotation'  : volume de mouvements sur les N derniers mois
     *   - 'ca'        : chiffre d'affaires généré (lignes facturées)
     *
     * Algorithme : tri décroissant par critère, cumul, classement :
     *   A = articles dont le cumul atteint 80 %
     *   B = articles suivants jusqu'à 95 % (donc tranche 80–95 %)
     *   C = reste (95–100 %)
     *
     * @return array{rows: array, totals: array}
     */
    public function abcAnalysis(string $criterion = 'valuation', int $monthsWindow = 12): array
    {
        $rows = match ($criterion) {
            'rotation' => $this->abcRotationData($monthsWindow),
            'ca'       => $this->abcCaData($monthsWindow),
            default    => $this->abcValuationData(),
        };

        // Tri décroissant + cumul
        usort($rows, fn($a, $b) => $b['value'] <=> $a['value']);
        $total = array_sum(array_column($rows, 'value'));

        $cumul = 0;
        foreach ($rows as $i => &$row) {
            $row['rank']    = $i + 1;
            $cumul         += $row['value'];
            $row['cumul']   = $cumul;
            $row['percent'] = $total > 0 ? $row['value'] / $total * 100 : 0;
            $row['cumul_percent'] = $total > 0 ? $cumul / $total * 100 : 0;
            $row['class']   = $row['cumul_percent'] <= 80 ? 'A'
                            : ($row['cumul_percent'] <= 95 ? 'B' : 'C');
        }
        unset($row);

        // Synthèse par classe
        $byClass = collect($rows)->groupBy('class')->map(fn($g) => [
            'count' => $g->count(),
            'value' => $g->sum('value'),
        ])->toArray();

        return [
            'rows'   => $rows,
            'total'  => $total,
            'count'  => count($rows),
            'by_class' => [
                'A' => $byClass['A'] ?? ['count' => 0, 'value' => 0],
                'B' => $byClass['B'] ?? ['count' => 0, 'value' => 0],
                'C' => $byClass['C'] ?? ['count' => 0, 'value' => 0],
            ],
        ];
    }

    private function abcValuationData(): array
    {
        return DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('p.is_active', 1)->whereNull('p.deleted_at')
            ->select(
                'p.id', 'p.reference', 'p.name',
                DB::raw('SUM(ps.quantity) AS quantity'),
                DB::raw('SUM(ps.quantity * COALESCE(ps.avg_cost, p.weighted_avg_cost, p.purchase_price, 0)) AS value')
            )
            ->groupBy('p.id','p.reference','p.name')
            ->having('value', '>', 0)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    private function abcRotationData(int $months): array
    {
        $from = now()->subMonths($months);
        return DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->where('p.is_active', 1)->whereNull('p.deleted_at')
            ->where('m.occurred_at', '>=', $from)
            ->whereIn('m.type', ['sortie', 'retour_fournisseur'])   // rotation = consommation
            ->select(
                'p.id', 'p.reference', 'p.name',
                DB::raw('SUM(m.quantity) AS quantity'),
                DB::raw('SUM(m.quantity) AS value')
            )
            ->groupBy('p.id','p.reference','p.name')
            ->having('value', '>', 0)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    private function abcCaData(int $months): array
    {
        $from = now()->subMonths($months);
        return DB::table('invoice_items as it')
            ->join('invoices as i', 'i.id', '=', 'it.invoice_id')
            ->join('products as p', 'p.id', '=', 'it.product_id')
            ->where('p.is_active', 1)->whereNull('p.deleted_at')
            ->where('i.deleted_at', null)
            ->where('i.status', '!=', 'brouillon')
            ->where('i.issued_at', '>=', $from)
            ->select(
                'p.id', 'p.reference', 'p.name',
                DB::raw('SUM(it.quantity) AS quantity'),
                DB::raw('SUM(it.line_total_ht) AS value')
            )
            ->groupBy('p.id','p.reference','p.name')
            ->having('value', '>', 0)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    /**
     * Lots dont la DLC est dépassée — à isoler / détruire.
     */
    public function expiredLotsQuery()
    {
        $now = now()->toDateString();
        return DB::table('stock_lots as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->where('l.quantity', '>', 0)
            ->whereNotNull('l.expiry_date')
            ->where('l.expiry_date', '<', $now)
            ->select(
                'l.id', 'l.lot_number', 'l.serial_number', 'l.expiry_date',
                'l.quantity', 'l.unit_cost',
                'p.id as product_id', 'p.reference', 'p.name',
                'w.name as warehouse_name',
                DB::raw('DATEDIFF(NOW(), l.expiry_date) AS days_expired')
            )
            ->orderBy('l.expiry_date');
    }
}
