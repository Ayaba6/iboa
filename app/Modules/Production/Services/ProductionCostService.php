<?php

namespace App\Modules\Production\Services;

use App\Models\AnalyticLine;
use App\Models\CostCenter;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

/**
 * [PRODUCTION — CDC §Coût de revient industriel]
 * Calcul du coût de revient d'un OF, décomposé par nature :
 *
 * Coût total = matière + main-d'œuvre + machine + énergie + maintenance
 *            + emballage + frais indirects.
 *   - matière     : somme réelle des consommations de bobines
 *   - MO          : pointage réel (time logs) > override > BOM.labor_per_unit × qté
 *   - machine     : temps machine (BOM) × coût horaire machine (dépréciation)
 *   - énergie     : temps machine × energy_cost_per_hour de la machine
 *   - maintenance : temps machine × maintenance_cost_per_hour de la machine
 *   - emballage   : BOM.packaging_per_unit × quantité
 *   - indirect    : taux % appliqué sur la somme des coûts directs (ou override)
 *
 * Comparaison standard/réel : standard = Σ(std_*_cost BOM) × quantité,
 * variance = réel − standard (>0 défavorable).
 * Coût/mètre et coût/unité dérivés des sorties réelles.
 * Marge = chiffre d'affaires estimé − coût total.
 */
class ProductionCostService
{
    /**
     * Recalcule et persiste le coût de revient (1 ligne par OF).
     *
     * @param array $opts labor_cost, machine_cost, energy_cost, maintenance_cost,
     *                    packaging_cost, overhead_cost, overhead_rate, revenue
     */
    public function compute(ProductionOrder $order, array $opts = []): ProductionCost
    {
        $order->loadMissing(['consumptions', 'outputs', 'billOfMaterial', 'productionLine.machine', 'product', 'order']);

        // NB : quantity_produced casté decimal → "0.00" (TRUTHY). Caster en float
        // AVANT le ?: sinon un OF non encore produit prend 0 au lieu de la demande.
        $quantity = (float) $order->quantity_produced ?: (float) $order->quantity_requested;
        $bom      = $order->billOfMaterial;

        // 1. Matière — coût réel consommé = bobines (ProductionConsumption) +
        //    composants BOM sortis du stock (product_stocks) valorisés au CMP.
        //    Les composants non-bobine sont tracés en stock_movements (type sortie)
        //    référençant les déclarations de production (ProductionOutput) de l'OF.
        $material = (int) $order->consumptions->sum('cost');
        $outputIds = $order->outputs->pluck('id');
        if ($outputIds->isNotEmpty()) {
            $material += (int) \App\Models\StockMovement::where('type', 'sortie')
                ->where('reference_type', ProductionOutput::class)
                ->whereIn('reference_id', $outputIds)
                ->sum('total_cost');
        }

        // 2. Main-d'œuvre — priorité au pointage RÉEL (production_time_logs),
        //    sinon override manuel, sinon estimation nomenclature (BOM),
        //    sinon temps standard de la GAMME (opérations × coût horaire poste).
        $realLabor = (int) $order->timeLogs()->sum('labor_cost');
        if ($realLabor > 0) {
            $labor = $realLabor;
        } elseif (array_key_exists('labor_cost', $opts) && $opts['labor_cost'] !== null) {
            $labor = (int) $opts['labor_cost'];
        } else {
            $labor = (int) round((float) ($bom->labor_per_unit ?? 0) * $quantity);
        }

        // [Audit OF] Aucun pointage, ni override, ni MO nomenclature → repli sur
        // le temps standard de la gamme : Σ (minutes réelles sinon prévues) × coût
        // horaire du poste de charge. Sans ce repli, un OF gammé sans pointage
        // affiche MO = 0 et sous-évalue le coût de revient.
        if ($labor === 0) {
            $order->loadMissing('operations.workCenter');
            $labor = (int) round($order->operations->sum(function ($op) {
                // NB : real_minutes casté decimal → "0.00" (chaîne TRUTHY).
                // Caster en float AVANT le ?: sinon le repli planned est masqué.
                $minutes = (float) $op->real_minutes ?: (float) $op->planned_minutes;

                return ($minutes / 60) * (float) ($op->workCenter?->cost_per_hour ?? 0);
            }));
        }

        $machineHours = $this->machineMinutes($order, $quantity) / 60;

        // 3. Machine — temps (h) × coût horaire (dépréciation équipement)
        $machine = array_key_exists('machine_cost', $opts) && $opts['machine_cost'] !== null
            ? (int) $opts['machine_cost']
            : (int) round($machineHours * $this->hourlyCost($order));

        // 4. Énergie — temps (h) × coût énergie horaire de la machine
        $energy = array_key_exists('energy_cost', $opts) && $opts['energy_cost'] !== null
            ? (int) $opts['energy_cost']
            : (int) round($machineHours * (float) ($order->productionLine?->machine?->energy_cost_per_hour ?? 0));

        // 5. Maintenance — temps (h) × taux horaire maintenance de la machine
        $maintenance = array_key_exists('maintenance_cost', $opts) && $opts['maintenance_cost'] !== null
            ? (int) $opts['maintenance_cost']
            : (int) round($machineHours * (float) ($order->productionLine?->machine?->maintenance_cost_per_hour ?? 0));

        // 6. Emballage — coût unitaire nomenclature × quantité produite
        $packaging = array_key_exists('packaging_cost', $opts) && $opts['packaging_cost'] !== null
            ? (int) $opts['packaging_cost']
            : (int) round((float) ($bom->packaging_per_unit ?? 0) * $quantity);

        // 6bis. Sous-traitance — opérations externalisées (saisie manuelle)
        $subcontract = array_key_exists('subcontract_cost', $opts) && $opts['subcontract_cost'] !== null
            ? (int) $opts['subcontract_cost']
            : 0;

        // 7. Frais indirects — taux % sur la somme des coûts directs
        $direct = $material + $labor + $machine + $energy + $maintenance + $packaging + $subcontract;
        if (array_key_exists('overhead_cost', $opts) && $opts['overhead_cost'] !== null) {
            $overhead = (int) $opts['overhead_cost'];
        } else {
            $rate     = (float) ($opts['overhead_rate'] ?? 0);
            $overhead = (int) round($direct * $rate / 100);
        }

        $total = $direct + $overhead;

        // Coût standard (nomenclature × quantité) + écart réel/standard
        $standardTotal = (int) round((
            (float) ($bom->std_material_cost ?? 0)
            + (float) ($bom->std_labor_cost ?? 0)
            + (float) ($bom->std_machine_cost ?? 0)
            + (float) ($bom->std_energy_cost ?? 0)
            + (float) ($bom->std_maintenance_cost ?? 0)
            + (float) ($bom->std_packaging_cost ?? 0)
            + (float) ($bom->std_overhead_cost ?? 0)
        ) * $quantity);
        $variance = $standardTotal > 0 ? $total - $standardTotal : null; // >0 = défavorable

        // Bases unitaires
        $meters = (float) $order->outputs->sum('total_meters');
        if ($meters <= 0) {
            $meters = $order->totalMeters();
        }
        $costPerMeter = $meters > 0 ? round($total / $meters, 2) : 0;
        $costPerUnit  = $quantity > 0 ? round($total / $quantity, 2) : 0;

        // Marge
        $revenue = array_key_exists('revenue', $opts) && $opts['revenue'] !== null
            ? (float) $opts['revenue']
            : $this->estimateRevenue($order, $quantity);
        $margin = (int) round($revenue - $total);

        $cost = ProductionCost::updateOrCreate(
            ['production_order_id' => $order->id],
            [
                'company_id'       => $order->company_id,
                'material_cost'    => $material,
                'labor_cost'       => $labor,
                'machine_cost'     => $machine,
                'energy_cost'      => $energy,
                'maintenance_cost' => $maintenance,
                'packaging_cost'   => $packaging,
                'overhead_cost'    => $overhead,
                'subcontract_cost' => $subcontract,
                'total_cost'       => $total,
                'standard_total'   => $standardTotal,
                'variance'         => $variance,
                'cost_per_meter'   => $costPerMeter,
                'cost_per_unit'    => $costPerUnit,
                'margin'           => $margin,
                'created_by'       => Auth::id(),
            ]
        );

        $this->postAnalyticLines($order, $material, $labor, $machine, $energy, $maintenance, $packaging, $overhead);

        return $cost;
    }

    /**
     * [CDC §13.3/§15.2] Ventile le coût de revient calculé vers la comptabilité
     * analytique (rentabilité par ligne de production) — sans cette passerelle,
     * le module analytique restait une saisie 100% manuelle, déconnectée des
     * coûts réels de production. Idempotent : on resème les 4 lignes à chaque
     * recalcul, comme ProductionCost::updateOrCreate() le fait pour lui-même.
     */
    private function postAnalyticLines(ProductionOrder $order, int $material, int $labor, int $machine, int $energy, int $maintenance, int $packaging, int $overhead): void
    {
        $lineCode = $order->productionLine?->code ?? 'PROD';
        $costCenter = CostCenter::firstOrCreate(
            ['company_id' => $order->company_id, 'code' => 'LINE-' . $lineCode],
            ['name' => $order->productionLine?->name ?? 'Production (sans ligne)', 'type' => 'cost', 'is_active' => true]
        );

        AnalyticLine::where('ref_type', ProductionOrder::class)->where('ref_id', $order->id)->delete();

        $rows = [
            'matiere'     => $material,
            'main_oeuvre' => $labor,
            'machine'     => $machine,     // dépréciation équipement (temps × coût horaire)
            'energie'     => $energy,      // temps machine × coût énergie horaire
            'maintenance' => $maintenance, // temps machine × taux maintenance horaire
            'emballage'   => $packaging,   // coût unitaire BOM × quantité
            'overhead'    => $overhead,
        ];

        foreach ($rows as $category => $amount) {
            if ($amount <= 0) continue;
            AnalyticLine::create([
                'company_id'     => $order->company_id,
                'cost_center_id' => $costCenter->id,
                'ref_type'       => ProductionOrder::class,
                'ref_id'         => $order->id,
                'date'           => now()->toDateString(),
                'label'          => 'Coût revient OF ' . $order->number,
                'category'       => $category,
                'amount'         => $amount,
                'created_by'     => Auth::id(),
            ]);
        }
    }

    private function machineMinutes(ProductionOrder $order, float $quantity): float
    {
        return (float) ($order->billOfMaterial->machine_time_per_unit ?? 0) * $quantity;
    }

    private function hourlyCost(ProductionOrder $order): float
    {
        return (float) ($order->productionLine?->machine?->hourly_cost ?? 0);
    }

    private function estimateRevenue(ProductionOrder $order, float $quantity): float
    {
        if ($order->product && $order->product->sale_price) {
            return (float) $order->product->sale_price * $quantity;
        }

        return (float) ($order->order->total_ttc ?? 0);
    }
}
