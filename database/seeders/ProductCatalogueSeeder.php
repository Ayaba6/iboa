<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Catalogue initial IBOA — Tôle bac, Faîtières, Acier, Bobines de tôle, Bobines de fil machine.
 * Idempotent : updateOrCreate sur code_article / firstOrCreate sur code famille et abbreviation unité.
 */
class ProductCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Unités ──────────────────────────────────────────────────────────
        $unitMap = [];
        foreach ([
            ['abbreviation' => 'feuille', 'name' => 'Feuille',          'is_active' => true],
            ['abbreviation' => 'bobine',  'name' => 'Bobine',            'is_active' => true],
            ['abbreviation' => 'rouleau', 'name' => 'Rouleau',           'is_active' => true],
            ['abbreviation' => 'ml',      'name' => 'Mètre linéaire',    'is_active' => true],
            ['abbreviation' => 'barre',   'name' => 'Barre',             'is_active' => true],
            ['abbreviation' => 'kg',      'name' => 'Kilogramme',        'is_active' => true],
            ['abbreviation' => 't',       'name' => 'Tonne',             'is_active' => true],
        ] as $u) {
            $unitMap[$u['abbreviation']] = Unit::firstOrCreate(
                ['abbreviation' => $u['abbreviation']],
                $u
            )->id;
        }

        // ── 2. Familles parentes ───────────────────────────────────────────────
        $pf    = $this->family('PF',    'Produits finis',       null, 0);
        $mp    = $this->family('MP',    'Matières premières',   null, 0);
        $march = $this->family('MARCH', 'Marchandises',         null, 0);

        // ── 3. Familles enfants ────────────────────────────────────────────────
        $toleBac  = $this->family('PF-TOLE-BAC',  'Produits finis — Tôle bac',                 $pf->id,    1, true, true,  true);
        $faitiere = $this->family('PF-FAITIERE',  'Produits finis — Faîtières',                $pf->id,    1, true, false, true);
        $filRecuit= $this->family('PF-FIL-RECUIT','Produits finis — Fil recuit',               $pf->id,    1, true, false, false);
        $bobTole  = $this->family('MP-BOB-TOLE',  'Matières premières — Bobines de tôle',      $mp->id,    1, true, true,  true);
        $bobFil   = $this->family('MP-BOB-FIL',   'Matières premières — Bobines de fil machine',$mp->id,   1, true, true,  false);
        $acier    = $this->family('MARCH-ACIER',  'Marchandises — Acier',                      $march->id, 1, true, false, false);

        // ── 4. Articles ────────────────────────────────────────────────────────

        // ── 4a. Tôle bac (16 produits) — unité : feuille
        $f = $unitMap['feuille'];
        foreach ([
            ['TB-ALZ-030',  'TOLE BAC ALUZINC 30/100',           'Tôle bac ALZ 30/100'],
            ['TB-ALZ-035',  'TOLE BAC ALUZINC 35/100',           'Tôle bac ALZ 35/100'],
            ['TB-GALV-030', 'TOLE BAC GALVA 30/100',             'Tôle bac GALV 30/100'],
            ['TB-GALV-035', 'TOLE BAC GALVA 35/100',             'Tôle bac GALV 35/100'],
            ['TB-PV-030',   'TOLE BAC PRELAQUEE VERTE 30/100',   'Tôle bac préla. verte 30'],
            ['TB-PV-035',   'TOLE BAC PRELAQUEE VERTE 35/100',   'Tôle bac préla. verte 35'],
            ['TB-PV-040',   'TOLE BAC PRELAQUEE VERTE 40/100',   'Tôle bac préla. verte 40'],
            ['TB-PR-030',   'TOLE BAC PRELAQUEE ROUGE 30/100',   'Tôle bac préla. rouge 30'],
            ['TB-PR-035',   'TOLE BAC PRELAQUEE ROUGE 35/100',   'Tôle bac préla. rouge 35'],
            ['TB-PR-040',   'TOLE BAC PRELAQUEE ROUGE 40/100',   'Tôle bac préla. rouge 40'],
            ['TB-PB-030',   'TOLE BAC PRELAQUEE BLEU 30/100',    'Tôle bac préla. bleu 30'],
            ['TB-PB-035',   'TOLE BAC PRELAQUEE BLEU 35/100',    'Tôle bac préla. bleu 35'],
            ['TB-PB-040',   'TOLE BAC PRELAQUEE BLEU 40/100',    'Tôle bac préla. bleu 40'],
            ['TB-PO-030',   'TOLE BAC PRELAQUEE ORANGE 30/100',  'Tôle bac préla. orange 30'],
            ['TB-PO-035',   'TOLE BAC PRELAQUEE ORANGE 35/100',  'Tôle bac préla. orange 35'],
            ['TB-PO-040',   'TOLE BAC PRELAQUEE ORANGE 40/100',  'Tôle bac préla. orange 40'],
        ] as [$code, $name, $court]) {
            $this->product($code, $name, $court, $toleBac->id, 'produit_fini',
                unit: $f, sellable: true, purchasable: false, lot: true, qualite: true);
        }

        // ── 4b. Faîtières (6 produits) — unité : ml
        $ml = $unitMap['ml'];
        foreach ([
            ['FA-ALZ-35',  'FAITIERE ALUZINC DE 35/100 NON ENTAILLEE',      'Faîtière ALZ 35/100'],
            ['FA-GALV-35', 'FAITIERE GALVA DE 35/100 NON ENTAILLEE',         'Faîtière GALV 35/100'],
            ['FA-PV-35',   'FAITIERE PRELAQUEE VERTE DE 35/100 ENTAILLEE',   'Faîtière préla. verte 35'],
            ['FA-PR-35',   'FAITIERE PRELAQUEE ROUGE DE 35/100 ENTAILLEE',   'Faîtière préla. rouge 35'],
            ['FA-PB-35',   'FAITIERE PRELAQUEE BLEU DE 35/100 ENTAILLEE',    'Faîtière préla. bleu 35'],
            ['FA-PO-35',   'FAITIERE PRELAQUEE ORANGE DE 35/100 ENTAILLEE',  'Faîtière préla. orange 35'],
        ] as [$code, $name, $court]) {
            $this->product($code, $name, $court, $faitiere->id, 'produit_fini',
                unit: $ml, sellable: true, purchasable: false, lot: false, qualite: true);
        }

        // ── 4c. Fil recuit (3 produits) — unité : rouleau / kg
        $rlx = $unitMap['rouleau'];
        $kg  = $unitMap['kg'];
        foreach ([
            ['FIL-R-RLX',  'FIL RECUIT /rouleau',    'Fil recuit rouleau', $rlx],
            ['FIL-R-1KG',  'FIL RECUIT DE 1 KG',     'Fil recuit 1 kg',    $kg],
            ['FIL-R-10KG', 'FIL RECUIT DE 10KG',     'Fil recuit 10 kg',   $kg],
        ] as [$code, $name, $court, $unit]) {
            $this->product($code, $name, $court, $filRecuit->id, 'produit_fini',
                unit: $unit, sellable: true, purchasable: false, lot: false, qualite: false);
        }

        // ── 4d. Fer à béton (5 produits) — unité : barre
        $barre = $unitMap['barre'];
        foreach ([
            ['FAB-06', 'FER A BETON NORMALISE DIAM 6 ( 375 b/T ) HA FEE 500 +',  'Fer béton Ø6 mm'],
            ['FAB-08', 'FER A BETON NORMALISE DIAM 8 ( 210 b/T ) HA FEE 500 +',  'Fer béton Ø8 mm'],
            ['FAB-10', 'FER A BETON NORMALISE DIAM 10 ( 135 b/T ) HA FEE 500 +', 'Fer béton Ø10 mm'],
            ['FAB-12', 'FER A BETON NORMALISE DIAM 12 ( 93 b/T ) HA FEE 500 +',  'Fer béton Ø12 mm'],
            ['FAB-14', 'FER A BETON NORMALISE DIAM 14 ( 68 b/T ) HA FEE 500 +',  'Fer béton Ø14 mm'],
        ] as [$code, $name, $court]) {
            $this->product($code, $name, $court, $acier->id, 'marchandise',
                unit: $barre, sellable: true, purchasable: true, lot: false, qualite: false);
        }

        // ── 4e. Fer d'attache — unité : kg
        $this->product('FER-ATT', "FER D'ATTACHE", "Fer d'attache", $acier->id, 'marchandise',
            unit: $kg, sellable: true, purchasable: true, lot: false, qualite: false);

        // ── 4f. Tubes rectangulaires (6 produits) — unité : ml
        foreach ([
            ['TUB-40x80-L',    'TUBE RECTANGULAIRE 40 X 80 LOURD',              'Tube 40x80 lourd'],
            ['TUB-40x80',      'TUBE RECTANGULAIRE 40 X 80',                     'Tube 40x80'],
            ['TUB-40x80-1',    'TUBE RECTANGULAIRE 40 X 80 X 1 MM',             'Tube 40x80 ép. 1mm'],
            ['TUB-40x80-12L',  'TUBE RECTANGULAIRE 40 X 80 LOURD 1.2 MM',       'Tube 40x80 lourd 1.2mm'],
            ['TUB-40x80-15L',  'TUBE RECTANGULAIRE 40 X 80 MM LOURD 1.5 MM',    'Tube 40x80 lourd 1.5mm'],
            ['TUB-GALV-12L',   'TUBE RECTANGULAIRE GALVA 40 X 80 LOURD 1.2 MM', 'Tube GALV 40x80 1.2mm'],
        ] as [$code, $name, $court]) {
            $this->product($code, $name, $court, $acier->id, 'marchandise',
                unit: $ml, sellable: true, purchasable: true, lot: false, qualite: false);
        }

        // ── 4g. Bobines de tôle (18 produits) — unité : bobine
        $bob = $unitMap['bobine'];
        foreach ([
            // Aluzinc
            ['BOB-ALZ-025',   'BOBINE DE TOLES ALUZINC 0.25 mm',          'Bobine ALZ 0.25mm'],
            ['BOB-ALZ-027',   'BOBINE DE TOLES ALUZINC 0.27 mm',          'Bobine ALZ 0.27mm'],
            ['BOB-ALZ-030',   'BOBINE DE TOLES ALUZINC 0.30 mm',          'Bobine ALZ 0.30mm'],
            // Galva
            ['BOB-GALV-025',  'BOBINE DE TOLE GALVA 0.25 mm',             'Bobine GALV 0.25mm'],
            ['BOB-GALV-027',  'BOBINE DE TOLE GALVA 0.27 mm',             'Bobine GALV 0.27mm'],
            ['BOB-GALV-030',  'BOBINE DE TOLE GALVA 0.30 mm',             'Bobine GALV 0.30mm'],
            // Prélaquée Bleue
            ['BOB-PB-025',    'BOBINE DE TOLE PRELAQUE BLEUE 0.25 mm',    'Bobine préla. bleue 0.25mm'],
            ['BOB-PB-027',    'BOBINE DE TOLE PRELAQUE BLEUE 0.27 mm',    'Bobine préla. bleue 0.27mm'],
            ['BOB-PB-030',    'BOBINE DE TOLE PRELAQUE BLEUE 0.30 mm',    'Bobine préla. bleue 0.30mm'],
            // Prélaquée Orange
            ['BOB-PO-025',    'BOBINE DE TOLE PRELAQUE ORANGE 0.25 mm',   'Bobine préla. orange 0.25mm'],
            ['BOB-PO-027',    'BOBINE DE TOLE PRELAQUE ORANGE 0.27 mm',   'Bobine préla. orange 0.27mm'],
            ['BOB-PO-030',    'BOBINE DE TOLE PRELAQUE ORANGE 0.30 mm',   'Bobine préla. orange 0.30mm'],
            // Prélaquée Rouge
            ['BOB-PR-025',    'BOBINE DE TOLE PRELAQUE ROUGE 0.25 mm',    'Bobine préla. rouge 0.25mm'],
            ['BOB-PR-027',    'BOBINE DE TOLE PRELAQUE ROUGE 0.27 mm',    'Bobine préla. rouge 0.27mm'],
            ['BOB-PR-030',    'BOBINE DE TOLE PRELAQUE ROUGE 0.30 mm',    'Bobine préla. rouge 0.30mm'],
            // Prélaquée Verte Foncée
            ['BOB-PVF-025',   'BOBINE DE TOLE PRELAQUE VERTE FONCE 0.25 mm', 'Bobine préla. verte 0.25mm'],
            ['BOB-PVF-027',   'BOBINE DE TOLE PRELAQUE VERTE FONCE 0.27 mm', 'Bobine préla. verte 0.27mm'],
            ['BOB-PVF-030',   'BOBINE DE TOLE PRELAQUE VERTE FONCE 0.30 mm', 'Bobine préla. verte 0.30mm'],
        ] as [$code, $name, $court]) {
            $this->product($code, $name, $court, $bobTole->id, 'matiere_premiere',
                unit: $bob, sellable: false, purchasable: true, lot: true, qualite: true);
        }

        // ── 4h. Bobines de fil machine (18 produits) — unité : bobine (tonne)
        $t = $unitMap['t'];
        foreach ([
            ['BOB-FM-60',  'BOBINE DE FIL MACHINE 6 mm',    'Fil machine Ø6mm'],
            ['BOB-FM-65',  'BOBINE DE FIL MACHINE 6.5 mm',  'Fil machine Ø6.5mm'],
            ['BOB-FM-70',  'BOBINE DE FIL MACHINE 7 mm',    'Fil machine Ø7mm'],
            ['BOB-FM-75',  'BOBINE DE FIL MACHINE 7.5 mm',  'Fil machine Ø7.5mm'],
            ['BOB-FM-80',  'BOBINE DE FIL MACHINE 8 mm',    'Fil machine Ø8mm'],
            ['BOB-FM-85',  'BOBINE DE FIL MACHINE 8.5 mm',  'Fil machine Ø8.5mm'],
            ['BOB-FM-90',  'BOBINE DE FIL MACHINE 9 mm',    'Fil machine Ø9mm'],
            ['BOB-FM-95',  'BOBINE DE FIL MACHINE 9.5 mm',  'Fil machine Ø9.5mm'],
            ['BOB-FM-100', 'BOBINE DE FIL MACHINE 10 mm',   'Fil machine Ø10mm'],
            ['BOB-FM-105', 'BOBINE DE FIL MACHINE 10.5 mm', 'Fil machine Ø10.5mm'],
            ['BOB-FM-110', 'BOBINE DE FIL MACHINE 11 mm',   'Fil machine Ø11mm'],
            ['BOB-FM-115', 'BOBINE DE FIL MACHINE 11.5 mm', 'Fil machine Ø11.5mm'],
            ['BOB-FM-120', 'BOBINE DE FIL MACHINE 12 mm',   'Fil machine Ø12mm'],
            ['BOB-FM-125', 'BOBINE DE FIL MACHINE 12.5 mm', 'Fil machine Ø12.5mm'],
            ['BOB-FM-130', 'BOBINE DE FIL MACHINE 13 mm',   'Fil machine Ø13mm'],
            ['BOB-FM-135', 'BOBINE DE FIL MACHINE 13.5 mm', 'Fil machine Ø13.5mm'],
            ['BOB-FM-140', 'BOBINE DE FIL MACHINE 14 mm',   'Fil machine Ø14mm'],
            ['BOB-FM-145', 'BOBINE DE FIL MACHINE 14.5 mm', 'Fil machine Ø14.5mm'],
        ] as [$code, $name, $court]) {
            $this->product($code, $name, $court, $bobFil->id, 'matiere_premiere',
                unit: $t, sellable: false, purchasable: true, lot: true, qualite: false);
        }

        $this->command->info('Catalogue IBOA seedé : 8 familles, 73 articles.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function family(
        string $code, string $name, ?int $parentId, int $depth,
        bool $gestionStock = false, bool $gestionLot = false, bool $controleQualite = false
    ): ProductFamily {
        return ProductFamily::firstOrCreate(
            ['code' => $code],
            [
                'name'             => $name,
                'parent_id'        => $parentId,
                'depth'            => $depth,
                'is_active'        => true,
                'gestion_stock'    => $gestionStock,
                'gestion_lot'      => $gestionLot,
                'controle_qualite' => $controleQualite,
            ]
        );
    }

    private function product(
        string $code, string $name, string $court, int $familyId, string $typeArticle,
        int $unit, bool $sellable, bool $purchasable, bool $lot, bool $qualite
    ): Product {
        return Product::updateOrCreate(
            ['code_article' => $code],
            [
                'reference'         => $code,
                'name'              => $name,
                'designation_courte'=> $court,
                'family_id'         => $familyId,
                'type_article'      => $typeArticle,
                'type'              => 'simple',
                'statut'            => 'actif',
                'is_active'         => true,
                'is_stockable'      => true,
                'is_sellable'       => $sellable,
                'is_purchasable'    => $purchasable,
                'has_lot_number'    => $lot,
                'controle_qualite'  => $qualite,
                'unit_id'           => $unit,
                'sale_unit_id'      => $unit,
                'purchase_unit_id'  => $unit,
            ]
        );
    }
}
