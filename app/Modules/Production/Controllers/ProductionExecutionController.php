<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Notifications\ValidationStepNotification;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * [PRODUCTION] Exécution d'un OF : consommation matière, sorties PF, chutes.
 * Toutes les actions exigent production.update et un OF « en cours ».
 */
class ProductionExecutionController extends Controller
{
    public function __construct(
        private CoilConsumptionService $consumption,
        private ProductionStockService $stock,
        private AccountingService $accounting,
    ) {
        // [CDC §13.9] validateChef/validateQuality ont leur propre permission
        // (production.declare / quality.manage) — Chef Atelier et Responsable
        // Qualité n'ont pas production.update et ne doivent pas en avoir besoin
        // pour valider un rebut.
        $this->middleware('permission:production.update')->except(['validateChef', 'validateQuality', 'validateOutput']);
        // [CDC §13.3] Visa chef d'équipe sur les déclarations de production.
        $this->middleware('permission:production.validate_declaration')->only('validateOutput');
    }

    // ── Consommation matière ────────────────────────────────────────────────
    public function consume(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'coil_id'         => ['required', 'integer', 'exists:coils,id'],
            'weight_consumed' => ['required', 'numeric', 'gt:0'],
            'length_consumed' => ['nullable', 'numeric', 'min:0'],
            'consumed_at'     => ['nullable', 'date'],
        ]);

        $coil = Coil::findOrFail($data['coil_id']);
        $this->consumption->consume($order, $coil, (float) $data['weight_consumed'], $data['length_consumed'] ?? null, $data['consumed_at'] ?? null);

        return back()->with('success', 'Consommation enregistrée.');
    }

    public function destroyConsumption(ProductionConsumption $consumption): RedirectResponse
    {
        $this->consumption->reverse($consumption);

        return back()->with('success', 'Consommation annulée — poids restitué à la bobine.');
    }

    // ── Sortie produit fini ─────────────────────────────────────────────────
    public function output(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'product_id'   => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id', new \App\Rules\WarehouseAllows('can_production')],
            'length'       => ['nullable', 'numeric', 'min:0'],
            'quantity'     => ['required', 'numeric', 'gt:0'],
            'color'        => ['nullable', 'string', 'max:60'],
            'thickness'    => ['nullable', 'numeric', 'min:0'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0'],
            'lot_number'   => ['nullable', 'string', 'max:60'],
            'notes'        => ['nullable', 'string', 'max:500'],
            'produced_at'  => ['nullable', 'date'],
        ]);

        $output = $this->stock->recordOutput($order, $data);

        // [CDC §13.3] Déclaration opérateur → visa chef d'équipe requis avant
        // clôture de l'OF. Notification ciblée au Chef Atelier.
        ValidationStepNotification::sendToRoles(
            ['chef_atelier'],
            title: 'Déclaration de production à valider',
            message: "Sortie de {$output->quantity} u déclarée sur OF {$order->number} — visa chef d'équipe requis.",
            url: route('production.orders.show', $order),
            modelType: 'ProductionOutput',
            modelId: $output->id,
            type: 'output_declared',
            icon: 'clipboard-check',
            color: 'blue',
        );

        return back()->with('success', 'Production enregistrée et entrée en stock — en attente du visa chef d\'équipe.');
    }

    /**
     * [CDC §13.3] Visa chef d'équipe sur une déclaration de production.
     * L'entrée en stock a déjà eu lieu ; le visa atteste les quantités
     * déclarées et conditionne la clôture de l'OF.
     */
    public function validateOutput(ProductionOutput $output): RedirectResponse
    {
        if ($output->isValidated()) {
            return back()->with('error', 'Cette déclaration est déjà validée.');
        }

        $output->update([
            'status'       => 'validee',
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);

        return back()->with('success', 'Déclaration validée.');
    }

    public function destroyOutput(ProductionOutput $output): RedirectResponse
    {
        $this->stock->reverseOutput($output);

        return back()->with('success', 'Sortie de production annulée.');
    }

    // ── Chutes / pertes ─────────────────────────────────────────────────────
    public function waste(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'type'        => ['required', 'in:reutilisable,non_reutilisable,rebut'],
            'weight'      => ['nullable', 'numeric', 'min:0'],
            'quantity'    => ['nullable', 'numeric', 'min:0'],
            'machine_id'  => ['nullable', 'integer', 'exists:production_machines,id'],
            'operator_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reason'      => ['nullable', 'string', 'max:255'],
        ]);

        $waste = $this->stock->recordWaste($order, $data);

        // [CDC §13.9] Rebut déclaré → Chef Atelier analyse la cause en premier.
        if ($data['type'] === 'rebut' && $waste instanceof ProductionWaste) {
            ValidationStepNotification::sendToRoles(
                ['chef_atelier'],
                title: 'Rebut à analyser',
                message: "Rebut déclaré sur OF {$order->number} — analyse de cause requise.",
                url: route('production.orders.show', $order),
                modelType: 'ProductionWaste',
                modelId: $waste->id,
                type: 'waste_declared',
                icon: 'trash',
                color: 'red',
            );
        }

        return back()->with('success', 'Chute enregistrée.');
    }

    /**
     * [PHASE B/§4] Entrée en stock des sous-produits réels du suivi :
     * chute (au poids, kg) et avarié (à la quantité), via les articles liés
     * à la nomenclature (scrap_product_id / defect_product_id).
     */
    public function byproduct(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'scrap_weight'        => ['nullable', 'numeric', 'min:0'],
            'scrap_warehouse_id'  => ['nullable', 'integer', 'exists:warehouses,id'],
            'defect_quantity'     => ['nullable', 'numeric', 'min:0'],
            'defect_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $bom = $order->billOfMaterial;
        if (! $bom) {
            return back()->with('error', 'Aucune nomenclature liée à cet OF.');
        }

        $done = [];
        if (($data['scrap_weight'] ?? 0) > 0 && $bom->scrap_product_id) {
            $this->stock->enterByproduct($order, (int) $bom->scrap_product_id, (float) $data['scrap_weight'], $data['scrap_warehouse_id'] ?? null);
            $done[] = 'chute';
        }
        if (($data['defect_quantity'] ?? 0) > 0 && $bom->defect_product_id) {
            $this->stock->enterByproduct($order, (int) $bom->defect_product_id, (float) $data['defect_quantity'], $data['defect_warehouse_id'] ?? null);
            $done[] = 'avarié';
        }

        if (! $done) {
            return back()->with('error', 'Rien enregistré — vérifiez les articles chute/avarié de la nomenclature et les quantités.');
        }

        return back()->with('success', 'Entrée en stock : ' . implode(' + ', $done) . '.');
    }

    public function destroyWaste(ProductionWaste $waste): RedirectResponse
    {
        $this->stock->reverseWaste($waste);

        return back()->with('success', 'Chute supprimée.');
    }

    /**
     * [§13.9 CDC] Étape 1 — Chef Atelier valide l'analyse de cause du rebut.
     */
    public function validateChef(Request $request, ProductionWaste $waste): RedirectResponse
    {
        $data = $request->validate([
            'cause'            => ['required', 'in:' . implode(',', array_keys(ProductionWaste::CAUSES))],
            'corrective_action'=> ['nullable', 'string', 'max:500'],
        ]);

        if ($waste->validated_by_chef) {
            return back()->with('error', 'Déjà validé par le chef atelier.');
        }

        $waste->update([
            'cause'             => $data['cause'],
            'corrective_action' => $data['corrective_action'] ?? null,
            'validated_by_chef' => true,
        ]);

        // [CDC §13.9] Chef Atelier validé → Responsable Qualité valide ensuite.
        ValidationStepNotification::sendToRoles(
            ['responsable_qualite'],
            title: 'Rebut — validation qualité requise',
            message: "Rebut sur OF {$waste->productionOrder?->number} validé par le Chef Atelier — validation qualité en attente.",
            url: route('production.orders.show', $waste->production_order_id),
            modelType: 'ProductionWaste',
            modelId: $waste->id,
            type: 'waste_chef_validated',
            icon: 'trash',
            color: 'red',
        );

        return back()->with('success', 'Validation chef atelier enregistrée.');
    }

    /**
     * [§13.9 CDC] Étape 2 — Responsable Qualité valide puis déclenche la valorisation comptable.
     * Condition préalable : chef atelier doit avoir validé en premier.
     */
    public function validateQuality(ProductionWaste $waste): RedirectResponse
    {
        if (! $waste->validated_by_chef) {
            return back()->with('error', 'Le chef atelier doit valider en premier (§13.9 CDC).');
        }
        if ($waste->validated_by_quality) {
            return back()->with('error', 'Déjà validé par le responsable qualité.');
        }

        $waste->update(['validated_by_quality' => true]);

        // Valorisation comptable : DR 6582 / CR 321 (idempotent)
        if ($waste->value > 0) {
            $this->accounting->postWaste($waste);
        }

        return back()->with('success', 'Rebut validé qualité — écriture comptable 6582/321 générée.');
    }
}
