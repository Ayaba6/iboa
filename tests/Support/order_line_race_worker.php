<?php

/**
 * [BUG-A3-SALES-LINE-IMMUTABLE-012] Worker de course sur les lignes de commande.
 *
 * Processus INDÉPENDANT, connexion PDO propre : c'est la seule façon de prouver
 * que `lockForUpdate()` sérialise réellement deux modifications concurrentes.
 * Un test mono-processus ne prouverait que la logique applicative.
 *
 * Appel :
 *   php order_line_race_worker.php <orderId> <userId> <prix> <startAtMicrotime>
 *
 * Chaque worker relit les lignes de la commande, applique SON prix, et passe
 * par `OrderService::update()` — donc par le synchroniseur et son verrou.
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = modification appliquée, 2 = refus métier, 3 = erreur inattendue.
 */

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $orderId, $userId, $prix, $startAt] = $argv;

// Départ synchronisé : les deux workers entrent en section critique ensemble.
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $user = User::findOrFail((int) $userId);
    Auth::login($user);
    app()->instance('current_company', $user->company);

    $order = Order::findOrFail((int) $orderId);

    $lignes = $order->items->map(fn ($l) => [
        'id'               => $l->id,
        'product_id'       => $l->product_id,
        'description'      => $l->description,
        'quantity'         => (float) $l->quantity,
        'unit_price'       => (int) $prix,
        'discount_percent' => 0,
        'tax_rate_value'   => 0,
    ])->all();

    app(OrderService::class)->update($order, ['items' => $lignes]);

    fwrite(STDOUT, json_encode([
        'result' => 'updated',
        'order_id' => (int) $orderId,
        'prix' => (int) $prix,
    ], JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (Illuminate\Validation\ValidationException|RuntimeException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'blocked',
        'order_id' => (int) $orderId,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'order_id' => (int) $orderId,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
