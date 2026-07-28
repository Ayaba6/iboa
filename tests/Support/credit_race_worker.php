<?php

/**
 * [Ventes §3] Worker de course crédit — processus INDÉPENDANT.
 *
 * Chaque worker ouvre sa propre connexion PDO : c'est la seule façon de prouver
 * que le verrou client sérialise réellement des transactions concurrentes. Un
 * test mono-processus ne prouverait que la logique applicative.
 *
 * Appel :
 *   php credit_race_worker.php <action> <id> <userId> <startAtMicrotime>
 *
 * Actions :
 *   submit_order    soumet la commande <id> (passe par le contrôle de crédit)
 *   issue_invoice   émet la facture <id> (n'est PAS un point de contrôle crédit)
 *   confirm_deposit confirme l'acompte <id> (réduit l'encours)
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = action réalisée, 2 = refus métier attendu, 3 = erreur inattendue.
 */

use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $action, $id, $userId, $startAt] = $argv;
$id = (int) $id;

// Départ synchronisé : les deux workers entrent en section critique ensemble.
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $user = User::findOrFail((int) $userId);
    Auth::login($user);
    app()->instance('current_company', $user->company);

    switch ($action) {
        case 'submit_order':
            $order = Order::findOrFail($id);
            app(CommercialWorkflowService::class)->submit($order);
            $payload = ['result' => 'submitted', 'order_id' => $id];
            break;

        case 'issue_invoice':
            // Émission d'une facture : écrit sur le même client, en concurrence
            // avec le contrôle de crédit. Volontairement SANS verrou client —
            // on mesure le comportement réel, on ne le maquille pas.
            DB::transaction(function () use ($id) {
                $invoice = Invoice::lockForUpdate()->findOrFail($id);
                $invoice->forceFill([
                    'status' => 'emise',
                    'remaining_amount' => $invoice->total_ttc,
                ])->save();
            });
            $payload = ['result' => 'invoiced', 'invoice_id' => $id];
            break;

        case 'confirm_deposit':
            DB::transaction(function () use ($id) {
                $payment = ClientPayment::lockForUpdate()->findOrFail($id);
                $payment->forceFill([
                    'status' => 'confirme',
                    'unallocated_amount' => (int) $payment->amount - (int) $payment->allocated_amount,
                ])->save();
            });
            $payload = ['result' => 'deposit_confirmed', 'payment_id' => $id];
            break;

        default:
            throw new InvalidArgumentException("Action inconnue : {$action}");
    }

    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (RuntimeException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'blocked',
        'action' => $action,
        'id' => $id,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'action' => $action,
        'id' => $id,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
