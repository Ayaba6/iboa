<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// [Sync ERP] Administration des synchronisations inter-modules.
class SyncLogController extends Controller
{
    public function __construct(private SyncOrchestrator $orchestrator)
    {
        $this->middleware('can:settings.manage');
    }

    public function index(Request $request): View
    {
        $logs = SyncLog::with('creator:id,name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->module, fn ($q, $m) => $q->where(fn ($qq) => $qq->where('source_module', $m)->orWhere('target_module', $m)))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qq) => $qq
                ->where('event_name', 'like', "%{$s}%")
                ->orWhere('action', 'like', "%{$s}%")
                ->orWhere('message', 'like', "%{$s}%")))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $stats = SyncLog::selectRaw('status, COUNT(*) as nb')->groupBy('status')->pluck('nb', 'status');
        $modules = SyncLog::select('source_module')->distinct()->pluck('source_module')
            ->merge(SyncLog::select('target_module')->distinct()->pluck('target_module'))
            ->unique()->sort()->values();

        return view('admin.sync-logs.index', compact('logs', 'stats', 'modules'));
    }

    public function retry(SyncLog $syncLog): RedirectResponse
    {
        if (!$syncLog->isRetryable()) {
            return back()->with('error', 'Cette synchronisation ne peut pas être relancée.');
        }

        $log = $this->orchestrator->retry($syncLog);

        return back()->with(
            $log->status === SyncLog::STATUS_SUCCESS ? 'success' : 'error',
            $log->status === SyncLog::STATUS_SUCCESS
                ? "Synchronisation #{$log->id} relancée avec succès."
                : "Relance #{$log->id} échouée : {$log->message}"
        );
    }
}
