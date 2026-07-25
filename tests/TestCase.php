<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mirror the testing env selected by PHPUnit/Pest back into the real
     * process env before the Laravel app boots for each test case.
     *
     * This keeps the historical safeguard against a stray local `.env`, while
     * finally respecting `phpunit.mysql.xml` when the suite intentionally asks
     * for a dedicated MySQL test database.
     */
    protected function setUp(): void
    {
        $overrides = $this->testingOverrides();

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();

        $this->app['env'] = $overrides['APP_ENV'] ?? 'testing';
    }

    /**
     * @return array<string, string|null>
     */
    private function testingOverrides(): array
    {
        $appEnv = $this->testingEnvValue('APP_ENV') ?: 'testing';
        $dbConnection = $this->testingEnvValue('DB_CONNECTION') ?: 'sqlite';
        $dbDatabase = $this->testingEnvValue('DB_DATABASE');

        if (! $dbDatabase && $dbConnection === 'sqlite') {
            $dbDatabase = ':memory:';
        }

        return [
            'APP_ENV' => $appEnv,
            'DB_CONNECTION' => $dbConnection,
            'DB_DATABASE' => $dbDatabase,
            'DB_URL' => $this->testingEnvValue('DB_URL') ?? '',
            'SESSION_DRIVER' => $this->testingEnvValue('SESSION_DRIVER') ?: 'array',
            'CACHE_STORE' => $this->testingEnvValue('CACHE_STORE') ?: 'array',
            'QUEUE_CONNECTION' => $this->testingEnvValue('QUEUE_CONNECTION') ?: 'sync',
            'MAIL_MAILER' => $this->testingEnvValue('MAIL_MAILER') ?: 'array',
            'BCRYPT_ROUNDS' => $this->testingEnvValue('BCRYPT_ROUNDS') ?: '4',
            'PULSE_ENABLED' => $this->testingEnvValue('PULSE_ENABLED') ?: 'false',
            'TELESCOPE_ENABLED' => $this->testingEnvValue('TELESCOPE_ENABLED') ?: 'false',
            'NIGHTWATCH_ENABLED' => $this->testingEnvValue('NIGHTWATCH_ENABLED') ?: 'false',
        ];
    }

    private function testingEnvValue(string $key): ?string
    {
        foreach ([
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
            getenv($key) ?: null,
        ] as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
