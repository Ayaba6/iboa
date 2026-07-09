<?php

/**
 * [Maquette X3] Contrats commerciaux : création, calcul des montants
 * (remise ligne), total HT, garde suppression brouillon uniquement.
 */

use App\Models\Client;
use App\Models\CommercialContract;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ctAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CT-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CT Co'], ['email' => 'ct@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function ctClient(): Client
{
    return Client::create(['code' => 'CLI-CT-' . random_int(1000, 9999), 'type' => 'entreprise', 'name' => 'Client Contrat', 'is_active' => true]);
}

it('crée un contrat de vente avec lignes et calcule le total HT (remises incluses)', function () {
    $this->actingAs(ctAdmin());

    $this->post(route('ventes.contrats.store'), [
        'contract_type' => 'vente',
        'client_id'     => ctClient()->id,
        'description'   => 'Contrat de fourniture de tôles bac',
        'contract_date' => '2026-07-07',
        'starts_at'     => '2026-07-07',
        'ends_at'       => '2027-07-06',
        'items'         => [
            ['designation' => 'Tôle bac 0,75', 'unit' => 'ML', 'quantity' => 10000, 'unit_price' => 3500, 'discount_percent' => 0],
            ['designation' => 'Tôle bac 1,00', 'unit' => 'ML', 'quantity' => 5000, 'unit_price' => 4200, 'discount_percent' => 0],
            ['designation' => 'Accessoires', 'unit' => 'ENS', 'quantity' => 1000, 'unit_price' => 1250, 'discount_percent' => 10],
        ],
    ])->assertRedirect();

    $ct = CommercialContract::first();
    expect($ct)->not->toBeNull()
        ->and($ct->number)->toStartWith('CT-')
        ->and($ct->items)->toHaveCount(3)
        // 35 000 000 + 21 000 000 + 1 250 000 × 0,9 = 57 125 000
        ->and((float) $ct->total_ht)->toBe(57125000.0)
        ->and((float) $ct->items[2]->amount_ht)->toBe(1125000.0);
});

it('exige un client pour un contrat de vente', function () {
    $this->actingAs(ctAdmin());

    $this->post(route('ventes.contrats.store'), [
        'contract_type' => 'vente',
        'description'   => 'Sans client',
        'contract_date' => '2026-07-07',
        'starts_at'     => '2026-07-07',
    ])->assertSessionHasErrors('client_id');
});

it('refuse de supprimer un contrat actif', function () {
    $this->actingAs(ctAdmin());

    $ct = CommercialContract::create([
        'company_id' => currentCompany()->id, 'number' => 'CT-TEST-0001',
        'contract_type' => 'vente', 'client_id' => ctClient()->id,
        'description' => 'Actif', 'contract_date' => '2026-07-07',
        'starts_at' => '2026-07-07', 'status' => 'actif',
    ]);

    $this->delete(route('ventes.contrats.destroy', $ct));
    expect(CommercialContract::find($ct->id))->not->toBeNull();

    $ct->update(['status' => 'brouillon']);
    $this->delete(route('ventes.contrats.destroy', $ct));
    expect(CommercialContract::find($ct->id))->toBeNull();
});
