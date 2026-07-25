<?php

uses(\Tests\Concerns\RefreshDatabase::class);

it('uses a safe dedicated testing database configuration', function () {
    $connection = config('database.default');
    $driver = config("database.connections.{$connection}.driver");
    $database = config("database.connections.{$connection}.database");

    expect(app()->environment())->toBe('testing');

    if ($driver === 'sqlite') {
        expect($database)->toBe(':memory:');

        return;
    }

    expect($driver)->toBe('mysql')
        ->and((string) $database)->toContain('test');
});
