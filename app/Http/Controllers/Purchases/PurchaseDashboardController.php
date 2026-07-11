<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\PurchaseInsightsService;
use App\Services\StockInsightsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * [ACHATS-PRO] Dashboard + insights avancés du module Achats.
 */
class PurchaseDashboardController extends Controller
{
    public function __construct(private PurchaseInsightsService $insights)
    {
        $this->middleware('can:purchase_orders.view');
    }

    public function dashboard(Request $request): View
    {
        $kpis      = $this->insights->dashboardKpis();
        $dueSoon   = $this->insights->upcomingDueInvoices(30)->take(10);
        $matching  = $this->insights->threeWayMatchingDiscrepancies();
        $matchingPreview = [
            'qty'    => $matching['qty_discrepancies']->take(5),
            'amount' => $matching['amount_discrepancies']->take(5),
            'qty_count'    => $matching['qty_count'],
            'amount_count' => $matching['amount_count'],
        ];
        $topScorecards = $this->insights->supplierScorecards(12)->take(5);

        // [ACHATS-PRO] Nouveaux indicateurs niveau Odoo/Sage
        $pipeline       = $this->insights->purchaseOrdersPipeline();
        $topSuppliers   = $this->insights->topSuppliers(5);
        $topProducts    = $this->insights->topPurchasedProducts(5);
        $monthly        = $this->insights->monthlyPurchaseEvolution(12);

        return view('achats.dashboard', compact(
            'kpis', 'dueSoon', 'matchingPreview', 'topScorecards',
            'pipeline', 'topSuppliers', 'topProducts', 'monthly'
        ));
    }

    public function matching(Request $request): View
    {
        $matching = $this->insights->threeWayMatchingDiscrepancies();
        return view('achats.insights.matching', compact('matching'));
    }

    public function suppliersScorecards(Request $request): View
    {
        $months = max(1, min(36, $request->integer('months', 12)));
        $scorecards = $this->insights->supplierScorecards($months);
        return view('achats.insights.suppliers', compact('scorecards', 'months'));
    }

    /**
     * [ACHATS-PRO] Génère un (ou plusieurs) PO depuis les alertes réappro,
     * regroupé par fournisseur par défaut. Un seul écran : sélection produits → submit
     * → un PO créé en brouillon par fournisseur.
     */
    public function restockToPo(Request $request)
    {
        if ($request->isMethod('GET')) {
            $alerts = app(StockInsightsService::class)->restockAlertsQuery()->get();
            // Group by supplier id (null suppliers go in 'sans')
            $grouped = $alerts->groupBy('default_supplier_id');
            return view('achats.insights.restock-po', compact('grouped'));
        }

        $data = $request->validate([
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'=> ['required', 'integer', 'exists:products,id'],
            'items.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'items.*.quantity'  => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'=> ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($data) {
            // Group lines by supplier
            $bySupplier = collect($data['items'])->groupBy('supplier_id');

            $created = [];
            foreach ($bySupplier as $supplierId => $lines) {
                $company = currentCompany();
                $supplier = Supplier::findOrFail($supplierId);

                $subtotal = 0;
                $itemsToCreate = [];
                foreach ($lines as $i => $line) {
                    $product = Product::findOrFail($line['product_id']);
                    $unitPrice = (float) ($line['unit_price'] ?? $product->last_purchase_price ?? $product->purchase_price ?? 0);
                    $qty       = (float) $line['quantity'];
                    $lineHt    = $unitPrice * $qty;
                    $subtotal += $lineHt;

                    $itemsToCreate[] = [
                        'product_id'        => $product->id,
                        'description'       => $product->name,
                        'quantity'          => $qty,
                        'unit_price'        => $unitPrice,
                        'discount_percent'  => 0,
                        'tax_rate_value'    => 0,
                        'line_total_ht'     => $lineHt,
                        'line_tax'          => 0,
                        'line_total_ttc'    => $lineHt,
                        'received_quantity' => 0,
                        'invoiced_quantity' => 0,
                        'sort_order'        => $i,
                    ];
                }

                $po = PurchaseOrder::create([
                    'company_id'     => $company->id,
                    'supplier_id'    => $supplierId,
                    'number'         => $this->generatePoNumber($company->id),
                    'status'         => 'brouillon',
                    'ordered_at'     => now()->toDateString(),
                    'expected_at'    => now()->addDays($supplier->avg_delivery_days ?: 7)->toDateString(),
                    'currency_code'  => 'XOF',
                    'exchange_rate'  => 1,
                    'subtotal_ht'    => $subtotal,
                    'total_tax'      => 0,
                    'total_ttc'      => $subtotal,
                    'notes'          => 'Généré automatiquement depuis les alertes de réapprovisionnement stock',
                    'created_by'     => auth()->id(),
                ]);

                foreach ($itemsToCreate as $item) {
                    $po->items()->create($item);
                }

                $created[] = $po;
            }

            $count = count($created);
            $msg = $count === 1
                ? "Bon de commande {$created[0]->number} créé en brouillon."
                : "{$count} bons de commande créés en brouillon (un par fournisseur).";

            if ($count === 1) {
                return redirect()->route('achats.commandes.show', $created[0])->with('success', $msg);
            }
            return redirect()->route('achats.commandes.index')->with('success', $msg);
        });
    }

    private function generatePoNumber(int $companyId): string
    {
        $prefix = 'BC-' . now()->format('Y');
        $last = PurchaseOrder::where('company_id', $companyId)
            ->where('number', 'like', $prefix . '-%')
            ->orderByDesc('id')->value('number');
        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
