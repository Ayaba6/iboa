<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionQualityController extends Controller
{
    public function __construct()
    {
        // [FIX A3 — rapport de test MTO] L'enregistrement du contrôle qualité de l'OF
        // est ouvert au Responsable Qualité (quality.manage) en plus de la production.
        $this->middleware('permission:production.update|quality.manage')->only('store');
        $this->middleware('permission:production.update')->except('store');
    }

    public function store(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate([
            'status'            => ['required', 'in:conforme,non_conforme,a_reprendre'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'controller_id'     => ['nullable', 'integer', 'exists:employees,id'],
            'controlled_at'     => ['nullable', 'date'],
        ]);

        // [Audit OF — anti-doublon] Un contrôle strictement identique au dernier
        // enregistré le même jour (verdict + 4 critères + qté rejetée + motif)
        // est un double-clic ou une re-soumission, pas un nouveau contrôle.
        $last = $order->qualityControls()->latest('id')->first();
        if ($last
            && $last->status === $request->input('status')
            && $last->thickness_ok === $request->boolean('thickness_ok')
            && $last->length_ok === $request->boolean('length_ok')
            && $last->color_ok === $request->boolean('color_ok')
            && $last->visual_ok === $request->boolean('visual_ok')
            && (float) $last->rejected_quantity === (float) $request->input('rejected_quantity', 0)
            && ($last->reason ?? '') === ($request->input('reason') ?? '')
            && optional($last->controlled_at)->isSameDay($request->input('controlled_at') ?? now())) {
            return back()->with('error', 'Contrôle identique déjà enregistré aujourd\'hui — doublon ignoré.');
        }

        // [CDC §31.33 — séparation des tâches] Un déclarant de production ne peut pas
        // valider CONFORME sa propre production (auto-libération interdite). L'auto-signalement
        // d'un défaut (non_conforme / a_reprendre) reste permis. Dérogation : super_admin.
        if ($request->input('status') === 'conforme') {
            $declarerIds = \App\Modules\Production\Models\ProductionOutput::where('production_order_id', $order->id)
                ->pluck('created_by')->filter()->unique();
            $isDeclarer  = $declarerIds->contains(Auth::id());
            $canOverride = Auth::user()?->hasRole('super_admin') ?? false;
            if ($isDeclarer && ! $canOverride) {
                return back()->with('error', "Séparation des tâches : vous avez déclaré cette production, un autre contrôleur doit valider la conformité.");
            }
        }

        $order->qualityControls()->create([
            'company_id'        => $order->company_id,
            'thickness_ok'      => $request->boolean('thickness_ok'),
            'length_ok'         => $request->boolean('length_ok'),
            'color_ok'          => $request->boolean('color_ok'),
            'visual_ok'         => $request->boolean('visual_ok'),
            'status'            => $request->input('status'),
            'reason'            => $request->input('reason'),
            'rejected_quantity' => $request->input('rejected_quantity', 0),
            'controller_id'     => $request->input('controller_id'),
            'controlled_at'     => $request->input('controlled_at') ?? now(),
            'created_by'        => Auth::id(),
        ]);

        // [Audit OF] QC conforme posé sur un OF déjà clôturé (dérogation) :
        // rejoue les effets qualité — logique partagée avec la clôture.
        if ($order->status === 'termine' && $request->input('status') === 'conforme') {
            app(\App\Modules\Production\Services\ProductionService::class)
                ->applyQualityOutcomes($order, 'conforme');
        }

        return back()->with('success', 'Contrôle qualité enregistré.');
    }

    public function destroy(ProductionQualityControl $qualityControl): RedirectResponse
    {
        $qualityControl->delete();

        return back()->with('success', 'Contrôle qualité supprimé.');
    }
}
