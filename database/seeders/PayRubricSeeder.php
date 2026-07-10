<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayRubric;
use Illuminate\Database\Seeder;

/**
 * [P1] Rubriques de paie standard — inspiré Sage Paie & RH.
 * Contexte Burkina Faso / Afrique francophone.
 *
 * Champ is_iuts_base ajouté (distinct de is_taxable et is_cnss_base) :
 *   - is_cnss_base  : inclus dans la base cotisable CNSS
 *   - is_iuts_base  : inclus dans la base imposable IUTS/ITS
 *   - is_taxable    : alias lisible, doit rester = is_iuts_base pour les gains
 */
class PayRubricSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command->warn('Aucune entreprise trouvée.');
            return;
        }

        $rubrics = [
            // ─────────────────────────────────────────────────────────────────
            // GAINS — Salaire et compléments
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'SAL_BASE',
                'libelle'       => 'Salaire de base',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_iuts_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 10,
                'description'   => 'Rémunération mensuelle de base selon le contrat de travail.',
            ],
            [
                'code'          => 'SAL_COMP',
                'libelle'       => 'Complément de salaire',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_iuts_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 15,
                'description'   => 'Complément ponctuel de salaire.',
            ],
            // ─────────────────────────────────────────────────────────────────
            // GAINS — Heures supplémentaires
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'HS_25',
                'libelle'       => 'Heures supplémentaires 25 %',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_iuts_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 20,
                'description'   => 'HS effectuées en semaine (majoration 25 %).',
            ],
            [
                'code'          => 'HS_50',
                'libelle'       => 'Heures supplémentaires 50 %',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 21,
                'description'   => 'HS le week-end ou jours fériés (majoration 50 %).',
            ],
            [
                'code'          => 'HS_NUIT',
                'libelle'       => 'Heures supplémentaires de nuit',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 22,
                'description'   => 'HS de nuit (majoration 75 %).',
            ],
            // ─────────────────────────────────────────────────────────────────
            // GAINS — Primes imposables
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'PRIME_RESP',
                'libelle'       => 'Prime de responsabilité',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 30,
            ],
            [
                'code'          => 'PRIME_PERF',
                'libelle'       => 'Prime de performance',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 31,
            ],
            [
                'code'          => 'PRIME_ANCIE',
                'libelle'       => 'Prime d\'ancienneté',
                'type'          => 'gain',
                'calc_type'     => 'taux',
                'base_ref'      => 'salaire_base',
                'rate'          => 3.00,
                'is_taxable'    => true,
                'is_cnss_base'  => true,
                'is_in_brut'    => true,
                'display_order' => 32,
                'description'   => '3 % du salaire de base (à ajuster selon la convention collective).',
            ],
            [
                'code'          => 'PRIME_EXCEPT',
                'libelle'       => 'Prime exceptionnelle',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => true,
                'is_cnss_base'  => false,
                'is_in_brut'    => true,
                'display_order' => 35,
            ],
            // ─────────────────────────────────────────────────────────────────
            // GAINS — Indemnités non imposables
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'PRIME_TRANSP',
                'libelle'       => 'Prime de transport',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 40,
                'description'   => 'Indemnité de transport non imposable.',
            ],
            [
                'code'          => 'IND_LOGEMENT',
                'libelle'       => 'Indemnité de logement',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 41,
            ],
            [
                'code'          => 'IND_REPAS',
                'libelle'       => 'Indemnité de repas',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 42,
            ],
            [
                'code'          => 'IND_DEPLAC',
                'libelle'       => 'Frais de déplacement',
                'type'          => 'gain',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 43,
            ],
            // ─────────────────────────────────────────────────────────────────
            // RETENUES — Absences
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'RET_ABS',
                'libelle'       => 'Retenue pour absence',
                'type'          => 'retenue',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 50,
                'description'   => 'Déduction pour absences injustifiées ou non payées.',
            ],
            // ─────────────────────────────────────────────────────────────────
            // RETENUES — Cotisations sociales
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'CNSS_SAL',
                'libelle'       => 'CNSS Salarié',
                'type'          => 'retenue',
                'calc_type'     => 'taux',
                'base_ref'      => 'cnss_base',
                'rate'          => 5.50,
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 60,
                'description'   => '5,5 % du salaire brut plafonné à 650 000 FCFA.',
            ],
            // ─────────────────────────────────────────────────────────────────
            // RETENUES — Fiscales
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'IUTS',
                'libelle'       => 'IUTS (Impôt sur traitements et salaires)',
                'type'          => 'retenue',
                'calc_type'     => 'formule',
                'formula'       => '0', // Calculé par le moteur, valeur stockée dans payroll_items
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 65,
                'description'   => 'Calculé par barème progressif + quotient familial.',
            ],
            // ─────────────────────────────────────────────────────────────────
            // RETENUES — Avances et prêts
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'AVANCE',
                'libelle'       => 'Avance sur salaire',
                'type'          => 'retenue',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 70,
            ],
            [
                'code'          => 'PRET',
                'libelle'       => 'Remboursement prêt',
                'type'          => 'retenue',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 71,
            ],
            [
                'code'          => 'AUTRES_RET',
                'libelle'       => 'Autres retenues',
                'type'          => 'retenue',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 79,
            ],
            // ─────────────────────────────────────────────────────────────────
            // COTISATIONS PATRONALES (n'apparaissent pas sur le bulletin individuel)
            // ─────────────────────────────────────────────────────────────────
            [
                'code'              => 'CNSS_PAT',
                'libelle'           => 'CNSS Patronal',
                'type'              => 'cotisation_pat',
                'calc_type'         => 'taux',
                'base_ref'          => 'cnss_base',
                'rate'              => 16.00,
                'is_taxable'        => false,
                'is_cnss_base'      => false,
                'is_in_brut'        => false,
                'show_on_bulletin'  => false,
                'display_order'     => 80,
                'description'       => '16 % du salaire brut plafonné (AT/MP inclus = 3,5 %).',
            ],
            // ─────────────────────────────────────────────────────────────────
            // TOTAUX (rubriques d'information)
            // ─────────────────────────────────────────────────────────────────
            [
                'code'          => 'BRUT',
                'libelle'       => 'Salaire brut',
                'type'          => 'information',
                'calc_type'     => 'formule',
                'formula'       => '0',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 90,
            ],
            [
                'code'          => 'NET_PAYE',
                'libelle'       => 'Net à payer',
                'type'          => 'information',
                'calc_type'     => 'formule',
                'formula'       => '0',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 99,
            ],
        ];

        // Ajouter les rubriques manquantes dans le catalogue existant
        $additional = [
            [
                'code'             => 'AT_MP',
                'libelle'          => 'Cotisation AT/MP patronale',
                'description'      => 'Accidents du travail & maladies professionnelles — 3,5 % du brut',
                'type'             => 'cotisation_pat',
                'calc_type'        => 'taux',
                'base_ref'         => 'salaire_brut',
                'rate'             => 3.50,
                'is_taxable'       => false,
                'is_cnss_base'     => false,
                'is_iuts_base'     => false,
                'is_in_brut'       => false,
                'show_on_bulletin' => false,
                'display_order'    => 81,
            ],
            [
                'code'          => 'RET_PRET',
                'libelle'       => 'Remboursement prêt salarié',
                'description'   => 'Mensualité déduite automatiquement par le moteur de paie',
                'type'          => 'retenue',
                'calc_type'     => 'manuel',
                'is_taxable'    => false,
                'is_cnss_base'  => false,
                'is_iuts_base'  => false,
                'is_in_brut'    => false,
                'display_order' => 72,
            ],
        ];

        // Comptes SYSCOHADA par rubrique (évite le risque GL — rubriques sans compte).
        $accounts = [
            '661' => ['SAL_BASE', 'SAL_COMP', 'HS_25', 'HS_50', 'HS_NUIT', 'PRIME_ANCIE', 'PRIME_EXCEPT', 'PRIME_PERF', 'PRIME_RESP', 'RET_ABS', 'BRUT'],
            '663' => ['IND_DEPLAC', 'IND_LOGEMENT', 'IND_REPAS', 'PRIME_TRANSP'],
            '664' => ['AT_MP', 'CNSS_PAT'],
            '431' => ['CNSS_SAL'],
            '447' => ['IUTS'],
            '421' => ['AVANCE', 'PRET', 'RET_PRET'],
            '428' => ['AUTRES_RET'],
            '422' => ['NET_PAYE'],
        ];
        $codeToAccount = [];
        foreach ($accounts as $acct => $codes) {
            foreach ($codes as $c) {
                $codeToAccount[$c] = $acct;
            }
        }

        $created = 0;
        foreach (array_merge($rubrics, $additional) as $data) {
            // Défaut is_iuts_base = is_taxable si non fourni (compatibilité)
            $data['is_iuts_base'] ??= $data['is_taxable'];
            $data['company_id'] = $company->id;
            // Compte comptable SYSCOHADA si non explicitement fourni.
            $data['account_code'] ??= $codeToAccount[$data['code']] ?? null;

            PayRubric::updateOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                $data
            );
            $created++;
        }

        $this->command->info("✓ {$created} rubriques de paie créées/mises à jour pour : {$company->name}");
    }
}
