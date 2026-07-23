<?php

namespace App\Services\Integrations;

use App\Services\Integrations\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Moov Money (Flooz) Burkina Faso — REST API
 *
 * Auth:  Static Bearer token (api_key or token field)
 * Docs:  https://developer.moov-africa.com
 *
 * Sandbox:    https://sandbox.moov-api.com/v1
 * Production: https://api.moov-africa.com/v1
 */
class MoovMoneyService extends BaseApiService implements PaymentGatewayInterface
{
    public const SANDBOX_BASE_URL    = 'https://sandbox.moov-api.com/v1';
    public const PRODUCTION_BASE_URL = 'https://api.moov-africa.com/v1';

    // ── Payment ───────────────────────────────────────────────────────────────

    /**
     * Initiate a Moov Money payment (USSD push).
     *
     * Required $params: phone (msisdn), amount (XOF), reference
     * Optional $params: description, order_id
     */
    public function initiatePayment(array $params): array
    {
        $bearerToken = $this->getBearerToken();
        if (! $bearerToken) {
            return ['success' => false, 'error' => "Token Moov Money non configuré (champ Token / API Key)."];
        }

        $payload = [
            'msisdn'      => $this->normalizePhone($params['phone'] ?? ''),
            'amount'      => (int) $params['amount'],
            'currency'    => 'XOF',
            'reference'   => $params['reference'] ?? uniqid('MOOV-'),
            'description' => $params['description'] ?? 'Paiement facture',
            'callback'    => route('integrations.webhooks.moov-money'),
        ];

        return $this->call('POST', '/payment/request', $payload, [
            'Authorization' => "Bearer {$bearerToken}",
        ], retry: false);
    }

    /**
     * Query transaction status.
     */
    public function checkStatus(string $externalReference): array
    {
        $bearerToken = $this->getBearerToken();
        if (! $bearerToken) {
            return ['success' => false, 'error' => 'Token indisponible.'];
        }

        return $this->call('GET', "/payment/{$externalReference}/status", [], [
            'Authorization' => "Bearer {$bearerToken}",
        ]);
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    public function processWebhook(array $payload, string $signature = ''): array
    {
        if (! $this->verifyWebhookSignature($payload, $signature)) {
            return [
                'success' => false,
                'skip'    => true,
                'error'   => "Signature HMAC invalide — webhook Moov rejeté.",
            ];
        }

        $rawStatus = strtoupper((string) ($payload['status'] ?? ''));
        $status    = match(true) {
            in_array($rawStatus, ['SUCCESSFUL', 'SUCCESS', '00']) => 'confirmed',
            in_array($rawStatus, ['FAILED', 'ERROR'])             => 'failed',
            in_array($rawStatus, ['CANCELLED'])                   => 'cancelled',
            default                                                => 'pending',
        };

        return [
            'success'            => true,
            'external_reference' => $payload['transactionId'] ?? $payload['reference'] ?? null,
            'amount'             => isset($payload['amount']) ? (int) $payload['amount'] : null,
            'phone'              => $payload['msisdn'] ?? null,
            'status'             => $status,
            'raw'                => $payload,
        ];
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $secret = $this->integration->webhook_secret;

        if (! $secret) {
            return $this->integration->isSandbox(); // Sandbox: accept; production: reject
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
        $token = $this->getBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Token Bearer non configuré.'];
        }

        // Simple authenticated request to a status endpoint
        $res = $this->call('GET', '/health', [], ['Authorization' => "Bearer {$token}"]);
        if ($res['success']) {
            return ['success' => true, 'message' => 'API Moov Money joignable.'];
        }
        // Some providers return 404 on /health but that still means the API is reachable
        if ($res['status'] !== null) {
            return ['success' => true, 'message' => "API joignable (HTTP {$res['status']})."];
        }
        return ['success' => false, 'message' => $res['error'] ?? 'Connexion impossible.'];
    }

    // ── Simulation ────────────────────────────────────────────────────────────

    public function simulate(array $params): array
    {
        if (! $this->integration->isSandbox()) {
            return ['success' => false, 'error' => 'Simulation uniquement disponible en mode sandbox.'];
        }

        $outcome = $params['outcome'] ?? 'success';

        return [
            'success'            => true,
            'simulated'          => true,
            'external_reference' => 'SIM-MOOV-' . strtoupper(uniqid()),
            'amount'             => (int) ($params['amount'] ?? 0),
            'phone'              => $params['phone'] ?? '',
            'status'             => match($outcome) {
                'success' => 'confirmed',
                'failure' => 'failed',
                default   => 'pending',
            },
            'raw' => [
                'status'        => strtoupper($outcome === 'success' ? 'SUCCESSFUL' : $outcome),
                'transactionId' => 'SIM-MOOV-' . uniqid(),
                'amount'        => $params['amount'] ?? 0,
                'msisdn'        => $params['phone'] ?? '',
            ],
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getBearerToken(): ?string
    {
        return $this->integration->token ?? $this->integration->api_key;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 8) return '+226' . $phone;
        if (strlen($phone) === 11 && str_starts_with($phone, '226')) return '+' . $phone;
        return $phone;
    }
}
