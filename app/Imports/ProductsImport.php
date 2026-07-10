<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class ProductsImport implements ToCollection, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    public int $imported = 0;
    public int $skipped  = 0;

    // Expected columns (heading row):
    // reference | nom | famille | unite | prix_vente | prix_achat | tva | stock_min | stock_max | description

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ref  = trim($row['reference'] ?? '');
            $name = trim($row['nom']       ?? '');

            if (empty($name)) {
                $this->skipped++;
                continue;
            }

            // Produits et familles sont GLOBAUX (pas de colonne company_id).
            $family = null;
            if (!empty($row['famille'])) {
                $family = ProductFamily::firstOrCreate(
                    ['name' => trim($row['famille'])],
                    ['code' => strtoupper(substr(trim($row['famille']), 0, 6)), 'is_active' => true]
                );
            }

            $unit = null;
            if (!empty($row['unite'])) {
                $unit = Unit::where('abbreviation', trim($row['unite']))->first()
                     ?? Unit::where('name', trim($row['unite']))->first();
            }

            $taxRate = null;
            if (!empty($row['tva'])) {
                $taxRate = TaxRate::where('rate', (float) $row['tva'])->first();
            }

            Product::updateOrCreate(
                ['reference' => $ref ?: null],
                [
                    'name'            => $name,
                    'family_id'       => $family?->id,
                    'unit_id'         => $unit?->id,
                    'tax_rate_id'     => $taxRate?->id,
                    'sale_price'      => (int) ($row['prix_vente'] ?? 0),
                    'purchase_price'  => (int) ($row['prix_achat'] ?? 0),
                    'stock_min'       => (float) ($row['stock_min'] ?? 0),
                    'stock_max'       => (float) ($row['stock_max'] ?? 0),
                    'description'     => trim($row['description'] ?? ''),
                    'is_active'       => true,
                    'is_stockable'    => true,
                ]
            );

            $this->imported++;
        }
    }
}
