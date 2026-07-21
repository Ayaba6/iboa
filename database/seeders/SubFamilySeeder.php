<?php

namespace Database\Seeders;

use App\Models\ProductFamily;
use Illuminate\Database\Seeder;

/**
 * [X3 §5] Sous-familles types d'OA METAL INDUSTRIE, rattachées à leur famille
 * parente. Idempotent (updateOrCreate par code). Une famille parente absente
 * est créée au passage (classement commercial pur).
 */
class SubFamilySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'TOLES_BAC' => ['name' => 'Tôles bac', 'children' => [
                'TB_PRELAQ'  => 'Prélaquées',
                'TB_GALVA'   => 'Galvanisées',
                'TB_TRANSL'  => 'Translucides',
                'TB_ONDUL'   => 'Ondulées',
                'TB_ACCESS'  => 'Accessoires',
            ]],
            'FER_BETON' => ['name' => 'Fer à béton', 'children' => [
                'FB_HA'      => 'Fer HA',
                'FB_LISSE'   => 'Fer lisse',
                'FB_TREILLIS' => 'Treillis soudé',
                'FB_FIL'     => 'Fil machine',
            ]],
            'MAT_PREM' => ['name' => 'Matières premières', 'children' => [
                'MP_BOB_PRE' => 'Bobines prélaquées',
                'MP_BOB_GAL' => 'Bobines galvanisées',
                'MP_BILLET'  => 'Billettes',
                'MP_FIL'     => 'Fils machine',
                'MP_CONSO'   => 'Consommables de production',
            ]],
        ];

        foreach ($tree as $code => $def) {
            $parent = ProductFamily::withTrashed()->updateOrCreate(
                ['code' => $code],
                ['name' => $def['name'], 'is_active' => true, 'deleted_at' => null]
            );
            foreach ($def['children'] as $childCode => $childName) {
                ProductFamily::withTrashed()->updateOrCreate(
                    ['code' => $childCode],
                    ['name' => $childName, 'parent_id' => $parent->id, 'is_active' => true, 'deleted_at' => null]
                );
            }
        }
    }
}
