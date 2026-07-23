<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExternalPayment;
use App\Models\ApiIntegration;
use App\Models\ApiLog;
use App\Models\ExternalTransaction;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives webhook callbacks from external payment providers.
 *
 * Security:
 *  - Routes are excluded from CSRF (see bootstrap/app.php or App\Http\Middleware\VerifyCsrfToken).
 *  - Signature verification is mandatory in production mode.
 *  - Raw request body is used for signature (not re-encoded JSON).
 *  - Always returns HTTP 200 to prevent provider retries on non-payment errors.
 *  - Idempotent: duplicate webhooks are handled gracefully.
 *
 * Flow:
 *  1. Log raw inbound payload.
 *  2. Find active integration by provider.
 *  3. Verify HMAC signature.
 *  4. Normalize payload to internal status.
 *  5. Find or create ExternalTransaction.
 *  6. Dispatch ProcessExternalPayment job if status = confirmed.
 */
class WebhookController extends Controller
{
    public function orangeMoney(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'orange_money', 'X-Orange-Signature');
    }

    public function moovMoney(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'moov_money', 'X-Moov-Signature');
    }

    // ── Core handler ──────────────────────────────────────────────────────────

    private function handleWebhook(
        Request $request,
        string  $provider,
        string  $signatureHeader,
    ): JsonResponse {
        $rawBody   = $request->getContent();
        $payload   = json_decode($rawBody, true) ?? $request->all();
        $signature = $request->header($signatureHeader, '');
        $ip        = $request->ip();

        // ── 1. Find integration ───────────────────────────────────────────────
        $integration = ApiIntegration::where('provider', $provider)
            ->where('is_active', true)
            ->first();

        // ── 2. Log raw inbound (always, even if integration missing) ──────────
        ApiLog::create([
            'api_integration_id' => $integration?->id,
            'service'            => $provider,
            'endpoint'           => $request->path(),
            'method'             => 'WEBHOOK',
            'payload'            => $payload,
            'direction'          => 'inbound',
            'success'            => true, // 'success' means received, not processed
            'ip_address'         => $ip,
            'created_at'         => now(),
        ]);

        // ── 3. Guard: integration not found or inactive ───────────────────────
        if (! $integration) {
            Log::warning("[Webhook/{$provider}] No active integration found.", ['ip' => $ip]);
            return response()->json(['status' => 'accepted'], 200);
            // 200 to prevent provider from retrying (we don't need these events)
        }

        // ── 4. Build service and verify signature ─────────────────────────────
        $service = IntegrationManager::make($integration);
        if (! $service) {
            Log::error("[Webhook/{$provider}] No service class for this provider.");
            return response()->json(['status' => 'ok'], 200);
        }

        // Pass both raw body and parsed payload for signature verification
        $result = $service->processWebhook($payload, $signature);

        if (! $result['success']) {
            if ($result['skip'] ?? false) {
                Log::warning("[Webhook/{$provider}] Rejected: " . ($result['error'] ?? ''), [
                    'ip'        => $ip,
                    'signature' => substr($signature, 0, 20) . '...',
                ]);
                // Still 200 — the provider should NOT retry on signature failures
                return response()->json(['status' => 'rejected', 'reason' => 'signature'], 200);
            }
            return response()->json(['status' => 'ok'], 200);
        }

        // ── 5. Find or create ExternalTransaction ─────────────────────────────
        $externalRef = $result['external_reference'] ?? null;
        $internalRef = $payload['reference'] ?? $payload['order_id'] ?? null;

        // [SEC-PHASE2] Recherche STRICTE : l'ancien where('external_reference',
        // $externalRef) avec une référence nulle devenait whereNull et pouvait
        // rattacher le webhook à une transaction étrangère.
        $transaction = null;
        if ($externalRef !== null && $externalRef !== '') {
            $transaction = ExternalTransaction::where('external_reference', $externalRef)
                ->where('provider', $provider)->first();
        }
        if (! $transaction && $internalRef) {
            $transaction = ExternalTransaction::where('internal_reference', $internalRef)
                ->where('provider', $provider)->first();
        }

        // [SEC-PHASE2] Même référence, CONTENU différent : un webhook dont le
        // montant diverge de la transaction connue est rejeté et journalisé —
        // le HMAC prouve l'origine, pas la légitimité métier.
        if ($transaction && $result['amount'] !== null
            && (int) $transaction->amount !== 0
            && (int) $result['amount'] !== (int) $transaction->amount) {
            Log::channel('security')->warning('webhook.montant_divergent', [
                'provider'     => $provider,
                'external_ref' => $externalRef,
                'attendu'      => (int) $transaction->amount,
                'recu'         => (int) $result['amount'],
                'ip'           => $ip,
            ]);

            return response()->json(['status' => 'rejected', 'reason' => 'amount_mismatch'], 200);
        }

        // [SEC-PHASE2] Mémoriser l'état AVANT traitement : le dispatch doit avoir
        // lieu quand la confirmation est NOUVELLE — y compris à la création
        // directe en 'confirmed' (l'ancien test « status !== confirmed » après
        // création ne déclenchait JAMAIS le job sur une première livraison SUCCESS).
        $wasConfirmed = $transaction?->status === 'confirmed';

        if (! $transaction) {
            // Try to find linked invoice
            $invoiceRef = $internalRef;
            $invoice    = $invoiceRef
                ? \App\Models\Invoice::where('number', $invoiceRef)->first()
                : null;

            try {
                $transaction = $this->createTransaction($integration, $provider, $result, $externalRef, $invoice);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // [SEC-PHASE2] Deux webhooks simultanés : la contrainte unique
                // (provider, external_reference) fait perdre la course au second —
                // on recharge la transaction créée par le premier.
                $transaction = ExternalTransaction::where('external_reference', $externalRef)
                    ->where('provider', $provider)->firstOrFail();
            }
        } else {
            // Update existing — but never downgrade status (confirmed stays confirmed)
            if ($transaction->status !== 'confirmed') {
                $transaction->update([
                    'status'        => $result['status'],
                    'provider_data' => array_merge($transaction->provider_data ?? [], $result['raw']),
                ]);
            }
        }

        // ── 6. Dispatch job if NEWLY confirmed ────────────────────────────────
        if ($result['status'] === 'confirmed' && ! $wasConfirmed) {
            ProcessExternalPayment::dispatch($transaction->fresh())
                ->onQueue('payments');

            Log::info("[Webhook/{$provider}] ✅ Payment confirmed, job dispatched", [
                'internal_ref' => $transaction->internal_reference,
                'external_ref' => $externalRef,
                'amount'       => $result['amount'],
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function createTransaction(
        ApiIntegration $integration,
        string $provider,
        array $result,
        ?string $externalRef,
        ?\App\Models\Invoice $invoice,
    ): ExternalTransaction {
        return ExternalTransaction::create([
            'internal_reference' => ExternalTransaction::generateReference(),
            'external_reference' => $externalRef,
            'api_integration_id' => $integration->id,
            'provider'           => $provider,
            'type'               => 'payment',
            'amount'             => (float) ($result['amount'] ?? 0),
            'currency'           => 'XOF',
            'status'             => $result['status'],
            'phone_number'       => $result['phone'] ?? null,
            'provider_data'      => $result['raw'],
            'invoice_id'         => $invoice?->id,
            'client_id'          => $invoice?->client_id,
            'direction'          => 'inbound',
            'initiated_by'       => 'webhook',
        ]);
    }
}
