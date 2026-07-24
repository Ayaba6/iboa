<?php

/**
 * [Phase 2.8 — reproductibilité documentaire] Le PDF d'une facture émise est
 * archivé + empreinté ; l'original ne se réécrit jamais ; toute altération du
 * fichier stocké est détectée par l'empreinte SHA-256.
 */

use App\Models\Company;
use App\Models\DocumentArchive;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Services\DocumentArchiveService;
use Illuminate\Support\Facades\Storage;

uses(\Tests\Concerns\RefreshDatabase::class);

function arcInvoice(): Invoice
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'ARC'], ['email' => 'arc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return Invoice::create([
        'company_id' => $co->id, 'client_id' => \App\Models\Client::factory()->create()->id,
        'fiscal_year_id' => $fy->id, 'number' => 'FA-ARC-' . uniqid(), 'status' => 'emise',
        'issued_at' => now(), 'currency_code' => 'XOF',
        'subtotal_ht' => 10000, 'total_tax' => 0, 'total_ttc' => 10000, 'remaining_amount' => 10000,
    ]);
}

it('archive le PDF émis avec empreinte, idempotence et détection d\'altération', function () {
    $inv = arcInvoice();
    $svc = app(DocumentArchiveService::class);
    $bytes = '%PDF-1.4 contenu original de la facture ' . $inv->number;

    $archive = $svc->archive($inv, $bytes, $inv->number);

    // Empreinte = SHA-256 du contenu réellement stocké
    expect($archive->sha256)->toBe(hash('sha256', $bytes))
        ->and($archive->byte_size)->toBe(strlen($bytes))
        ->and($archive->verifyIntegrity())->toBeTrue()
        ->and($archive->contents())->toBe($bytes);

    // Idempotence : ré-archiver (même avec un AUTRE contenu) ne réécrit PAS
    // l'original — l'exemplaire d'origine fait foi.
    $again = $svc->archive($inv, '%PDF différent régénéré', $inv->number);
    expect($again->id)->toBe($archive->id)
        ->and($again->sha256)->toBe($archive->sha256)
        ->and(DocumentArchive::where('document_id', $inv->id)->count())->toBe(1);

    // Altération du fichier stocké → intégrité rompue (détectée)
    Storage::disk($archive->disk)->put($archive->path, 'CONTENU FALSIFIÉ');
    expect($archive->fresh()->verifyIntegrity())->toBeFalse();
});

it('archive à la génération du PDF d\'une facture émise (chemin contrôleur)', function () {
    $u = \App\Models\User::factory()->create(['company_id' => Company::firstOrCreate(['name' => 'ARC'], ['email' => 'arc@iboa.test'])->id, 'email_verified_at' => now()]);
    $u->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($u);

    $inv = arcInvoice();
    $p = \App\Models\Product::factory()->create();
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'L', 'quantity' => 1, 'unit_price' => 10000,
        'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 10000, 'line_tax' => 0, 'line_total_ttc' => 10000,
    ]);

    $this->get(route('ventes.factures.pdf', $inv))->assertOk();

    $archive = app(DocumentArchiveService::class)->for($inv);
    expect($archive)->not->toBeNull()
        ->and($archive->verifyIntegrity())->toBeTrue()
        ->and($archive->byte_size)->toBeGreaterThan(0);
});
