<?php

declare(strict_types=1);

/**
 * Preuve multi-processus MySQL d?di?e au flux bobines.
 *
 * Sc?nario 1 : deux processus tentent de consommer la m?me bobine.
 * Sc?nario 2 : une consommation r?elle et un backflush concurrents se disputent
 * le m?me stock agr?g?. Dans les deux cas, aucun stock n?gatif ni double
 * consommation ne doit appara?tre.
 */

$dsn = 'mysql:host=127.0.0.1;port=3306';
$user = getenv('MYSQL_USER') ?: 'root';
$pass = getenv('MYSQL_PASSWORD') ?: '';
$db   = getenv('COIL_CONCURRENCY_DB') ?: 'iboa_coil_concur_test';

function connectWithRetry(string $dsn, string $user, string $pass, ?string $db = null): PDO
{
    $target = $db ? $dsn . ';dbname=' . $db : $dsn;
    $last = null;
    for ($i = 0; $i < 10; $i++) {
        try {
            return new PDO($target, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $e) {
            $last = $e;
            usleep(50000);
        }
    }

    throw $last ?? new RuntimeException('MySQL connection failed');
}

if (($argv[1] ?? null) === 'worker') {
    $mode = $argv[2] ?? 'consume';
    $want = (float) ($argv[3] ?? 0);
    $pdo = connectWithRetry($dsn, $user, $pass, $db);
    usleep(random_int(0, 40000));
    $pdo->beginTransaction();

    try {
        if ($mode === 'consume') {
            $coil = $pdo->query('SELECT remaining_weight FROM coils WHERE id = 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
            $remaining = (float) ($coil['remaining_weight'] ?? 0);
            if ($remaining < $want) {
                $pdo->commit();
                echo "REFUS\n";
                exit(0);
            }

            usleep(30000);
            $pdo->prepare('UPDATE coils SET remaining_weight = remaining_weight - ? WHERE id = 1')->execute([$want]);
            $stock = $pdo->query('SELECT quantity FROM product_stocks WHERE id = 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
            $qty = (float) ($stock['quantity'] ?? 0);
            if ($qty < $want) {
                $pdo->rollBack();
                echo "ROLLBACK\n";
                exit(0);
            }
            $pdo->prepare('UPDATE product_stocks SET quantity = quantity - ? WHERE id = 1')->execute([$want]);
            $pdo->prepare("INSERT INTO stock_movements(kind, quantity) VALUES ('consume', ?)")->execute([$want]);
        } elseif ($mode === 'backflush') {
            $stock = $pdo->query('SELECT quantity FROM product_stocks WHERE id = 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
            $qty = (float) ($stock['quantity'] ?? 0);
            if ($qty < $want) {
                $pdo->commit();
                echo "REFUS\n";
                exit(0);
            }

            usleep(30000);
            $pdo->prepare('UPDATE product_stocks SET quantity = quantity - ? WHERE id = 1')->execute([$want]);
            $pdo->prepare("INSERT INTO stock_movements(kind, quantity) VALUES ('backflush', ?)")->execute([$want]);
        } else {
            throw new RuntimeException('Mode inconnu: ' . $mode);
        }

        $pdo->commit();
        echo "OK\n";
        exit(0);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

$pdo = connectWithRetry($dsn, $user, $pass);
$pdo->exec("DROP DATABASE IF EXISTS {$db}");
$pdo->exec("CREATE DATABASE {$db}");
$pdo = connectWithRetry($dsn, $user, $pass, $db);
$pdo->exec('CREATE TABLE coils (id INT PRIMARY KEY, remaining_weight DECIMAL(18,4) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE product_stocks (id INT PRIMARY KEY, quantity DECIMAL(18,4) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE stock_movements (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20) NOT NULL, quantity DECIMAL(18,4) NOT NULL) ENGINE=InnoDB');

function resetScenario(PDO $pdo, float $coilQty, float $stockQty): void
{
    $pdo->exec('DELETE FROM stock_movements');
    $pdo->exec('DELETE FROM coils');
    $pdo->exec('DELETE FROM product_stocks');
    $pdo->prepare('INSERT INTO coils (id, remaining_weight) VALUES (1, ?)')->execute([$coilQty]);
    $pdo->prepare('INSERT INTO product_stocks (id, quantity) VALUES (1, ?)')->execute([$stockQty]);
}

function runWorkers(array $workers): array
{
    $php = PHP_BINARY;
    $script = __FILE__;
    $procs = [];

    foreach ($workers as $worker) {
        $parts = array_map('escapeshellarg', array_merge([$script, 'worker'], $worker));
        $cmd = escapeshellarg($php) . ' ' . implode(' ', $parts);
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptor, $pipes, __DIR__, $_ENV);
        if (! is_resource($proc)) {
            throw new RuntimeException('Impossible de lancer un worker');
        }
        $procs[] = [$proc, $pipes, $worker];
    }

    $results = [];
    foreach ($procs as [$proc, $pipes, $worker]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            throw new RuntimeException('Worker en ?chec: ' . implode(' ', $worker) . ' :: ' . trim($stderr ?: $stdout));
        }
        $results[] = [
            'worker' => $worker,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'exit_code' => $code,
        ];
    }

    return $results;
}

function assertInvariant(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

resetScenario($pdo, 10, 10);
$scenario1 = runWorkers([
    ['consume', '6'],
    ['consume', '6'],
]);
$coilAfter = (float) $pdo->query('SELECT remaining_weight FROM coils WHERE id = 1')->fetchColumn();
$stockAfter = (float) $pdo->query('SELECT quantity FROM product_stocks WHERE id = 1')->fetchColumn();
$movementCount = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
assertInvariant($coilAfter >= 0 && $stockAfter >= 0, 'Sc?nario 1 : stock n?gatif d?tect?');
assertInvariant($movementCount === 1, 'Sc?nario 1 : une seule consommation doit passer');

echo "Scenario 1 - same coil\n";
foreach ($scenario1 as $result) {
    echo '  ' . implode(' ', $result['worker']) . ' => ' . ($result['stdout'] ?: $result['stderr']) . "\n";
}
echo sprintf("  final coil=%.2f stock=%.2f movements=%d\n", $coilAfter, $stockAfter, $movementCount);

resetScenario($pdo, 10, 10);
$scenario2 = runWorkers([
    ['consume', '6'],
    ['backflush', '6'],
]);
$coilAfter2 = (float) $pdo->query('SELECT remaining_weight FROM coils WHERE id = 1')->fetchColumn();
$stockAfter2 = (float) $pdo->query('SELECT quantity FROM product_stocks WHERE id = 1')->fetchColumn();
$movementCount2 = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
assertInvariant($coilAfter2 >= 0 && $stockAfter2 >= 0, 'Sc?nario 2 : stock n?gatif d?tect?');
assertInvariant($movementCount2 === 1, 'Sc?nario 2 : un seul d?bit de stock doit passer');

echo "Scenario 2 - consume vs backflush\n";
foreach ($scenario2 as $result) {
    echo '  ' . implode(' ', $result['worker']) . ' => ' . ($result['stdout'] ?: $result['stderr']) . "\n";
}
echo sprintf("  final coil=%.2f stock=%.2f movements=%d\n", $coilAfter2, $stockAfter2, $movementCount2);

echo "OK - coil concurrency invariants hold\n";
