<?php

declare(strict_types=1);

/**
 * Real multi-process MySQL proof for coil-related stock invariants.
 *
 * Covered scenarios:
 *  1. two production orders consuming the same coil at the same time;
 *  2. concurrent real consumption and backflush;
 *  3. double backflush;
 *  4. cancellation attempt while a consumption is still uncommitted;
 *  5. concurrent consumption and transfer;
 *  6. consumption requests above the remaining quantity;
 *  7. deliberate deadlock with opposite lock order;
 *  8. controlled retry after deadlock;
 *  9. duplicate movement prevention with a shared dedupe key.
 */

const BARRIER_TIMEOUT_MS = 10_000;
const WORKER_TIMEOUT_MS = 20_000;
const POLL_US = 10_000;

$dsnBase = 'mysql:host=127.0.0.1;port=3306';
$user = getenv('MYSQL_USER') ?: 'root';
$pass = getenv('MYSQL_PASSWORD') ?: '';
$db = getenv('COIL_CONCURRENCY_DB') ?: 'iboa_coil_concur_test';
$reportPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'coil-concurrency-report.json';

if (($argv[1] ?? null) === 'worker') {
    workerMain($dsnBase, $user, $pass, $db, (string) ($argv[2] ?? ''));
}

$admin = connectWithRetry($dsnBase, $user, $pass);
$report = [];

try {
    setupDatabase($admin, $dsnBase, $user, $pass, $db);
    $pdo = connectWithRetry($dsnBase, $user, $pass, $db);
    configureConnection($pdo);

    $report[] = runScenarioSameCoil($pdo);
    $report[] = runScenarioConsumeVsBackflush($pdo);
    $report[] = runScenarioDoubleBackflush($pdo);
    $report[] = runScenarioCancelDuringConsume($pdo);
    $report[] = runScenarioConsumeVsTransfer($pdo);
    $report[] = runScenarioOverConsume($pdo);
    $report[] = runScenarioDeliberateDeadlock($pdo);
    $report[] = runScenarioControlledRetry($pdo);
    $report[] = runScenarioNoDuplicateMovement($pdo);

    foreach ($report as $scenario) {
        renderScenario($scenario);
    }

    $summary = [
        'generated_at' => gmdate('c'),
        'database' => $db,
        'scenarios' => $report,
    ];
    if (is_dir(dirname($reportPath))) {
        file_put_contents($reportPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo 'Report: ' . $reportPath . PHP_EOL;
    }

    echo 'OK - 9/9 real multi-process scenarios validated' . PHP_EOL;
} finally {
    if ((getenv('COIL_CONCURRENCY_KEEP_DB') ?: '0') !== '1') {
        dropDatabase($admin, $db);
    }
}

function workerMain(string $dsnBase, string $user, string $pass, string $db, string $payload): never
{
    try {
        if ($payload === '') {
            throw new InvalidArgumentException('Missing worker payload');
        }

        $config = json_decode(base64_decode($payload, true) ?: '', true, 512, JSON_THROW_ON_ERROR);
        $data = connectWithRetry($dsnBase, $user, $pass, $db);
        $sync = connectWithRetry($dsnBase, $user, $pass, $db);
        configureConnection($data);
        configureConnection($sync);

        $result = executeWorker($data, $sync, $config);
        echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    } catch (Throwable $e) {
        $fallback = [
            'worker' => $config['worker'] ?? 'unknown',
            'mode' => $config['mode'] ?? 'unknown',
            'outcome' => 'ERROR',
            'attempts' => $config['attempts'] ?? 0,
            'message' => $e->getMessage(),
        ];
        echo json_encode($fallback, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(1);
    }
}

function connectWithRetry(string $dsn, string $user, string $pass, ?string $db = null): PDO
{
    $target = $db ? $dsn . ';dbname=' . $db : $dsn;
    $last = null;

    for ($i = 0; $i < 20; $i++) {
        try {
            return new PDO($target, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $last = $e;
            usleep(50_000);
        }
    }

    throw $last ?? new RuntimeException('MySQL connection failed');
}

function configureConnection(PDO $pdo): void
{
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 2');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
}

function setupDatabase(PDO $admin, string $dsnBase, string $user, string $pass, string $db): void
{
    dropDatabase($admin, $db);
    $admin->exec("CREATE DATABASE `{$db}`");

    $pdo = connectWithRetry($dsnBase, $user, $pass, $db);
    configureConnection($pdo);
    $pdo->exec('CREATE TABLE coils (id INT PRIMARY KEY, remaining_weight DECIMAL(18,4) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE product_stocks (id INT PRIMARY KEY, quantity DECIMAL(18,4) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(40) NOT NULL,
        quantity DECIMAL(18,4) NOT NULL,
        dedupe_key VARCHAR(120) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_stock_movements_dedupe_key (dedupe_key)
    ) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE barrier_waits (
        scenario VARCHAR(80) NOT NULL,
        phase VARCHAR(80) NOT NULL,
        worker VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (scenario, phase, worker)
    ) ENGINE=InnoDB');
}

function dropDatabase(PDO $admin, string $db): void
{
    $admin->exec("DROP DATABASE IF EXISTS `{$db}`");
}

function executeWorker(PDO $data, PDO $sync, array $config): array
{
    $attempt = 0;
    $retryMax = (int) ($config['retry_max'] ?? 0);

    while (true) {
        $attempt++;

        try {
            $result = match ($config['mode']) {
                'debit' => runDebitWorker($data, $sync, $config),
                'cancel' => runCancelWorker($data, $sync, $config),
                default => throw new InvalidArgumentException('Unknown worker mode: ' . $config['mode']),
            };

            $result['attempts'] = $attempt;
            return $result;
        } catch (PDOException $e) {
            if (! isDeadlock($e)) {
                throw $e;
            }

            if ($data->inTransaction()) {
                $data->rollBack();
            }

            if ($attempt > $retryMax) {
                return [
                    'worker' => $config['worker'],
                    'mode' => $config['mode'],
                    'outcome' => 'DEADLOCK',
                    'attempts' => $attempt,
                    'message' => $e->getMessage(),
                ];
            }

            usleep(50_000);
        }
    }
}

function runDebitWorker(PDO $data, PDO $sync, array $config): array
{
    $scenario = $config['scenario'];
    $worker = $config['worker'];
    $participants = (int) ($config['participants'] ?? 1);
    $quantity = (float) ($config['quantity'] ?? 0);
    $locks = $config['locks'] ?? [];
    $checks = $config['checks'] ?? [];
    $effects = $config['effects'] ?? [];
    $kind = (string) ($config['kind'] ?? 'movement');
    $dedupeKey = $config['dedupe_key'] ?? null;

    awaitBarrier($sync, $scenario, 'start', $worker, $participants, BARRIER_TIMEOUT_MS);

    if (isset($config['wait_phase'])) {
        waitForPhaseCount(
            $sync,
            $scenario,
            (string) $config['wait_phase'],
            (int) ($config['wait_phase_count'] ?? 1),
            BARRIER_TIMEOUT_MS
        );
    }

    $data->beginTransaction();

    try {
        $values = [];
        foreach ($locks as $index => $resource) {
            $values[$resource] = lockResource($data, $resource);

            if ($index === 0 && ! empty($config['after_first_lock_barrier'])) {
                awaitBarrier($sync, $scenario, 'after-first-lock', $worker, $participants, BARRIER_TIMEOUT_MS);
            }
        }

        if ($dedupeKey !== null && movementExists($data, (string) $dedupeKey)) {
            $data->commit();
            return [
                'worker' => $worker,
                'mode' => $config['mode'],
                'outcome' => 'DUPLICATE',
                'kind' => $kind,
            ];
        }

        foreach ($checks as $resource) {
            if (($values[$resource] ?? 0.0) < $quantity) {
                $data->commit();
                return [
                    'worker' => $worker,
                    'mode' => $config['mode'],
                    'outcome' => 'REFUS',
                    'kind' => $kind,
                ];
            }
        }

        foreach ($effects as $resource => $sign) {
            applyEffect($data, $resource, $quantity * (float) $sign);
        }

        insertMovement($data, $kind, $quantity, $dedupeKey);

        if (isset($config['signal_phase_after_insert'])) {
            signalPhase($sync, $scenario, (string) $config['signal_phase_after_insert'], $worker);
        }

        if (! empty($config['hold_before_commit_us'])) {
            usleep((int) $config['hold_before_commit_us']);
        }

        $data->commit();

        return [
            'worker' => $worker,
            'mode' => $config['mode'],
            'outcome' => 'OK',
            'kind' => $kind,
        ];
    } catch (Throwable $e) {
        if ($data->inTransaction()) {
            $data->rollBack();
        }

        throw $e;
    }
}

function runCancelWorker(PDO $data, PDO $sync, array $config): array
{
    $scenario = $config['scenario'];
    $worker = $config['worker'];
    $participants = (int) ($config['participants'] ?? 1);
    $targetKey = (string) ($config['target_dedupe_key'] ?? '');

    awaitBarrier($sync, $scenario, 'start', $worker, $participants, BARRIER_TIMEOUT_MS);

    if (isset($config['wait_phase'])) {
        waitForPhaseCount(
            $sync,
            $scenario,
            (string) $config['wait_phase'],
            (int) ($config['wait_phase_count'] ?? 1),
            BARRIER_TIMEOUT_MS
        );
    }

    $data->beginTransaction();

    try {
        $stmt = $data->prepare('SELECT id, kind, quantity FROM stock_movements WHERE dedupe_key = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$targetKey]);
        $movement = $stmt->fetch();

        if (! $movement) {
            $data->commit();
            return [
                'worker' => $worker,
                'mode' => $config['mode'],
                'outcome' => 'NOT_FOUND_OR_PENDING',
                'kind' => 'cancel',
            ];
        }

        $quantity = (float) $movement['quantity'];
        $kind = (string) $movement['kind'];

        if ($kind === 'consume' || $kind === 'deadlock-a' || $kind === 'deadlock-b' || $kind === 'retry-a' || $kind === 'retry-b' || $kind === 'consume-dedupe') {
            lockResource($data, 'coil');
            lockResource($data, 'stock');
            applyEffect($data, 'coil', $quantity);
            applyEffect($data, 'stock', $quantity);
        } else {
            lockResource($data, 'stock');
            applyEffect($data, 'stock', $quantity);
        }

        insertMovement($data, 'cancel-' . $kind, $quantity, 'cancel:' . $targetKey);
        $data->commit();

        return [
            'worker' => $worker,
            'mode' => $config['mode'],
            'outcome' => 'CANCELED',
            'kind' => 'cancel-' . $kind,
        ];
    } catch (Throwable $e) {
        if ($data->inTransaction()) {
            $data->rollBack();
        }

        throw $e;
    }
}

function lockResource(PDO $pdo, string $resource): float
{
    return match ($resource) {
        'coil' => (float) $pdo->query('SELECT remaining_weight FROM coils WHERE id = 1 FOR UPDATE')->fetchColumn(),
        'stock' => (float) $pdo->query('SELECT quantity FROM product_stocks WHERE id = 1 FOR UPDATE')->fetchColumn(),
        default => throw new InvalidArgumentException('Unknown resource: ' . $resource),
    };
}

function applyEffect(PDO $pdo, string $resource, float $delta): void
{
    $sql = match ($resource) {
        'coil' => 'UPDATE coils SET remaining_weight = remaining_weight + ? WHERE id = 1',
        'stock' => 'UPDATE product_stocks SET quantity = quantity + ? WHERE id = 1',
        default => throw new InvalidArgumentException('Unknown resource: ' . $resource),
    };

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$delta]);
}

function insertMovement(PDO $pdo, string $kind, float $quantity, ?string $dedupeKey): void
{
    $stmt = $pdo->prepare('INSERT INTO stock_movements (kind, quantity, dedupe_key) VALUES (?, ?, ?)');
    $stmt->execute([$kind, $quantity, $dedupeKey]);
}

function movementExists(PDO $pdo, string $dedupeKey): bool
{
    $stmt = $pdo->prepare('SELECT id FROM stock_movements WHERE dedupe_key = ? LIMIT 1');
    $stmt->execute([$dedupeKey]);
    return (bool) $stmt->fetchColumn();
}

function signalPhase(PDO $sync, string $scenario, string $phase, string $worker): void
{
    $stmt = $sync->prepare('INSERT INTO barrier_waits (scenario, phase, worker) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP');
    $stmt->execute([$scenario, $phase, $worker]);
}

function awaitBarrier(PDO $sync, string $scenario, string $phase, string $worker, int $expected, int $timeoutMs): void
{
    signalPhase($sync, $scenario, $phase, $worker);
    waitForPhaseCount($sync, $scenario, $phase, $expected, $timeoutMs);
}

function waitForPhaseCount(PDO $sync, string $scenario, string $phase, int $expected, int $timeoutMs): void
{
    $stmt = $sync->prepare('SELECT COUNT(*) FROM barrier_waits WHERE scenario = ? AND phase = ?');
    $deadline = microtime(true) + ($timeoutMs / 1000);

    while (microtime(true) < $deadline) {
        $stmt->execute([$scenario, $phase]);
        if ((int) $stmt->fetchColumn() >= $expected) {
            return;
        }

        usleep(POLL_US);
    }

    throw new RuntimeException("Barrier timeout for {$scenario}/{$phase}");
}

function runWorkers(array $workers): array
{
    $php = PHP_BINARY;
    $script = __FILE__;
    $procs = [];

    foreach ($workers as $worker) {
        $payload = base64_encode(json_encode($worker, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' worker ' . escapeshellarg($payload);
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptor, $pipes, __DIR__, null);
        if (! is_resource($proc)) {
            throw new RuntimeException('Unable to start worker process');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $procs[] = [
            'proc' => $proc,
            'pipes' => $pipes,
            'worker' => $worker,
            'stdout' => '',
            'stderr' => '',
            'done' => false,
        ];
    }

    $deadline = microtime(true) + (WORKER_TIMEOUT_MS / 1000);

    while (true) {
        $allDone = true;

        foreach ($procs as $index => $entry) {
            if ($entry['done']) {
                continue;
            }

            $entry['stdout'] .= stream_get_contents($entry['pipes'][1]);
            $entry['stderr'] .= stream_get_contents($entry['pipes'][2]);
            $status = proc_get_status($entry['proc']);

            if (! $status['running']) {
                fclose($entry['pipes'][1]);
                fclose($entry['pipes'][2]);
                $entry['done'] = true;
                $entry['exit_code'] = proc_close($entry['proc']);
            } else {
                $allDone = false;
            }

            $procs[$index] = $entry;
        }

        if ($allDone) {
            break;
        }

        if (microtime(true) >= $deadline) {
            foreach ($procs as $entry) {
                if (! $entry['done']) {
                    proc_terminate($entry['proc']);
                }
            }
            throw new RuntimeException('Worker timeout exceeded');
        }

        usleep(POLL_US);
    }

    $results = [];
    foreach ($procs as $entry) {
        $payload = trim($entry['stdout']);
        $stderr = trim($entry['stderr']);

        if (($entry['exit_code'] ?? 1) !== 0) {
            throw new RuntimeException('Worker failed: ' . ($payload !== '' ? $payload : $stderr));
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid worker payload: ' . $payload);
        }

        $decoded['stderr'] = $stderr;
        $results[] = $decoded;
    }

    return $results;
}

function resetScenario(PDO $pdo, string $scenario, float $coilQty, float $stockQty): void
{
    $pdo->exec('DELETE FROM stock_movements');
    $pdo->exec('DELETE FROM coils');
    $pdo->exec('DELETE FROM product_stocks');
    $stmt = $pdo->prepare('DELETE FROM barrier_waits WHERE scenario = ?');
    $stmt->execute([$scenario]);
    $pdo->prepare('INSERT INTO coils (id, remaining_weight) VALUES (1, ?)')->execute([$coilQty]);
    $pdo->prepare('INSERT INTO product_stocks (id, quantity) VALUES (1, ?)')->execute([$stockQty]);
}

function fetchState(PDO $pdo): array
{
    $coil = (float) $pdo->query('SELECT remaining_weight FROM coils WHERE id = 1')->fetchColumn();
    $stock = (float) $pdo->query('SELECT quantity FROM product_stocks WHERE id = 1')->fetchColumn();
    $movementCount = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
    $movementKinds = $pdo->query('SELECT kind, COUNT(*) AS c FROM stock_movements GROUP BY kind ORDER BY kind')->fetchAll();
    $dedupeCount = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements WHERE dedupe_key IS NOT NULL')->fetchColumn();

    return [
        'coil' => $coil,
        'stock' => $stock,
        'movement_count' => $movementCount,
        'movement_kinds' => $movementKinds,
        'dedupe_count' => $dedupeCount,
    ];
}

function assertInvariant(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function outcomes(array $results): array
{
    return array_map(static fn (array $result) => $result['outcome'], $results);
}

function countOutcome(array $results, string $outcome): int
{
    return count(array_filter($results, static fn (array $result) => $result['outcome'] === $outcome));
}

function hasAttemptGreaterThan(array $results, int $threshold): bool
{
    foreach ($results as $result) {
        if ((int) ($result['attempts'] ?? 0) > $threshold) {
            return true;
        }
    }

    return false;
}

function buildDebitWorker(string $scenario, string $worker, string $kind, float $quantity, array $locks, array $checks, array $effects, int $participants = 2, array $extra = []): array
{
    return array_merge([
        'scenario' => $scenario,
        'worker' => $worker,
        'mode' => 'debit',
        'kind' => $kind,
        'quantity' => $quantity,
        'locks' => $locks,
        'checks' => $checks,
        'effects' => $effects,
        'participants' => $participants,
    ], $extra);
}

function renderScenario(array $scenario): void
{
    echo $scenario['label'] . PHP_EOL;
    foreach ($scenario['results'] as $result) {
        $line = sprintf(
            '  %s => %s (attempts=%d)',
            $result['worker'],
            $result['outcome'],
            (int) ($result['attempts'] ?? 0)
        );
        echo $line . PHP_EOL;
    }

    echo sprintf(
        '  final coil=%.4f stock=%.4f movements=%d',
        $scenario['state']['coil'],
        $scenario['state']['stock'],
        $scenario['state']['movement_count']
    ) . PHP_EOL;
}

function runScenarioSameCoil(PDO $pdo): array
{
    $scenario = 'scenario-1-same-coil';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'consume-A', 'consume', 6, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1]),
        buildDebitWorker($scenario, 'consume-B', 'consume', 6, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1]),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 1: exactly one consumption must succeed');
    assertInvariant(countOutcome($results, 'REFUS') === 1, 'Scenario 1: exactly one consumption must be refused');
    assertInvariant($state['coil'] === 4.0 && $state['stock'] === 4.0, 'Scenario 1: final quantities must be 4/4');
    assertInvariant($state['movement_count'] === 1, 'Scenario 1: only one movement must exist');

    return ['label' => 'Scenario 1 - two concurrent consumptions on the same coil', 'results' => $results, 'state' => $state];
}

function runScenarioConsumeVsBackflush(PDO $pdo): array
{
    $scenario = 'scenario-2-consume-vs-backflush';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'consume-A', 'consume', 6, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1]),
        buildDebitWorker($scenario, 'backflush-B', 'backflush', 6, ['stock'], ['stock'], ['stock' => -1]),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 2: exactly one debit must succeed');
    assertInvariant($state['stock'] === 4.0, 'Scenario 2: stock must end at 4');
    assertInvariant(in_array($state['coil'], [4.0, 10.0], true), 'Scenario 2: coil must end at 4 or 10');
    assertInvariant($state['movement_count'] === 1, 'Scenario 2: only one movement must exist');

    return ['label' => 'Scenario 2 - concurrent consume vs backflush', 'results' => $results, 'state' => $state];
}

function runScenarioDoubleBackflush(PDO $pdo): array
{
    $scenario = 'scenario-3-double-backflush';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'backflush-A', 'backflush', 6, ['stock'], ['stock'], ['stock' => -1]),
        buildDebitWorker($scenario, 'backflush-B', 'backflush', 6, ['stock'], ['stock'], ['stock' => -1]),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 3: exactly one backflush must succeed');
    assertInvariant(countOutcome($results, 'REFUS') === 1, 'Scenario 3: exactly one backflush must be refused');
    assertInvariant($state['coil'] === 10.0 && $state['stock'] === 4.0, 'Scenario 3: final quantities must be 10/4');
    assertInvariant($state['movement_count'] === 1, 'Scenario 3: only one movement must exist');

    return ['label' => 'Scenario 3 - double backflush', 'results' => $results, 'state' => $state];
}

function runScenarioCancelDuringConsume(PDO $pdo): array
{
    $scenario = 'scenario-4-cancel-during-consume';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker(
            $scenario,
            'consume-A',
            'consume',
            6,
            ['coil', 'stock'],
            ['coil', 'stock'],
            ['coil' => -1, 'stock' => -1],
            2,
            ['dedupe_key' => 'consume-live', 'signal_phase_after_insert' => 'movement-inserted', 'hold_before_commit_us' => 1_000_000]
        ),
        [
            'scenario' => $scenario,
            'worker' => 'cancel-B',
            'mode' => 'cancel',
            'participants' => 2,
            'wait_phase' => 'movement-inserted',
            'wait_phase_count' => 1,
            'target_dedupe_key' => 'consume-live',
        ],
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 4: the consumption must still commit');
    assertInvariant(countOutcome($results, 'CANCELED') === 1, 'Scenario 4: cancel must serialize after the open consume and reverse it safely');
    assertInvariant($state['coil'] === 10.0 && $state['stock'] === 10.0, 'Scenario 4: final quantities must be restored to 10/10');
    assertInvariant($state['movement_count'] === 2, 'Scenario 4: consume plus reversal must be recorded');

    return ['label' => 'Scenario 4 - cancellation serialized behind an open consumption', 'results' => $results, 'state' => $state];
}

function runScenarioConsumeVsTransfer(PDO $pdo): array
{
    $scenario = 'scenario-5-consume-vs-transfer';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'consume-A', 'consume', 6, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1]),
        buildDebitWorker($scenario, 'transfer-B', 'transfer', 6, ['stock'], ['stock'], ['stock' => -1]),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 5: exactly one debit must succeed');
    assertInvariant($state['stock'] === 4.0, 'Scenario 5: stock must end at 4');
    assertInvariant(in_array($state['coil'], [4.0, 10.0], true), 'Scenario 5: coil must end at 4 or 10');
    assertInvariant($state['movement_count'] === 1, 'Scenario 5: only one movement must exist');

    return ['label' => 'Scenario 5 - concurrent consume vs transfer', 'results' => $results, 'state' => $state];
}

function runScenarioOverConsume(PDO $pdo): array
{
    $scenario = 'scenario-6-over-consume';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'consume-A', 'consume', 11, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1]),
        buildDebitWorker($scenario, 'consume-B', 'consume', 11, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1]),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'REFUS') === 2, 'Scenario 6: both consumptions must be refused');
    assertInvariant($state['coil'] === 10.0 && $state['stock'] === 10.0, 'Scenario 6: quantities must remain unchanged');
    assertInvariant($state['movement_count'] === 0, 'Scenario 6: no movement must be created');

    return ['label' => 'Scenario 6 - requests above the remaining quantity', 'results' => $results, 'state' => $state];
}

function runScenarioDeliberateDeadlock(PDO $pdo): array
{
    $scenario = 'scenario-7-deadlock';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'deadlock-A', 'deadlock-a', 1, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1], 2, ['after_first_lock_barrier' => true]),
        buildDebitWorker($scenario, 'deadlock-B', 'deadlock-b', 1, ['stock', 'coil'], ['stock', 'coil'], ['stock' => -1, 'coil' => -1], 2, ['after_first_lock_barrier' => true]),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'DEADLOCK') === 1, 'Scenario 7: exactly one worker must hit a deadlock');
    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 7: the other worker must complete');
    assertInvariant($state['coil'] === 9.0 && $state['stock'] === 9.0, 'Scenario 7: exactly one debit must persist');
    assertInvariant($state['movement_count'] === 1, 'Scenario 7: only one movement must exist after the deadlock');

    return ['label' => 'Scenario 7 - deliberate deadlock with opposite lock order', 'results' => $results, 'state' => $state];
}

function runScenarioControlledRetry(PDO $pdo): array
{
    $scenario = 'scenario-8-retry';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'retry-A', 'retry-a', 1, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1], 2, ['after_first_lock_barrier' => true, 'retry_max' => 2, 'dedupe_key' => 'retry-A']),
        buildDebitWorker($scenario, 'retry-B', 'retry-b', 1, ['stock', 'coil'], ['stock', 'coil'], ['stock' => -1, 'coil' => -1], 2, ['after_first_lock_barrier' => true, 'retry_max' => 2, 'dedupe_key' => 'retry-B']),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 2, 'Scenario 8: both workers must succeed after retry');
    assertInvariant(hasAttemptGreaterThan($results, 1), 'Scenario 8: at least one worker must need a retry');
    assertInvariant($state['coil'] === 8.0 && $state['stock'] === 8.0, 'Scenario 8: both debits must persist after retry');
    assertInvariant($state['movement_count'] === 2, 'Scenario 8: two movements must exist');

    return ['label' => 'Scenario 8 - controlled retry after deadlock', 'results' => $results, 'state' => $state];
}

function runScenarioNoDuplicateMovement(PDO $pdo): array
{
    $scenario = 'scenario-9-no-duplicate';
    resetScenario($pdo, $scenario, 10, 10);
    $results = runWorkers([
        buildDebitWorker($scenario, 'dedupe-A', 'consume-dedupe', 4, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1], 2, ['dedupe_key' => 'shared-dedupe-key']),
        buildDebitWorker($scenario, 'dedupe-B', 'consume-dedupe', 4, ['coil', 'stock'], ['coil', 'stock'], ['coil' => -1, 'stock' => -1], 2, ['dedupe_key' => 'shared-dedupe-key']),
    ]);
    $state = fetchState($pdo);

    assertInvariant(countOutcome($results, 'OK') === 1, 'Scenario 9: exactly one worker must create the movement');
    assertInvariant(countOutcome($results, 'DUPLICATE') === 1, 'Scenario 9: the second worker must be deduplicated');
    assertInvariant($state['coil'] === 6.0 && $state['stock'] === 6.0, 'Scenario 9: only one debit must affect quantities');
    assertInvariant($state['movement_count'] === 1, 'Scenario 9: only one movement must exist');
    assertInvariant($state['dedupe_count'] === 1, 'Scenario 9: only one dedupe key must be stored');

    return ['label' => 'Scenario 9 - duplicate movement prevention', 'results' => $results, 'state' => $state];
}

function isDeadlock(PDOException $e): bool
{
    $message = $e->getMessage();
    $sqlState = (string) $e->getCode();
    $driverCode = (string) ($e->errorInfo[1] ?? '');

    return $sqlState === '40001'
        || $driverCode === '1213'
        || str_contains($message, 'Deadlock found')
        || str_contains($message, 'try restarting transaction');
}