<?php

namespace App\Services\Integrations;

use App\Models\ApiIntegration;
use App\Services\Integrations\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Orange Money Burkina Faso — REST API v2
 *
 * Auth:  OAuth2 client_credentials → Bearer token (cached 55 min)
 * Docs:  https://developer.orange.com/apis/om-webpay-bf
 *
 * Sandbox:    https://api.orange.com/oauth/v3   (token)
 *             https://api.orange.com/orange-money-webpay/bf/v1  (webpayment)
 * Production: same base URL, different merchant credentials
 *
 * Fixed bugs from v1:
 *  - Currency was 'OUV' → corrected to 'XOF'
 *  - Access token was never cached → OAuth2 call on EVERY payment initiation
 *  - Signature verification accepted all requests when no secret (security hole in production)
 */
class OrangeMoneyService extends BaseApiService implements PaymentGatewayInterface
{
    // Sandbox defaults (pre-filled when user creates sandbox integration)
    public const SANDBOX_TOKEN_URL   = 'https://api.orange.com/oauth/v3/token';
    public const SANDBOX_BASE_URL    = 'https://api.orange.com/orange-money-webpay/dev/v1';
    public const PRODUCTION_BASE_URL = 'https://api.orange.com/orange-money-webpay/bf/v1';

    private string $tokenCacheKey;

    public function __construct(ApiIntegration $integration)
    {
        parent::__construct($integration);
        $this->tokenCacheKey = "om_token_{$integration->id}";
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    /**
     * Get Bearer token with 55-minute cache (tokens expire after 60 min).
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember($this->tokenCacheKey, now()->addMinutes(55), function () {
            $clientId     = $this->integration->client_id ?? '';
            $clientSecret = $this->integration->client_secret ?? '';

            if (! $clientId || ! $clientSecret) {
                return null;
            }

            // Token endpoint is at a fixed URL, not relative to base_url
            $tokenUrl = $this->integration->extra_config['token_url']
                ?? self::SANDBOX_TOKEN_URL;

            return $this->fetchOAuthToken(
                $tokenUrl,
                $clientId,
                $clientSecret,
            );
        });
    }

    /** Invalidate cached token (call on credential update). */
    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    // ── Payment ───────────────────────────────────────────────────────────────

    /**
     * Initiate web payment (Orange Money Burkina Faso).
     *
     * Required $params: amount (int XOF), order_id (string), reference (string)
     * Optional $params: phone (msisdn), return_url, cancel_url, description
     */
    public function initiatePayment(array $params): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [
                'success' => false,
                'error'   => "Impossible d'obtenir le token Orange Money. Vérifiez client_id / client_secret.",
            ];
        }

        $merchantKey = $this->integration->api_key ?? '';
        if (! $merchantKey) {
            return ['success' => false, 'error' => "merchant_key (API Key) non configuré."];
        }

        $payload = [
            'merchant_key' => $merchantKey,
            'currency'     => 'XOF',                        // ← CORRIGÉ (était 'OUV')
            'order_id'     => $params['order_id'] ?? $params['reference'] ?? uniqid('OM-'),
            'amount'       => (int) $params['amount'],
            'return_url'   => $params['return_url'] ?? url('/'),
            'cancel_url'   => $params['cancel_url'] ?? url('/'),
            'notif_url'    => route('integrations.webhooks.orange-money'),
            'lang'         => 'fr',
            'reference'    => $params['reference'] ?? '',
        ];

        if (!empty($params['phone'])) {
            $payload['msisdn'] = $this->normalizePhone($params['phone']);
        }
        if (!empty($params['description'])) {
            $payload['description'] = $params['description'];
        }

        return $this->call('POST', '/webpayment', $payload, [
            'Authorization' => "Bearer {$token}",
        ], retry: false); // Not safe to retry payment initiation
    }

    /**
     * Query transaction status.
     */
    public function checkStatus(string $externalReference): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Token indisponible.'];
        }

        return $this->call('GET', "/webpayment/{$externalReference}", [], [
            'Authorization' => "Bearer {$token}",
        ]);
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    /**
     * Process incoming Orange Money webhook (HMAC-SHA256 verified).
     */
    public function processWebhook(array $payload, string $signature = ''): array
    {
        if (! $this->verifyWebhookSignature($payload, $signature)) {
            return [
                'success' => false,
                'skip'    => true,
                'error'   => "Signature HMAC invalide — webhook rejeté.",
            ];
        }

        $rawStatus = strtoupper((string) ($payload['status'] ?? ''));
        $status    = match(true) {
            in_array($rawStatus, ['SUCCESS', '00'])         => 'confirmed',
            in_array($rawStatus, ['FAILED', 'CANCELLED'])   => 'failed',
            default                                          => 'pending',
        };

        return [
            'success'            => true,
            'external_reference' => $payload['txnid']  ?? $payload['order_id'] ?? null,
            'amount'             => isset($payload['amount']) ? (int) $payload['amount'] : null,
            'phone'              => $payload['msisdn']  ?? $payload['phone'] ?? null,
            'status'             => $status,
            'raw'                => $payload,
        ];
    }

    /**
     * Verify HMAC-SHA256 signature.
     * In production without a webhook_secret configured → REJECT (security-first).
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $secret = $this->integration->webhook_secret;

        // No secret configured
        if (! $secret) {
            // In sandbox: accept (useful for testing)
            if ($this->integration->isSandbox()) return true;
            // In production: REJECT — webhook_secret MUST be configured
            return false;
        }

        if (! $signature) return false;

        $computed = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret);
        // [SEC-PHASE2] ltrim strippe une LISTE de caractères, pas un préfixe :
        // une signature commençant par s/h/a/2/5/6/= était tronquée → faux rejets.
        return hash_equals($computed, preg_replace('/^sha256=/', '', $signature));
    }

    // ── Ping ─────────────────────────────────────────────────────────────────

    public function ping(): array
    {
        $token = $this->getAccessToken();
        if ($token) {
            return ['success' => true, 'message' => 'Token OAuth2 obtenu — API joignable.'];
        }
        return ['success' => false, 'message' => 'Impossible d\'obtenir un token OAuth2.'];
    }

    // ── Sandbox simulation ────────────────────────────────────────────────────

    /**
     * Simulate a payment result without calling the real API.
     * Only available in sandbox mode.
     */
    public function simulate(array $params): array
    {
        if (! $this->integration->isSandbox()) {
            return ['success' => false, 'error' => 'Simulation uniquement disponible en mode sandbox.'];
        }

        $outcome = $params['outcome'] ?? 'success'; // success|failure|pending

        return [
            'success'            => true,
            'simulated'          => true,
            'external_reference' => 'SIM-' . strtoupper(uniqid()),
            'amount'             => (int) ($params['amount'] ?? 0),
            'phone'              => $params['phone'] ?? '',
            'status'             => match($outcome) {
                'success' => 'confirmed',
                'failure' => 'failed',
                default   => 'pending',
            },
            'raw' => [
                'status'   => strtoupper($outcome),
                'txnid'    => 'SIM-' . uniqid(),
                'amount'   => $params['amount'] ?? 0,
                'msisdn'   => $params['phone'] ?? '',
                'order_id' => $params['order_id'] ?? 'SIM',
            ],
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function normalizePhone(string $phone): string
    {
        // Ensure Burkina Faso format: +226XXXXXXXX or 226XXXXXXXX
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 8) {
            return '+226' . $phone;
        }
        if (strlen($phone) === 11 && str_starts_with($phone, '226')) {
            return '+' . $phone;
        }
        return $phone;
    }
}
