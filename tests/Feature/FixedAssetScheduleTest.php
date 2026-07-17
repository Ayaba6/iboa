<?php

use App\Models\FixedAsset;
use App\Services\FixedAssetService;

uses(\Tests\Concerns\RefreshDatabase::class);

/**
 * Plan d'amortissement des immobilisations — conformité SYSCOHADA :
 * base = valeur brute (acquisition + frais accessoires) − valeur résiduelle,
 * prorata temporis en jours la 1ère année, aucune dotation ne dépasse
 * l'annuité linéaire, le solde glisse sur l'exercice N+durée.
 */

function assetCompany(): \App\Models\Company
{
    return bfCompany();
}

function makeAsset(array $overrides = []): FixedAsset
{
    $company = assetCompany();

    return FixedAsset::create(array_merge([
        'company_id'          => $company->id,
        'code'                => 'IMB-TEST-' . uniqid(),
        'name'                => 'Machine test',
        'category'            => 'materiel_industriel',
        'acquisition_date'    => '2026-07-17',
        'commissioning_date'  => '2026-07-17',
        'acquisition_cost'    => 12_000_000,
        'accessory_cost'      => 500_000,
        'residual_value'      => 0,
        'useful_life_years'   => 5,
        'depreciation_method' => 'lineaire',
        'asset_account'       => '241000',
        'depr_account'        => '284100',
        'charge_account'      => '681100',
        'status'              => 'en_service',
    ], $overrides));
}

describe('Plan d\'amortissement linéaire', function () {

    it('inclut les frais accessoires dans la base amortissable', function () {
        $this->actingAs(bfAdmin());
        $asset = makeAsset();

        expect($asset->gross_value)->toBe(12_500_000);

        $rows = app(FixedAssetService::class)->computeSchedule($asset);
        expect(array_sum(array_column($rows, 'depreciation_amount')))->toBe(12_500_000);
    });

    it('étale le plan sur durée+1 exercices avec prorata jours et annuité plafonnée', function () {
        $this->actingAs(bfAdmin());
        $asset = makeAsset(); // mise en service 17/07/2026 → 168 jours restants

        $rows    = app(FixedAssetService::class)->computeSchedule($asset);
        $annuite = (int) round(12_500_000 / 5); // 2 500 000

        expect($rows)->toHaveCount(6)
            ->and($rows[0]['fiscal_year'])->toBe(2026)
            ->and($rows[0]['depreciation_amount'])->toBe((int) round($annuite * 168 / 365)) // 1 150 685
            ->and(collect($rows)->max('depreciation_amount'))->toBeLessThanOrEqual($annuite)
            ->and($rows[5]['fiscal_year'])->toBe(2031)
            ->and(end($rows)['net_book_value'])->toBe(0)
            ->and(end($rows)['cumulated_depreciation'])->toBe(12_500_000);
    });

    it('termine le plan à la valeur résiduelle quand elle est non nulle', function () {
        $this->actingAs(bfAdmin());
        $asset = makeAsset(['residual_value' => 2_000_000]);

        $rows = app(FixedAssetService::class)->computeSchedule($asset);

        expect(array_sum(array_column($rows, 'depreciation_amount')))->toBe(10_500_000) // base = 12,5M − 2M
            ->and(end($rows)['net_book_value'])->toBe(2_000_000); // VNC finale = valeur résiduelle
    });

    it('ne fait aucun prorata quand la mise en service est au 1er janvier', function () {
        $this->actingAs(bfAdmin());
        $asset = makeAsset([
            'acquisition_date'   => '2026-01-01',
            'commissioning_date' => '2026-01-01',
            'accessory_cost'     => 0,
            'acquisition_cost'   => 10_000_000,
        ]);

        $rows = app(FixedAssetService::class)->computeSchedule($asset);

        expect($rows)->toHaveCount(5)
            ->and($rows[0]['depreciation_amount'])->toBe(2_000_000)
            ->and(end($rows)['cumulated_depreciation'])->toBe(10_000_000);
    });
});
