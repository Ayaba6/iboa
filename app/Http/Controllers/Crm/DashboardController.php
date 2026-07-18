<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\{Auth, Cache};

class DashboardController extends Controller
{
    private const TTL = 300; // 5 minutes

    public function index()
    {
        $companyId = Auth::user()->company_id;
        $month     = now()->format('Y-m');

        // ── KPIs cachés 5 min ──────────────────────────────────────────
        $kpis = Cache::remember("crm:kpis:{$companyId}:{$month}", self::TTL, function () use ($companyId) {
            $stageRows = CrmOpportunity::forCompany($companyId)
                ->whereNotNull('stage')
                ->selectRaw('stage, COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total_amount')
                ->groupBy('stage')
                ->get()
                ->keyBy('stage');

            $stageStats = [];
            foreach (array_keys(CrmOpportunity::STAGES) as $stage) {
                $row = $stageRows->get($stage);
                $stageStats[$stage] = [
                    'count'  => $row ? (int) $row->cnt : 0,
                    'amount' => $row ? (float) $row->total_amount : 0.0,
                    'config' => CrmOpportunity::STAGES[$stage],
                ];
            }

            return [
                'totalContacts'     => CrmContact::forCompany($companyId)->count(),
                'newThisMonth'      => CrmContact::forCompany($companyId)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
                'openOpps'          => CrmOpportunity::forCompany($companyId)->active()->count(),
                'pipeline'          => CrmOpportunity::forCompany($companyId)->active()->sum('amount'),
                // Valeur pondérée = Σ(montant × probabilité) — le libellé « valeur
                // pondérée » affichait auparavant le pipeline brut.
                'weightedPipeline'  => (float) CrmOpportunity::forCompany($companyId)->active()
                    ->selectRaw('COALESCE(SUM(amount * probability / 100), 0) as w')->value('w'),
                'wonThisMonth'      => CrmOpportunity::forCompany($companyId)
                    ->where('stage', 'gagne')
                    ->whereMonth('updated_at', now()->month)
                    ->whereYear('updated_at', now()->year)->sum('amount'),
                'overdueActivities' => CrmActivity::forCompany($companyId)->overdue()->count(),
                'stageStats'        => $stageStats,
            ];
        });

        // ── Données temps-réel (listes courtes — pas de cache) ─────────
        $pendingActivities = CrmActivity::forCompany($companyId)
            ->pending()
            ->with(['contact', 'opportunity'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->limit(10)
            ->get();

        $recentContacts = CrmContact::forCompany($companyId)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        $topOpps = CrmOpportunity::forCompany($companyId)
            ->active()
            ->with('contact')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        return view('crm.dashboard', array_merge($kpis, compact(
            'pendingActivities', 'recentContacts', 'topOpps'
        )));
    }
}
