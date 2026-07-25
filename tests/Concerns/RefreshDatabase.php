<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * Project-local RefreshDatabase trait.
 *
 * Goals:
 *  - bypass Laravel's production confirmation prompt during `migrate:fresh`
 *  - keep a hard fail-closed guard against refreshing a non-dedicated database
 *  - support both sqlite `:memory:` and a dedicated MySQL test database
 */
trait RefreshDatabase
{
    /**
     * Define hooks to migrate the database before and after each test.
     */
    public function refreshDatabase()
    {
        $conn = config('database.default');
        $driver = config("database.connections.{$conn}.driver");
        $database = config("database.connections.{$conn}.database");

        if (! $this->isSafeTestingDatabase($driver, $database)) {
            throw new \RuntimeException(
                "REFUS DE RAFRAICHIR LA BASE : la connexion de test est « {$conn} » "
                . "(driver={$driver}, database={$database}) au lieu d'une base de test dediee. "
                . "Valeurs autorisees : sqlite/:memory: ou MySQL avec un nom contenant 'test'."
            );
        }

        if ($this->app && $this->app->bound('env')) {
            $this->app->instance('env', 'testing');
        }

        $this->beforeRefreshingDatabase();

        if ($this->usingInMemoryDatabases()) {
            $this->restoreInMemoryDatabase();
        }

        $this->refreshTestDatabase();

        $this->afterRefreshingDatabase();
    }

    /**
     * Determine if any of the connections transacting is using in-memory databases.
     */
    protected function usingInMemoryDatabases()
    {
        foreach ($this->connectionsToTransact() as $name) {
            if ($this->usingInMemoryDatabase($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given database connection is an in-memory database.
     */
    protected function usingInMemoryDatabase(?string $name = null)
    {
        if (is_null($name)) {
            $name = config('database.default');
        }

        return config("database.connections.{$name}.database") === ':memory:';
    }

    /**
     * Restore the in-memory database between tests.
     */
    protected function restoreInMemoryDatabase()
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            if (isset(RefreshDatabaseState::$inMemoryConnections[$name])) {
                $database->connection($name)->setPdo(RefreshDatabaseState::$inMemoryConnections[$name]);
            }
        }
    }

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->migrateDatabases();

            $this->app[Kernel::class]->setArtisan(null);

            $this->updateLocalCacheOfInMemoryDatabases();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Update locally cached in-memory PDO connections after migration.
     */
    protected function updateLocalCacheOfInMemoryDatabases()
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            if ($this->usingInMemoryDatabase($name)) {
                RefreshDatabaseState::$inMemoryConnections[$name] = $database->connection($name)->getPdo();
            }
        }
    }

    /**
     * Migrate the database - passes --force to skip the production prompt.
     */
    protected function migrateDatabases()
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            $this->resetMySqlTestDatabase((string) $connection);
            $this->artisan('migrate', $this->migrateUsing($connection));

            return;
        }

        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
    }

    /**
     * Override the standard migrate args to always include --force.
     */
    protected function migrateUsing(string $connection): array
    {
        $seeder = property_exists($this, 'seeder') ? $this->seeder : false;

        return array_merge(
            [
                '--database' => $connection,
                '--force' => true,
            ],
            $seeder ? ['--seeder' => $seeder] : ['--seed' => property_exists($this, 'seed') ? $this->seed : false]
        );
    }

    /**
     * Override the standard migrate:fresh args to always include --force.
     */
    protected function migrateFreshUsing()
    {
        $seeder = property_exists($this, 'seeder') ? $this->seeder : false;

        return array_merge(
            [
                '--drop-views' => property_exists($this, 'dropViews') ? $this->dropViews : false,
                '--drop-types' => property_exists($this, 'dropTypes') ? $this->dropTypes : false,
                '--force' => true,
            ],
            $seeder ? ['--seeder' => $seeder] : ['--seed' => property_exists($this, 'seed') ? $this->seed : false]
        );
    }

    /**
     * Begin a database transaction on the testing database.
     */
    public function beginDatabaseTransaction()
    {
        $database = $this->app->make('db');

        $connections = $this->connectionsToTransact();

        $this->app->instance('db.transactions', $transactionsManager = new \Illuminate\Foundation\Testing\DatabaseTransactionsManager(
            $connections,
        ));

        foreach ($connections as $name) {
            $connection = $database->connection($name);
            $connection->setTransactionManager($transactionsManager);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database, $connections) {
            foreach ($connections as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }

    /**
     * The database connections that should have transactions.
     */
    protected function connectionsToTransact()
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact : [null];
    }

    /**
     * Perform any work that should take place before the database has started refreshing.
     */
    protected function beforeRefreshingDatabase()
    {
        // ...
    }

    /**
     * Perform any work that should take place once the database has finished refreshing.
     */
    protected function afterRefreshingDatabase()
    {
        // ...
    }


    private function resetMySqlTestDatabase(string $connection): void
    {
        $config = config("database.connections.{$connection}", []);
        $database = (string) ($config['database'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');

        if ($database === '') {
            throw new \RuntimeException('Base MySQL de test introuvable dans la configuration.');
        }

        DB::purge($connection);

        $dsn = sprintf('mysql:host=%s;port=%d', $host, $port);
        $pdo = new \PDO($dsn, $username, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $quotedDatabase = str_replace('`', '``', $database);
        $pdo->exec("DROP DATABASE IF EXISTS `{$quotedDatabase}`");
        $pdo->exec("CREATE DATABASE `{$quotedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");

        DB::purge($connection);
    }

    private function isSafeTestingDatabase(?string $driver, mixed $database): bool
    {
        if ($driver === 'sqlite' && $database === ':memory:') {
            return true;
        }

        if ($driver === 'mysql' && is_string($database)) {
            return (bool) preg_match('/(^|_)(test|testing)(_|$)/i', $database);
        }

        return false;
    }
}
