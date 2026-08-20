<?php

/**
 * [Ventes §16] Worker de course « bons de préparation » — processus INDÉPENDANT.
 *
 * Chaque worker ouvre sa propre connexion PDO. C'est la seule façon de prouver
 * que les verrous sérialisent réellement : un test mono-processus rejouerait la
 * logique applicative en série et ne prouverait rien.
 *
 * Appel :
 *   php picking_race_worker.php <action> <userId> <startAt> <json-args>
 *
 * Actions :
 *   create    crée un bon        args {order_id, order_item_id, quantity, warehouse_id}
 *   allocate  alloue du stock    args {item_id, stock_lot_id, warehouse_id, quantity}
 *   validate  valide un bon      args {picking_id}
 *   cancel    annule un bon      args {picking_id, reason}
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = action réalisée, 2 = refus métier attendu, 3 = erreur inattendue.
 */

use App\Models\Order;
use App\Models\SalesPicking;
use App\Models\SalesPickingItem;
use App\Models\User;
use App\Services\Sales\SalesPickingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $action, $userId, $startAt, $rawArgs] = $argv;
$args = json_decode($rawArgs, true) ?: [];

// Départ synchronisé : les workers entrent en section critique ensemble.
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $user = User::findOrFail((int) $userId);
    Auth::login($user);
    app()->instance('current_company', $user->company);

    $service = app(SalesPickingService::class);

    switch ($action) {
        case 'create':
            $order = Order::findOrFail((int) $args['order_id']);
            $picking = $service->create($order, [
                ['order_item_id' => (int) $args['order_item_id'], 'quantity' => (float) $args['quantity']],
            ], ['warehouse_id' => (int) $args['warehouse_id']]);
            $payload = ['result' => 'created', 'picking_id' => $picking->id, 'number' => $picking->number];
            break;

        case 'allocate':
            $item = SalesPickingItem::findOrFail((int) $args['item_id']);
            $allocation = $service->allocate($item, [
                'stock_lot_id' => (int) $args['stock_lot_id'],
                'warehouse_id' => (int) $args['warehouse_id'],
                'quantity' => (float) $args['quantity'],
            ]);
            $payload = ['result' => 'allocated', 'allocation_id' => $allocation->id];
            break;

        case 'validate':
            $picking = SalesPicking::findOrFail((int) $args['picking_id']);
            $validated = $service->validate($picking);
            $payload = ['result' => 'validated', 'picking_id' => $validated->id, 'status' => $validated->status];
            break;

        case 'cancel':
            $picking = SalesPicking::findOrFail((int) $args['picking_id']);
            $cancelled = $service->cancel($picking, $args['reason'] ?? 'Annulation concurrente.');
            $payload = ['result' => 'cancelled', 'picking_id' => $cancelled->id, 'status' => $cancelled->status];
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
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'action' => $action,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
