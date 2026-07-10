<?php

use App\Models\Supplier;
use App\Services\SupplierService;

uses(\Tests\Concerns\RefreshDatabase::class);

function supplierSvc(): SupplierService
{
    return app(SupplierService::class);
}

it('génère le premier code quand aucun fournisseur', function () {
    expect(supplierSvc()->generateCode())->toBe('FOUR-00001');
});

it('incrémente à partir du plus grand numéro, pas du dernier créé', function () {
    // Dernier créé (id le plus haut) a un petit numéro → l'ancien algo cassait ici
    Supplier::factory()->create(['code' => 'FOUR-00050', 'name' => 'A']);
    Supplier::factory()->create(['code' => 'FOUR-00002', 'name' => 'B']);

    expect(supplierSvc()->generateCode())->toBe('FOUR-00051');
});

it('ignore les codes manuels non numériques', function () {
    Supplier::factory()->create(['code' => 'FOUR-FER-01', 'name' => 'A']);
    Supplier::factory()->create(['code' => 'ACME-2024', 'name' => 'B']);

    expect(supplierSvc()->generateCode())->toBe('FOUR-00001');
});

it('évite les collisions en sautant les codes déjà pris (trous de séquence)', function () {
    // Numéro max = 2 mais FOUR-00003 déjà pris → doit sauter à 00004
    Supplier::factory()->create(['code' => 'FOUR-00002', 'name' => 'A']);
    Supplier::factory()->create(['code' => 'FOUR-00003', 'name' => 'B']);

    expect(supplierSvc()->generateCode())->toBe('FOUR-00004');
});

it('tient compte des fournisseurs soft-deleted', function () {
    $s = Supplier::factory()->create(['code' => 'FOUR-00009', 'name' => 'A']);
    $s->delete();

    // Le code d'un fournisseur archivé reste unique en base → ne pas le réutiliser
    expect(supplierSvc()->generateCode())->toBe('FOUR-00010');
});

it('crée deux fournisseurs consécutifs sans collision', function () {
    Supplier::factory()->create(['code' => 'FOUR-00001', 'name' => 'X']);
    Supplier::factory()->create(['code' => 'FOUR-00002', 'name' => 'Y']);

    $a = supplierSvc()->create(['name' => 'GEN A', 'type' => 'entreprise']);
    $b = supplierSvc()->create(['name' => 'GEN B', 'type' => 'entreprise']);

    expect($a->code)->toBe('FOUR-00003')
        ->and($b->code)->toBe('FOUR-00004');
});
