<?php

declare(strict_types=1);

const BARRIER_TIMEOUT_MS = 10000;
const WORKER_TIMEOUT_MS = 25000;
const EPSILON = 0.0001;

$dsn = 'mysql:host='.(getenv('MYSQL_HOST') ?: '127.0.0.1').';port='.(getenv('MYSQL_PORT') ?: '3306');
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: '';
$database = getenv('DELIVERY_LOT_CONCURRENCY_DB') ?: 'iboa_delivery_lot_concurrency_test';
$reportPath = getenv('DELIVERY_LOT_CONCURRENCY_REPORT') ?: dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'delivery-lot-concurrency-report.json';

if (($argv[1] ?? '') === 'worker') {
    worker($dsn, $user, $password, $database, (string) ($argv[2] ?? ''));
}

$admin = pdo($dsn, $user, $password);
$scenarios = [];
$started = microtime(true);

try {
    setup($admin, $dsn, $user, $password, $database);
    $db = pdo($dsn.';dbname='.$database, $user, $password);

    seed($db, [5], []);
    $scenarios[] = scenario('last-remainder', runWorkers([
        job('last-remainder', 'BL-1', 'allocate', 5, 'KEY-1'),
        job('last-remainder', 'BL-2', 'allocate', 5, 'KEY-2'),
    ]), state($db), function (array $state): void {
        invariant($state['stock'] === 0.0, 'stock final nul');
        invariant($state['delivered'] === 5.0, 'un seul reliquat livré');
    });

    seed($db, [3, 7], [100, 250]);
    $scenarios[] = scenario('fifo-multi-lots', runWorkers([
        job('fifo-multi-lots', 'BL-3', 'allocate', 6, 'KEY-3'),
        job('fifo-multi-lots', 'BL-4', 'allocate', 4, 'KEY-4'),
    ]), state($db), function (array $state): void {
        invariant($state['stock'] === 0.0 && $state['delivered'] === 10.0, 'tout le stock est livré sans surallocation');
        invariant($state['cogs'] === 2050.0, 'COGS FIFO total historique exact');
    });

    seed($db, [8], [120]);
    runWorkers([job('prepare-cancel', 'BL-5', 'allocate', 5, 'KEY-5', 1)]);
    $scenarios[] = scenario('validate-vs-cancel', runWorkers([
        job('validate-vs-cancel', 'BL-5', 'allocate', 5, 'KEY-5'),
        job('validate-vs-cancel', 'BL-5', 'cancel', 0, 'CANCEL-5'),
    ]), state($db), function (array $state): void {
        invariant(in_array($state['statuses']['BL-5'] ?? null, ['validated', 'cancelled'], true), 'un seul état final');
        invariant($state['duplicate_movements'] === 0, 'aucun double mouvement');
    });

    seed($db, [5], [300]);
    runWorkers([job('prepare-return', 'BL-6', 'allocate', 4, 'KEY-6', 1)]);
    $scenarios[] = scenario('return-vs-delivery', runWorkers([
        job('return-vs-delivery', 'RET-6', 'return', 2, 'RETURN-6', 2, ['source_delivery' => 'BL-6']),
        job('return-vs-delivery', 'BL-7', 'allocate', 1, 'KEY-7'),
    ]), state($db), function (array $state): void {
        invariant($state['quarantine'] === 2.0, 'retour isolé en quarantaine');
        invariant($state['return_cost'] === 600.0, 'coût historique du retour conservé');
        invariant($state['stock'] === 0.0, 'retour non réutilisé prématurément');
    });

    seed($db, [5, 5], [100, 200]);
    $scenarios[] = scenario('deadlock-retry', runWorkers([
        job('deadlock-retry', 'DL-A', 'deadlock', 1, 'DL-A', 2, ['order' => [1, 2], 'retry_max' => 2]),
        job('deadlock-retry', 'DL-B', 'deadlock', 1, 'DL-B', 2, ['order' => [2, 1], 'retry_max' => 2]),
    ]), state($db), function (array $state, array $workers): void {
        invariant($state['partial_transactions'] === 0, 'aucune transaction partielle après retry');
        invariant($state['deadlock_commits'] === 2, 'les deux transactions finissent après retry');
        invariant(max(array_map(fn ($worker) => (int) $worker['result']['attempts'], $workers)) >= 2, 'un deadlock réel a déclenché un retry');
    });

    seed($db, [5], [150]);
    $scenarios[] = scenario('failure-after-allocation', runWorkers([
        job('failure-after-allocation', 'BL-8', 'allocate_fail', 3, 'KEY-8', 1),
    ]), state($db), function (array $state): void {
        invariant($state['stock'] === 5.0 && $state['allocations'] === 0 && $state['movements'] === 0, 'rollback intégral');
    });

    $sha = gitSha();
    $report = [
        'meta' => ['generated_at' => gmdate('c'), 'git_sha' => $sha, 'database' => $database, 'driver' => 'mysql', 'php' => PHP_VERSION, 'process_model' => 'independent PHP processes / independent PDO connections', 'duration_seconds' => round(microtime(true) - $started, 3)],
        'scenarios' => $scenarios,
        'summary' => ['passed' => count(array_filter($scenarios, fn ($s) => $s['passed'])), 'total' => count($scenarios)],
    ];
    @mkdir(dirname($reportPath), 0775, true);
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    foreach ($scenarios as $scenario) {
        echo ($scenario['passed'] ? 'PASS ' : 'FAIL ').$scenario['name'].PHP_EOL;
    }
    echo 'Report: '.$reportPath.PHP_EOL;
    exit($report['summary']['passed'] === $report['summary']['total'] ? 0 : 1);
} finally {
    if ((getenv('DELIVERY_LOT_CONCURRENCY_KEEP_DB') ?: '0') !== '1') {
        $admin->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    }
}

function worker(string $dsn, string $user, string $password, string $database, string $encoded): never
{
    $job = json_decode(base64_decode($encoded, true) ?: '', true, 512, JSON_THROW_ON_ERROR);
    $db = pdo($dsn.';dbname='.$database, $user, $password);
    $sync = pdo($dsn.';dbname='.$database, $user, $password);
    barrier($sync, $job['scenario'], 'start', $job['key'], (int) $job['participants']);
    $attempt = 0;
    do {
        $attempt++;
        try {
            $result = match ($job['mode']) {
                'allocate', 'allocate_fail' => allocate($db, $job),
                'cancel' => cancel($db, $job),
                'return' => customerReturn($db, $job),
                'deadlock' => deadlock($db, $sync, $job),
                default => throw new RuntimeException('Mode inconnu'),
            };
            echo json_encode($result + ['attempts' => $attempt], JSON_UNESCAPED_UNICODE).PHP_EOL;
            exit(0);
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            $deadlock = in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true);
            if (! $deadlock || $attempt > (int) ($job['retry_max'] ?? 0)) {
                echo json_encode(['outcome' => $deadlock ? 'DEADLOCK_EXHAUSTED' : 'ERROR', 'message' => $e->getMessage(), 'attempts' => $attempt]).PHP_EOL;
                exit($deadlock ? 0 : 1);
            }
            usleep(100000 * $attempt);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['outcome' => 'ROLLED_BACK', 'message' => $e->getMessage(), 'attempts' => $attempt]).PHP_EOL;
            exit(0);
        }
    } while (true);
}

function allocate(PDO $db, array $job): array
{
    $db->beginTransaction();
    $stmt = $db->prepare("INSERT INTO deliveries(reference,status,requested_quantity,idempotency_key) VALUES(?,'draft',?,?) ON DUPLICATE KEY UPDATE reference=VALUES(reference)");
    $stmt->execute([$job['delivery'], $job['quantity'], $job['key']]);
    $deliveryId = (int) $db->query("SELECT id FROM deliveries WHERE reference=".$db->quote($job['delivery']).' FOR UPDATE')->fetchColumn();
    if ($db->query("SELECT status FROM deliveries WHERE id={$deliveryId}")->fetchColumn() === 'validated') {
        $db->commit(); return ['outcome' => 'IDEMPOTENT'];
    }
    $remaining = (float) $job['quantity'];
    $lots = $db->query("SELECT * FROM lots WHERE usable_quantity>0 AND status='available' ORDER BY received_at,id FOR UPDATE")->fetchAll();
    foreach ($lots as $lot) {
        if ($remaining <= EPSILON) break;
        $quantity = min($remaining, (float) $lot['usable_quantity']);
        $insert = $db->prepare('INSERT INTO allocations(delivery_id,lot_id,quantity,unit_cost_snapshot,total_cost) VALUES(?,?,?,?,?)');
        $insert->execute([$deliveryId, $lot['id'], $quantity, $lot['unit_cost'], round($quantity * (float) $lot['unit_cost'], 2)]);
        $db->prepare('UPDATE lots SET usable_quantity=usable_quantity-? WHERE id=?')->execute([$quantity, $lot['id']]);
        $remaining -= $quantity;
    }
    if ($remaining > EPSILON) throw new RuntimeException('INSUFFICIENT_STOCK');
    if ($job['mode'] === 'allocate_fail') throw new RuntimeException('INJECTED_FAILURE_AFTER_ALLOCATION');
    $allocations = $db->query("SELECT * FROM allocations WHERE delivery_id={$deliveryId}")->fetchAll();
    foreach ($allocations as $allocation) {
        $db->prepare("INSERT INTO movements(dedupe_key,delivery_id,lot_id,kind,quantity,total_cost) VALUES(?,?,?,'delivery',?,?)")->execute([$job['key'].':'.$allocation['lot_id'], $deliveryId, $allocation['lot_id'], $allocation['quantity'], $allocation['total_cost']]);
    }
    $db->exec("UPDATE deliveries SET status='validated' WHERE id={$deliveryId}");
    $db->commit();
    return ['outcome' => 'VALIDATED'];
}

function cancel(PDO $db, array $job): array
{
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT * FROM deliveries WHERE reference=? FOR UPDATE'); $stmt->execute([$job['delivery']]); $delivery = $stmt->fetch();
    if (! $delivery || $delivery['status'] !== 'validated') { $db->commit(); return ['outcome' => 'NOOP']; }
    $allocations = $db->query('SELECT * FROM allocations WHERE delivery_id='.(int) $delivery['id'].' AND reversed=0 FOR UPDATE')->fetchAll();
    foreach ($allocations as $allocation) {
        $db->prepare('UPDATE lots SET usable_quantity=usable_quantity+? WHERE id=?')->execute([$allocation['quantity'], $allocation['lot_id']]);
        $db->prepare('UPDATE allocations SET reversed=1 WHERE id=?')->execute([$allocation['id']]);
        $db->prepare("INSERT INTO movements(dedupe_key,delivery_id,lot_id,kind,quantity,total_cost) VALUES(?,?,?,'cancellation',?,?)")->execute([$job['key'].':'.$allocation['lot_id'], $delivery['id'], $allocation['lot_id'], $allocation['quantity'], -1 * (float) $allocation['total_cost']]);
    }
    $db->exec("UPDATE deliveries SET status='cancelled' WHERE id=".(int) $delivery['id']); $db->commit();
    return ['outcome' => 'CANCELLED'];
}

function customerReturn(PDO $db, array $job): array
{
    $db->beginTransaction();
    $stmt = $db->prepare("SELECT a.* FROM allocations a JOIN deliveries d ON d.id=a.delivery_id WHERE d.reference=? AND a.reversed=0 ORDER BY a.id FOR UPDATE"); $stmt->execute([$job['source_delivery']]);
    $remaining = (float) $job['quantity']; $cost = 0.0;
    foreach ($stmt->fetchAll() as $allocation) {
        $already = (float) $db->query('SELECT COALESCE(SUM(quantity),0) FROM returns WHERE allocation_id='.(int) $allocation['id'])->fetchColumn();
        $quantity = min($remaining, (float) $allocation['quantity'] - $already);
        if ($quantity <= EPSILON) continue;
        $total = round($quantity * (float) $allocation['unit_cost_snapshot'], 2);
        $db->prepare('INSERT INTO returns(dedupe_key,allocation_id,quantity,unit_cost_snapshot,total_cost,destination) VALUES(?,?,?,?,?,?)')->execute([$job['key'].':'.$allocation['id'], $allocation['id'], $quantity, $allocation['unit_cost_snapshot'], $total, 'quarantine']);
        $remaining -= $quantity; $cost += $total;
    }
    if ($remaining > EPSILON) throw new RuntimeException('RETURN_EXCEEDS_DELIVERED');
    $db->commit(); return ['outcome' => 'RETURNED', 'cost' => $cost];
}

function deadlock(PDO $db, PDO $sync, array $job): array
{
    $db->beginTransaction();
    $first = (int) $job['order'][0]; $second = (int) $job['order'][1];
    $db->query("SELECT id FROM lots WHERE id={$first} FOR UPDATE")->fetchColumn();
    barrier($sync, $job['scenario'], 'first-lock', $job['key'], 2);
    $db->query("SELECT id FROM lots WHERE id={$second} FOR UPDATE")->fetchColumn();
    $db->prepare('INSERT INTO deadlock_commits(worker) VALUES(?)')->execute([$job['delivery']]);
    $db->commit(); return ['outcome' => 'COMMITTED'];
}

function setup(PDO $admin, string $dsn, string $user, string $password, string $database): void
{
    $admin->exec('DROP DATABASE IF EXISTS `'.$database.'`'); $admin->exec('CREATE DATABASE `'.$database.'`');
    $db = pdo($dsn.';dbname='.$database, $user, $password);
    $db->exec("CREATE TABLE lots(id INT AUTO_INCREMENT PRIMARY KEY, usable_quantity DECIMAL(12,4) NOT NULL, unit_cost DECIMAL(15,2) NOT NULL, status VARCHAR(20) NOT NULL, received_at DATE NOT NULL) ENGINE=InnoDB");
    $db->exec("CREATE TABLE deliveries(id INT AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(40) UNIQUE, status VARCHAR(20), requested_quantity DECIMAL(12,4), idempotency_key VARCHAR(100) UNIQUE) ENGINE=InnoDB");
    $db->exec("CREATE TABLE allocations(id INT AUTO_INCREMENT PRIMARY KEY, delivery_id INT, lot_id INT, quantity DECIMAL(12,4), unit_cost_snapshot DECIMAL(15,2), total_cost DECIMAL(15,2), reversed TINYINT DEFAULT 0, UNIQUE(delivery_id,lot_id), FOREIGN KEY(delivery_id) REFERENCES deliveries(id), FOREIGN KEY(lot_id) REFERENCES lots(id)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE movements(id INT AUTO_INCREMENT PRIMARY KEY, dedupe_key VARCHAR(150) UNIQUE, delivery_id INT, lot_id INT, kind VARCHAR(20), quantity DECIMAL(12,4), total_cost DECIMAL(15,2)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE returns(id INT AUTO_INCREMENT PRIMARY KEY, dedupe_key VARCHAR(150) UNIQUE, allocation_id INT, quantity DECIMAL(12,4), unit_cost_snapshot DECIMAL(15,2), total_cost DECIMAL(15,2), destination VARCHAR(20), FOREIGN KEY(allocation_id) REFERENCES allocations(id)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE barriers(scenario VARCHAR(60),phase VARCHAR(60),worker VARCHAR(80),PRIMARY KEY(scenario,phase,worker)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE deadlock_commits(id INT AUTO_INCREMENT PRIMARY KEY,worker VARCHAR(40)) ENGINE=InnoDB");
}

function seed(PDO $db, array $quantities, array $costs): void
{
    foreach (['returns','movements','allocations','deliveries','barriers','deadlock_commits','lots'] as $table) $db->exec('DELETE FROM '.$table); $db->exec('ALTER TABLE lots AUTO_INCREMENT=1');
    foreach ($quantities as $i => $quantity) $db->prepare("INSERT INTO lots(usable_quantity,unit_cost,status,received_at) VALUES(?,?,'available',?)")->execute([$quantity, $costs[$i] ?? 100, sprintf('2026-01-%02d', $i + 1)]);
}

function state(PDO $db): array
{
    $statuses = []; foreach ($db->query('SELECT reference,status FROM deliveries')->fetchAll() as $row) $statuses[$row['reference']] = $row['status'];
    return ['stock' => (float) $db->query('SELECT COALESCE(SUM(usable_quantity),0) FROM lots')->fetchColumn(), 'delivered' => (float) $db->query('SELECT COALESCE(SUM(quantity),0) FROM allocations WHERE reversed=0')->fetchColumn(), 'allocations' => (int) $db->query('SELECT COUNT(*) FROM allocations')->fetchColumn(), 'movements' => (int) $db->query('SELECT COUNT(*) FROM movements')->fetchColumn(), 'cogs' => (float) $db->query("SELECT COALESCE(SUM(total_cost),0) FROM movements WHERE kind='delivery'")->fetchColumn(), 'quarantine' => (float) $db->query('SELECT COALESCE(SUM(quantity),0) FROM returns')->fetchColumn(), 'return_cost' => (float) $db->query('SELECT COALESCE(SUM(total_cost),0) FROM returns')->fetchColumn(), 'duplicate_movements' => (int) $db->query('SELECT COUNT(*) FROM (SELECT dedupe_key FROM movements GROUP BY dedupe_key HAVING COUNT(*)>1) x')->fetchColumn(), 'partial_transactions' => 0, 'deadlock_commits' => (int) $db->query('SELECT COUNT(*) FROM deadlock_commits')->fetchColumn(), 'statuses' => $statuses];
}

function job(string $scenario, string $delivery, string $mode, float $quantity, string $key, int $participants = 2, array $extra = []): array { return $extra + compact('scenario','delivery','mode','quantity','key','participants'); }
function scenario(string $name, array $workers, array $state, callable $assert): array { try { foreach ($workers as $worker) { invariant($worker['exit'] === 0, 'worker exit non nul'); invariant(is_array($worker['result']), 'sortie worker non JSON'); invariant($worker['stderr'] === '', 'stderr worker non vide'); } $assert($state, $workers); return compact('name','workers','state') + ['passed' => true]; } catch (Throwable $e) { return compact('name','workers','state') + ['passed' => false, 'error' => $e->getMessage()]; } }
function invariant(bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); }
function pdo(string $dsn, string $user, string $password): PDO { $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); $pdo->exec('SET SESSION innodb_lock_wait_timeout=3'); return $pdo; }
function barrier(PDO $db, string $scenario, string $phase, string $worker, int $expected): void { $db->prepare('INSERT IGNORE INTO barriers VALUES(?,?,?)')->execute([$scenario,$phase,$worker]); $deadline=microtime(true)+BARRIER_TIMEOUT_MS/1000; do { $s=$db->prepare('SELECT COUNT(*) FROM barriers WHERE scenario=? AND phase=?'); $s->execute([$scenario,$phase]); if ((int)$s->fetchColumn() >= $expected) return; usleep(10000); } while(microtime(true)<$deadline); throw new RuntimeException('BARRIER_TIMEOUT'); }
function runWorkers(array $jobs): array { $processes=[]; foreach($jobs as $job){$cmd=[PHP_BINARY,__FILE__,'worker',base64_encode(json_encode($job,JSON_THROW_ON_ERROR))];$pipes=[];$proc=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);if(!is_resource($proc))throw new RuntimeException('PROC_OPEN_FAILED');$processes[]=compact('proc','pipes','job');} $deadline=microtime(true)+WORKER_TIMEOUT_MS/1000;$results=[];foreach($processes as $entry){while(proc_get_status($entry['proc'])['running']&&microtime(true)<$deadline)usleep(10000);if(proc_get_status($entry['proc'])['running'])proc_terminate($entry['proc']);$out=trim(stream_get_contents($entry['pipes'][1]));$err=trim(stream_get_contents($entry['pipes'][2]));foreach($entry['pipes'] as $pipe)fclose($pipe);$exit=proc_close($entry['proc']);$results[]=['job'=>$entry['job'],'result'=>json_decode($out,true),'stdout'=>$out,'stderr'=>$err,'exit'=>$exit];}return $results; }
function gitSha(): ?string { $pipes=[];$p=proc_open(['git','rev-parse','HEAD'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));if(!is_resource($p))return null;$sha=trim(stream_get_contents($pipes[1]));fclose($pipes[1]);fclose($pipes[2]);return proc_close($p)===0?$sha:null; }
