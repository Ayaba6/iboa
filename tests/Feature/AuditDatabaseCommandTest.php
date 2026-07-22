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
