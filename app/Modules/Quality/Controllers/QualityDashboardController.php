<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Quality\Models\CorrectiveAction;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use App\Modules\Quality\Models\QualityRelease;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [QUA-08] Tableau de bord indicateurs qualité : taux NC, rebut, délais,
 * efficacité CAPA, libérations, récurrence.
 */
class QualityDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:quality.view');
    }

    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);

        $ncYear = NonConformity::whereYear('created_at', $year);
        $insYear = QualityInspection::whereYear('created_at', $year);

        // Inspections & taux NC
        $insTotal = (clone $insYear)->count();
        $insNok   = (clone $insYear)->where('status', 'non_conforme')->count();
        $checked  = (float) (clone $insYear)->sum('quantity_checked');
        $rejected = (float) (clone $insYear)->sum('quantity_rejected');

        // Non-conformités
        $ncTotal = (clone $ncYear)->count();
        $ncBySeverity = [
            'mineure'  => (clone $ncYear)->where('severity', 'mineure')->count(),
            'majeure'  => (clone $ncYear)->where('severity', 'majeure')->count(),
            'critique' => (clone $ncYear)->where('severity', 'critique')->count(),
        ];
        $ncOpen = (clone $ncYear)->whereIn('status', ['ouverte', 'en_cours'])->count();
        $clientClaims = (clone $ncYear)->where('client_claim', true)->count();

        // Délai moyen de traitement (jours) sur NC clôturées
        $closed = (clone $ncYear)->where('status', 'cloturee')->whereNotNull('closed_at')
            ->get(['created_at', 'closed_at']);
        $avgLead = $closed->isEmpty() ? null
            : round($closed->avg(fn ($nc) => abs($nc->created_at->diffInDays($nc->closed_at))), 1);

        // CAPA
        $capaVerified = CorrectiveAction::whereYear('created_at', $year)->where('status', 'verifiee')->count();
        $capaEffective = CorrectiveAction::whereYear('created_at', $year)->where('is_effective', true)->count();
        $capaOverdue = CorrectiveAction::whereNotNull('due_date')->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['faite', 'verifiee', 'cloturee'])->count();

        // Libérations
        $relByStatus = [
            'libere'     => QualityRelease::whereYear('created_at', $year)->where('status', 'libere')->count(),
            'derogation' => QualityRelease::whereYear('created_at', $year)->where('status', 'derogation')->count(),
            'refuse'     => QualityRelease::whereYear('created_at', $year)->where('status', 'refuse')->count(),
            'en_attente' => QualityRelease::whereYear('created_at', $year)->where('status', 'en_attente')->count(),
        ];
        $relTotalDecided = $relByStatus['libere'] + $relByStatus['derogation'] + $relByStatus['refuse'];

        // Tendance mensuelle des NC
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[$m] = NonConformity::whereYear('created_at', $year)->whereMonth('created_at', $m)->count();
        }

        // Récurrence : top articles par nombre de NC
        $topProducts = (clone $ncYear)->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) as nc_count')
            ->groupBy('product_id')->orderByDesc('nc_count')->limit(5)->get();
        $productNames = Product::whereIn('id', $topProducts->pluck('product_id'))->pluck('name', 'id');

        $kpis = [
            'taux_nc'        => $insTotal ? round($insNok / $insTotal * 100, 1) : 0.0,
            'taux_rebut'     => $checked ? round($rejected / $checked * 100, 2) : 0.0,
            'nc_open'        => $ncOpen,
            'avg_lead'       => $avgLead,
            'capa_overdue'   => $capaOverdue,
            'capa_efficacite'=> $capaVerified ? round($capaEffective / $capaVerified * 100, 1) : null,
            'taux_refus'     => $relTotalDecided ? round($relByStatus['refuse'] / $relTotalDecided * 100, 1) : 0.0,
            'client_claims'  => $clientClaims,
        ];

        $years = range(now()->year, now()->year - 4);

        return view('qualite.dashboard.index', compact(
            'year', 'years', 'kpis', 'ncTotal', 'ncBySeverity', 'insTotal', 'insNok',
            'relByStatus', 'relTotalDecided', 'monthly', 'topProducts', 'productNames'
        ));
    }
}
