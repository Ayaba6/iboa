<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * [FIX-TESTS-01] Project-local RefreshDatabase trait that bypasses Laravel's
 * production-confirmation prompt during `migrate:fresh`.
 *
 * Without this fix, every Feature test fails with:
 *
 *   BadMethodCallException
 *   Received Mockery_*_OutputStyle::askQuestion(), but no expectations
 *   were specified at vendor/symfony/console/Style/SymfonyStyle.php:234
 *
 * Why: this project's `.env` declares `APP_ENV=production`. The cached
 * `Env` repository keeps that value across the bootstrap cycle, so
 * `Application::environment()` returns `'production'` when the migrate
 * command's `confirmToProceed()` runs. The interactive prompt then
 * triggers `askQuestion()` on the test runner's mocked OutputStyle.
 *
 * The fix is twofold:
 *  - rebind `$app['env']` to `'testing'` before each migration runs
 *  - pass `--force` to `migrate:fresh` so even if the env override misses,
 *    the prompt is still skipped.
 *
 * This trait re-implements the public surface of Laravel's RefreshDatabase
 * so we don't have to fight PHP's trait composition rules.
 */
trait RefreshDatabase
{
    /**
     * Define hooks to migrate the database before and after each test.
     */
    public function refreshDatabase()
    {
        // [SAFEGUARD] Refuse catégoriquement de rafraîchir une base non-SQLite.
        //
        // Sinon, lancer `php artisan test` alors que la config est cachée
        // (`php artisan config:cache`) fait lire DB_CONNECTION=mysql depuis le
        // cache au lieu du SQLite :memory: de phpunit.xml — et migrate:fresh
        // DÉTRUIT la vraie base MySQL de développement/production.
        $conn = config('database.default');
        $driver = config("database.connections.{$conn}.driver");
        $database = config("database.connections.{$conn}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                "REFUS DE RAFRAÎCHIR LA BASE : la connexion de test est « {$conn} » "
                . "(driver={$driver}, database={$database}) au lieu de sqlite/:memory:. "
                . "Cause probable : config cachée. Lancez `php artisan config:clear` "
                . "avant `php artisan test`."
            );
        }

        // Force-set the environment to 'testing' so the migrate command's
        // confirmToProceed() check returns false without prompting.
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
     * Migrate the database — passes --force to skip the production prompt.
     */
    protected function migrateDatabases()
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
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
                '--force'      => true,
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

        // [SEC-PHASE2 §8] Manager de TESTING (pas celui de prod) : il exécute
        // les callbacks DB::afterCommit au commit de niveau 1 (transaction des
        // services), indispensable pour tester la journalisation post-commit.
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

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->rollback();
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
}
