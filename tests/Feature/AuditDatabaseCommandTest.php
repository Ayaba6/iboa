<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('a3:audit-database passe sur une base propre et détecte une anomalie injectée', function () {
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    Company::firstOrCreate(['name' => 'AUD'], ['email' => 'a@a.io', 'current_fiscal_year_id' => $fy->id]);

    // Base propre → exit 0
    $this->artisan('a3:audit-database')->assertSuccessful();

    // Injection : client avec email invalide et espaces parasites → exit 1
    Client::factory()->create(['name' => ' Doublon SA ', 'email' => 'pas-un-email']);
    $this->artisan('a3:audit-database')->assertFailed();

    // Lecture seule : la commande n'a rien modifié
    expect(Client::where('email', 'pas-un-email')->exists())->toBeTrue();
});

// [Phase 2.3] Nouveaux contrôles : détection prouvée, pas seulement « propre ».
it('détecte un document validé sans ligne et une facture à échéance incohérente', function () {
    $fy = \App\Models\FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = \App\Models\Company::firstOrCreate(['name' => 'AUD'], ['email' => 'aud@iboa.test', 'current_fiscal_year_id' => $fy->id]);

    // Facture émise SANS ligne + échéance antérieure à l'émission
    \App\Models\Invoice::create([
        'company_id' => $co->id, 'client_id' => \App\Models\Client::factory()->create()->id,
        'fiscal_year_id' => $fy->id, 'number' => 'FA-AUD-' . uniqid(),
        'status' => 'emise', 'issued_at' => '2026-07-20', 'due_at' => '2026-07-01',
        'subtotal_ht' => 1000, 'total_tax' => 0, 'total_ttc' => 1000, 'remaining_amount' => 1000,
    ]);

    $this->artisan('a3:audit-database')->assertExitCode(1);
});
