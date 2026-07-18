<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExternalPayment;
use App\Models\ApiIntegration;
use App\Models\ApiLog;
use App\Models\ExternalTransaction;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\OrangeMoneyService;
use App\Services\Integrations\MoovMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $integrations = ApiIntegration::withCount(['logs', 'externalTransactions'])
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $summary = [
            'total'      => $integrations->count(),
            'active'     => $integrations->where('is_active', true)->count(),
            'errors'     => $integrations->where('status', 'error')->count(),
            'logs_today' => ApiLog::whereDate('created_at', today())->count(),
            'tx_pending' => ExternalTransaction::where('status', 'pending')->count(),
        ];

        return view('integrations.index', compact('integrations', 'summary'));
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        $integrations = ApiIntegration::withCount(['logs', 'externalTransactions'])->get();

        // 7-day activity for sparkline — agrégé en 2 requêtes groupées (au lieu de 28)
        $since = now()->subDays(6)->startOfDay();

        $logsByDay = ApiLog::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('SUM(success = 1) as success')
            ->selectRaw('SUM(success = 0) as failed')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $txByDay = ExternalTransaction::where('created_at', '>=', $since)
            ->where('status', 'confirmed')
            ->selectRaw('DATE(created_at) as d')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $sevenDays = collect(range(6, 0))->map(function ($daysAgo) use ($logsByDay, $txByDay) {
            $day  = now()->subDays($daysAgo);
            $date = $day->format('Y-m-d');
            $l    = $logsByDay->get($date);
            return [
                'date'    => $date,
                'label'   => $day->format('d/m'),
                'calls'   => (int)   ($l->calls   ?? 0),
                'success' => (int)   ($l->success ?? 0),
                'failed'  => (int)   ($l->failed  ?? 0),
                'amount'  => (float) ($txByDay->get($date)->amount ?? 0),
            ];
        });

        $recentLogs = ApiLog::with('integration')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $recentTransactions = ExternalTransaction::with(['integration', 'invoice', 'client'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $stats = [
            'calls_today'      => ApiLog::whereDate('created_at', today())->count(),
            'calls_success'    => ApiLog::whereDate('created_at', today())->where('success', true)->count(),
            'calls_failed'     => ApiLog::whereDate('created_at', today())->where('success', false)->count(),
            'amount_confirmed' => (float) ExternalTransaction::where('status', 'confirmed')->whereDate('created_at', today())->sum('amount'),
            'amount_week'      => (float) ExternalTransaction::where('status', 'confirmed')->whereBetween('created_at', [now()->startOfWeek(), now()])->sum('amount'),
            'tx_today'         => ExternalTransaction::whereDate('created_at', today())->count(),
            'tx_pending'       => ExternalTransaction::where('status', 'pending')->count(),
            'tx_failed'        => ExternalTransaction::where('status', 'failed')->count(),
            'avg_latency_ms'   => (float) ApiLog::whereDate('created_at', today())->whereNotNull('duration_ms')->avg('duration_ms'),
        ];

        $alertIntegrations = ApiIntegration::inError()->get();

        return view('integrations.dashboard', compact(
            'integrations', 'recentLogs', 'recentTransactions',
            'stats', 'sevenDays', 'alertIntegrations'
        ));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('integrations.form', [
            'integration'    => new ApiIntegration(),
            'types'          => self::TYPES,
            'providers'      => self::PROVIDERS,
            'providerHints'  => self::PROVIDER_HINTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateIntegration($request);
        $data['slug'] = Str::slug($data['name'] . '-' . $data['provider'] . '-' . Str::random(4));

        if ($request->filled('extra_config_raw')) {
            $data['extra_config'] = $this->decodeExtraConfig($request);
        }

        $integration = ApiIntegration::create($data);
        IntegrationManager::forget($integration->type);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', "Intégration « {$integration->name} » créée avec succès.");
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(ApiIntegration $integration): View
    {
        $logs = ApiLog::where('api_integration_id', $integration->id)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'logs_page');

        $transactions = ExternalTransaction::where('api_integration_id', $integration->id)
            ->with(['invoice', 'client'])
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'tx_page');

        $stats = [
            'total_calls'    => ApiLog::where('api_integration_id', $integration->id)->count(),
            'success_calls'  => ApiLog::where('api_integration_id', $integration->id)->where('success', true)->count(),
            'failed_calls'   => ApiLog::where('api_integration_id', $integration->id)->where('success', false)->count(),
            'calls_today'    => ApiLog::where('api_integration_id', $integration->id)->whereDate('created_at', today())->count(),
            'total_amount'   => (float) ExternalTransaction::where('api_integration_id', $integration->id)->where('status', 'confirmed')->sum('amount'),
            'total_tx'       => ExternalTransaction::where('api_integration_id', $integration->id)->count(),
            'pending_tx'     => ExternalTransaction::where('api_integration_id', $integration->id)->where('status', 'pending')->count(),
            'avg_latency_ms' => (float) ApiLog::where('api_integration_id', $integration->id)->avg('duration_ms'),
        ];

        $successRate = $stats['total_calls'] > 0
            ? round($stats['success_calls'] / $stats['total_calls'] * 100, 1)
            : null;

        return view('integrations.show', compact('integration', 'logs', 'transactions', 'stats', 'successRate'));
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(ApiIntegration $integration): View
    {
        return view('integrations.form', [
            'integration'   => $integration,
            'types'         => self::TYPES,
            'providers'     => self::PROVIDERS,
            'providerHints' => self::PROVIDER_HINTS,
        ]);
    }

    public function update(Request $request, ApiIntegration $integration): RedirectResponse
    {
        $data = $this->validateIntegration($request, $integration);

        // Do NOT overwrite encrypted fields if empty string submitted
        foreach (['api_key', 'secret_key', 'client_id', 'client_secret', 'token', 'webhook_secret'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        if ($request->filled('extra_config_raw')) {
            $data['extra_config'] = $this->decodeExtraConfig($request);
        }

        $integration->update($data);
        IntegrationManager::forget($integration->type);

        // Invalidate token cache for payment providers
        $this->invalidateTokenCache($integration);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', "Intégration « {$integration->name} » mise à jour.");
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(ApiIntegration $integration): RedirectResponse
    {
        $name = $integration->name;
        $type = $integration->type;
        $integration->delete();
        IntegrationManager::forget($type);

        return redirect()
            ->route('integrations.index')
            ->with('success', "Intégration « {$name} » supprimée.");
    }

    // ── Toggle ────────────────────────────────────────────────────────────────

    public function toggle(ApiIntegration $integration): RedirectResponse
    {
        $integration->update(['is_active' => ! $integration->is_active]);
        IntegrationManager::forget($integration->type);

        $state = $integration->fresh()->is_active ? 'activée' : 'désactivée';
        return back()->with('success', "Intégration « {$integration->name} » {$state}.");
    }

    // ── Ping (AJAX) ───────────────────────────────────────────────────────────

    public function ping(ApiIntegration $integration): JsonResponse
    {
        $service = IntegrationManager::make($integration);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => "Aucun service disponible pour le fournisseur « {$integration->provider} ».",
            ]);
        }

        $result = $service->ping();

        // Update integration status
        if ($result['success']) {
            $integration->markOk();
        } else {
            $integration->markError($result['message'] ?? 'Ping failed');
        }

        return response()->json($result);
    }

    // ── Test (redirect) ───────────────────────────────────────────────────────

    public function test(ApiIntegration $integration): RedirectResponse
    {
        $service = IntegrationManager::make($integration);

        if (! $service) {
            return back()->with('error', "Aucun service disponible pour « {$integration->provider} ».");
        }

        $result = $service->ping();

        if ($result['success']) {
            $integration->markOk();
            return back()->with('success', "✅ Test réussi : " . ($result['message'] ?? 'Connexion OK'));
        }

        $integration->markError($result['message'] ?? 'Test failed');
        return back()->with('error', "❌ Test échoué : " . ($result['message'] ?? 'Erreur inconnue'));
    }

    // ── Simulate (sandbox only) ───────────────────────────────────────────────

    public function simulate(ApiIntegration $integration): View
    {
        abort_unless($integration->isSandbox(), 403, 'Simulation uniquement disponible en mode sandbox.');

        $recentTransactions = ExternalTransaction::where('api_integration_id', $integration->id)
            ->where('initiated_by', 'simulator')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('integrations.simulate', compact('integration', 'recentTransactions'));
    }

    public function simulateSend(Request $request, ApiIntegration $integration): RedirectResponse
    {
        abort_unless($integration->isSandbox(), 403, 'Simulation uniquement disponible en mode sandbox.');

        $validated = $request->validate([
            'amount'   => ['required', 'numeric', 'min:100', 'max:5000000'],
            'phone'    => ['required', 'string'],
            'order_id' => ['nullable', 'string', 'max:50'],
            'outcome'  => ['required', 'in:success,failure,pending'],
        ]);

        $service = IntegrationManager::make($integration);
        if (! $service || ! method_exists($service, 'simulate')) {
            return back()->with('error', "Ce fournisseur ne supporte pas la simulation.");
        }

        $result = $service->simulate($validated);

        if (! $result['success']) {
            return back()->with('error', $result['error'] ?? 'Simulation échouée.');
        }

        // Create simulated transaction
        $transaction = ExternalTransaction::create([
            'internal_reference' => ExternalTransaction::generateReference(),
            'external_reference' => $result['external_reference'],
            'api_integration_id' => $integration->id,
            'provider'           => $integration->provider,
            'type'               => 'payment',
            'amount'             => (float) $validated['amount'],
            'currency'           => 'XOF',
            'status'             => $result['status'],
            'phone_number'       => $result['phone'],
            'provider_data'      => $result['raw'],
            'direction'          => 'inbound',
            'initiated_by'       => 'simulator',
            'notes'              => 'Paiement simulé (sandbox)',
        ]);

        if ($result['status'] === 'confirmed') {
            ProcessExternalPayment::dispatch($transaction)->onQueue('payments');
        }

        return back()->with('success', sprintf(
            "✅ Simulation %s — Réf: %s — Statut: %s",
            strtoupper($validated['outcome']),
            $transaction->internal_reference,
            $result['status']
        ));
    }

    // ── Transactions listing ──────────────────────────────────────────────────

    public function transactions(Request $request): View
    {
        $query = ExternalTransaction::with(['integration', 'invoice', 'client'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->paginate(30)->withQueryString();

        $providers = ExternalTransaction::distinct('provider')
            ->pluck('provider')
            ->filter()
            ->sort()
            ->values();

        $totals = [
            'confirmed_sum' => ExternalTransaction::where('status', 'confirmed')->sum('amount'),
            'pending_count' => ExternalTransaction::where('status', 'pending')->count(),
            'failed_count'  => ExternalTransaction::where('status', 'failed')->count(),
        ];

        return view('integrations.transactions', compact('transactions', 'providers', 'totals'));
    }

    // ── Retry failed transaction ──────────────────────────────────────────────

    public function retryTransaction(ExternalTransaction $transaction): RedirectResponse
    {
        if (! $transaction->canRetry()) {
            return back()->with('error', "Cette transaction ne peut plus être relancée (max 5 tentatives atteint ou statut non-failed).");
        }

        ProcessExternalPayment::dispatch($transaction)->onQueue('payments');
        $transaction->increment('retry_count');
        $transaction->update(['last_retry_at' => now()]);

        return back()->with('success', "Transaction {$transaction->internal_reference} envoyée en file de retraitement.");
    }

    // ── Logs listing ─────────────────────────────────────────────────────────

    public function allLogs(Request $request): View
    {
        $query = ApiLog::with('integration')->orderByDesc('created_at');

        if ($request->filled('integration_id')) {
            $query->where('api_integration_id', $request->integration_id);
        }
        if ($request->filled('success')) {
            $query->where('success', $request->success === '1');
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs         = $query->paginate(50)->withQueryString();
        $integrations = ApiIntegration::orderBy('name')->get(['id', 'name', 'provider']);

        return view('integrations.logs', compact('logs', 'integrations'));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Décode la configuration avancée JSON. Un JSON invalide était auparavant
     * ignoré en silence : l'utilisateur croyait sa config enregistrée. On
     * renvoie désormais une erreur de validation explicite.
     */
    private function decodeExtraConfig(Request $request): array
    {
        $json = json_decode($request->extra_config_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($json)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'extra_config_raw' => 'Configuration avancée : JSON invalide (' . json_last_error_msg() . ') — corrigez la syntaxe ou videz le champ.',
            ]);
        }

        return $json;
    }

    private function validateIntegration(Request $request, ?ApiIntegration $integration = null): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'type'              => ['required', Rule::in(array_keys(self::TYPES))],
            'provider'          => ['required', Rule::in(array_keys(self::PROVIDERS))],
            'base_url'          => ['nullable', 'url', 'max:255'],
            'sandbox_base_url'  => ['nullable', 'url', 'max:255'],
            'timeout_seconds'   => ['nullable', 'integer', 'min:5', 'max:120'],
            'api_key'           => ['nullable', 'string', 'max:500'],
            'secret_key'        => ['nullable', 'string', 'max:500'],
            'client_id'         => ['nullable', 'string', 'max:500'],
            'client_secret'     => ['nullable', 'string', 'max:500'],
            'token'             => ['nullable', 'string', 'max:1000'],
            'webhook_secret'    => ['nullable', 'string', 'max:500'],
            'mode'              => ['required', 'in:sandbox,production'],
            'is_active'         => ['sometimes', 'boolean'],
            'notify_on_error'   => ['sometimes', 'boolean'],
            'extra_config_raw'  => ['nullable', 'string', function ($attr, $val, $fail) {
                if ($val && json_decode($val) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $fail("Le champ extra_config doit contenir du JSON valide.");
                }
            }],
        ]);
    }

    private function invalidateTokenCache(ApiIntegration $integration): void
    {
        $cacheKey = match($integration->provider) {
            'orange_money' => "om_token_{$integration->id}",
            default        => null,
        };
        if ($cacheKey) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public const TYPES = [
        'payment'   => 'Paiement mobile / Fintech',
        'sms'       => 'SMS Gateway',
        'email'     => 'Email SMTP / API',
        'bank'      => 'API Bancaire',
        'fiscal'    => 'API Fiscale',
        'ecommerce' => 'E-Commerce / Marketplace',
    ];

    public const PROVIDERS = [
        'orange_money' => 'Orange Money',
        'moov_money'   => 'Moov Money / Flooz',
        'nexah'        => 'Nexah SMS',
        'twilio'       => 'Twilio',
        'sms_generic'  => 'SMS API Générique',
        'smtp'         => 'SMTP Email',
        'mailgun'      => 'Mailgun',
        'sendgrid'     => 'SendGrid',
        'bank_generic' => 'Banque (API REST)',
        'fiscal_bf'    => 'Fiscalité Burkina Faso',
        'woocommerce'  => 'WooCommerce',
        'shopify'      => 'Shopify',
    ];

    public const PROVIDER_HINTS = [
        'orange_money' => [
            'fields'       => ['client_id' => 'Client ID Orange', 'client_secret' => 'Client Secret', 'api_key' => 'Merchant Key'],
            'sandbox_url'  => OrangeMoneyService::SANDBOX_BASE_URL,
            'prod_url'     => OrangeMoneyService::PRODUCTION_BASE_URL,
            'doc_url'      => 'https://developer.orange.com/apis/om-webpay-bf',
            'webhook_note' => 'Renseignez votre Notification URL dans le portail Orange : notif_url',
        ],
        'moov_money' => [
            'fields'       => ['token' => 'Bearer Token API', 'webhook_secret' => 'Webhook Secret'],
            'sandbox_url'  => MoovMoneyService::SANDBOX_BASE_URL,
            'prod_url'     => MoovMoneyService::PRODUCTION_BASE_URL,
            'doc_url'      => 'https://developer.moov-africa.com',
            'webhook_note' => 'Renseignez l\'URL de callback dans le portail Moov.',
        ],
        'nexah' => [
            'fields' => ['api_key' => 'API Key Nexah', 'extra_config' => '{"sender_id": "VOTRE_ID"}'],
        ],
        'twilio' => [
            'fields' => ['client_id' => 'Account SID', 'token' => 'Auth Token', 'extra_config' => '{"from_number": "+1XXXXXXXXXX"}'],
            'doc_url' => 'https://console.twilio.com',
        ],
    ];
}
