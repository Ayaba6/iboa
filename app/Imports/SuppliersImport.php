<?php

namespace App\Imports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class SuppliersImport implements ToCollection, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    public int $imported = 0;
    public int $skipped  = 0;

    // Expected columns: nom | code | email | telephone | adresse | ville | pays | ifu | rccm | notes

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim($row['nom'] ?? '');

            if (empty($name)) {
                $this->skipped++;
                continue;
            }

            Supplier::updateOrCreate(
                ['code' => trim($row['code'] ?? '')],
                [
                    'name'      => $name,
                    'email'     => trim($row['email']     ?? '') ?: null,
                    'phone'     => trim($row['telephone'] ?? '') ?: null,
                    'address'   => trim($row['adresse']   ?? '') ?: null,
                    'city'      => trim($row['ville']     ?? '') ?: null,
                    'country'   => trim($row['pays']      ?? 'Bénin'),
                    'ifu'       => trim($row['ifu']       ?? '') ?: null,
                    'rccm'      => trim($row['rccm']      ?? '') ?: null,
                    'notes'     => trim($row['notes']     ?? '') ?: null,
                    'is_active' => true,
                ]
            );

            $this->imported++;
        }
    }
}
