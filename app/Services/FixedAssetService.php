<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FixedAssetService
{
    public function __construct(private DocumentSequenceService $sequenceService) {}

    // ────────────────────────────────────────────────────────────────────────────
    // Création d'une immobilisation + génération du plan d'amortissement
    // ────────────────────────────────────────────────────────────────────────────

    public function create(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            $company = Company::findOrFail(Auth::user()->company_id);

            $data['company_id'] = $company->id;
            $data['code']       = $this->sequenceService->nextNumber($company, 'immobilisation');
            $data['created_by'] = Auth::id();

            $asset = FixedAsset::create($data);

            if ($asset->isDepreciable()) {
                $this->generateSchedule($asset);
            }

            return $asset->fresh(['depreciations']);
        });
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Génère (ou régénère) le plan d'amortissement
    // Ne touche jamais les lignes déjà postées (is_posted = true).
    // ────────────────────────────────────────────────────────────────────────────

    public function generateSchedule(FixedAsset $asset): void
    {
        if (! $asset->isDepreciable()) return;

        // Supprimer uniquement les lignes non postées (les postées restent intactes)
        $asset->depreciations()->where('is_posted', false)->delete();

        $schedule = $this->computeSchedule($asset);

        foreach ($schedule as $row) {
            // Ne pas écraser une ligne déjà postée
            $existing = $asset->depreciations()->where('fiscal_year', $row['fiscal_year'])->first();
            if ($existing && $existing->is_posted) continue;

            FixedAssetDepreciation::updateOrCreate(
                ['fixed_asset_id' => $asset->id, 'fiscal_year' => $row['fiscal_year']],
                [
                    'company_id'             => $asset->company_id,
                    'depreciation_amount'    => $row['depreciation_amount'],
                    'cumulated_depreciation' => $row['cumulated_depreciation'],
                    'net_book_value'         => $row['net_book_value'],
                    'is_posted'              => false,
                ]
            );
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Calcule le tableau d'amortissement (retourne un array, ne persiste pas)
    // ────────────────────────────────────────────────────────────────────────────

    public function computeSchedule(FixedAsset $asset): array
    {
        // Base amortissable = valeur brute (acquisition + frais accessoires) − valeur résiduelle
        $grossValue   = $asset->gross_value;
        $base         = $grossValue - $asset->residual_value;
        $years        = $asset->useful_life_years;
        $method       = $asset->depreciation_method;
        $startDate    = Carbon::instance($asset->commissioning_date);
        $firstYear    = (int) $startDate->format('Y');

        if ($base <= 0 || $years <= 0) return [];

        $rows          = [];
        $cumulTotal    = 0;

        // Prorata temporis 1ère année (jours de commissioning jusqu'à fin d'année / 365)
        $endOfFirstYear    = Carbon::create($firstYear, 12, 31);
        $daysInFirstYear   = $startDate->diffInDays($endOfFirstYear) + 1;
        $prorataFirst      = $daysInFirstYear / 365.0;

        if ($method === 'lineaire') {
            $annualAmount = (int) round($base / $years);

            // Avec prorata la 1ère année, le plan s'étale sur years+1 exercices
            // civils : une dotation annuelle ne dépasse JAMAIS l'annuité linéaire,
            // le complément glisse sur l'exercice suivant.
            for ($i = 0; $cumulTotal < $base && $i <= $years; $i++) {
                $year = $firstYear + $i;

                $amount = ($i === 0)
                    ? (int) round($annualAmount * $prorataFirst)
                    : min($annualAmount, $base - $cumulTotal);

                // Dernier exercice possible : absorber le reliquat d'arrondi
                if ($i === $years) {
                    $amount = $base - $cumulTotal;
                }
                if ($amount <= 0) break;

                $cumulTotal += $amount;
                $rows[] = [
                    'fiscal_year'            => $year,
                    'depreciation_amount'    => $amount,
                    'cumulated_depreciation' => $cumulTotal,
                    // VNC = valeur brute − cumul ; en fin de plan elle vaut la valeur résiduelle
                    'net_book_value'         => max(0, $grossValue - $cumulTotal),
                ];
            }
        } else {
            // Méthode dégressive : taux dégressif = taux linéaire × coefficient SYSCOHADA
            // Coefficients usuels : 3 ans→1.5, 4-5 ans→2, 6+ ans→2.5
            $linearRate = 1 / $years;
            $coeff      = $years <= 3 ? 1.5 : ($years <= 5 ? 2.0 : 2.5);
            $degressiveRate = $linearRate * $coeff;

            $bnc = $base; // base nette comptable (valeur résiduelle comptable pour dégressif)

            for ($i = 0; $i < $years; $i++) {
                $year = $firstYear + $i;

                // Prorata temporis 1ère année
                $prorata = ($i === 0) ? $prorataFirst : 1.0;

                // Bascule vers linéaire quand taux dégressif < taux linéaire restant
                $remainingYears = $years - $i;
                $linearRemRate  = ($remainingYears > 0) ? 1 / $remainingYears : 1;

                $rate   = ($degressiveRate >= $linearRemRate) ? $degressiveRate : $linearRemRate;
                $amount = (int) round($bnc * $rate * $prorata);

                // Dernière année : solde
                if ($i === $years - 1 || $amount >= $bnc) {
                    $amount = (int) $bnc;
                }

                $cumulTotal += $amount;
                $bnc        -= $amount;

                $rows[] = [
                    'fiscal_year'            => $year,
                    'depreciation_amount'    => $amount,
                    'cumulated_depreciation' => $cumulTotal + (int) $asset->depreciations()->where('is_posted', true)->sum('depreciation_amount'),
                    'net_book_value'         => max(0, (int) $bnc + $asset->residual_value),
                ];

                if ($bnc <= 0) break;
            }
        }

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Passer la dotation aux amortissements pour un exercice donné
    // Génère l'écriture GL : DR charge_account / CR depr_account
    // ────────────────────────────────────────────────────────────────────────────

    public function postDepreciation(FixedAssetDepreciation $line): FixedAssetDepreciation
    {
        if ($line->is_posted) {
            throw new \RuntimeException('Cette dotation a déjà été comptabilisée.');
        }
        if ($line->depreciation_amount <= 0) {
            throw new \RuntimeException('Le montant de la dotation est nul.');
        }

        return DB::transaction(function () use ($line) {
            $asset   = $line->fixedAsset;
            $company = Company::findOrFail(Auth::user()->company_id);

            // Résoudre ou créer les comptes GL
            $chargeAccount = $this->resolveAccount($company, $asset->charge_account, 'Dotations aux amortissements', 6, 'charge');
            $deprAccount   = $this->resolveAccount($company, $asset->depr_account,   'Amortissements — ' . $asset->name, 2, 'passif');

            // Journal type OD
            $journalType = JournalType::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'OD'],
                ['name' => 'Opérations diverses', 'type' => 'operations_diverses', 'is_active' => true]
            );

            $number = $this->sequenceService->nextNumber($company, 'ecriture_comptable');

            $entry = JournalEntry::create([
                'company_id'      => $company->id,
                'journal_type_id' => $journalType->id,
                'fiscal_year_id'  => $company->current_fiscal_year_id,
                'number'          => $number,
                'entry_date'      => now()->endOfYear()->format('Y-m-d') <= now()->format('Y-m-d')
                                       ? now()->format('Y-m-d')
                                       : now()->format('Y-m-d'),
                'value_date'      => now()->format('Y-m-d'),
                'reference'       => $asset->code,
                'description'     => 'Dotation amortissement ' . $asset->name . ' — ' . $line->fiscal_year,
                'status'          => 'valide',
                'total_debit'     => $line->depreciation_amount,
                'total_credit'    => $line->depreciation_amount,
                'created_by'      => Auth::id(),
                'validated_by'    => Auth::id(),
                'validated_at'    => now(),
            ]);

            $glLines = [
                ['account_id' => $chargeAccount->id, 'label' => 'Dotation amort. ' . $asset->name . ' ' . $line->fiscal_year, 'debit' => $line->depreciation_amount, 'credit' => 0],
                ['account_id' => $deprAccount->id,   'label' => 'Amort. cumulé ' . $asset->name . ' ' . $line->fiscal_year,   'debit' => 0, 'credit' => $line->depreciation_amount],
            ];

            foreach ($glLines as $i => $gl) {
                $entry->lines()->create(['sort_order' => $i] + $gl);
                Account::where('id', $gl['account_id'])->increment('debit_balance',  $gl['debit']);
                Account::where('id', $gl['account_id'])->increment('credit_balance', $gl['credit']);
            }

            $line->update([
                'journal_entry_id' => $entry->id,
                'is_posted'        => true,
            ]);

            return $line->fresh();
        });
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────────

    private function resolveAccount(Company $company, string $code, string $name, int $classNumber, string $type): Account
    {
        $classId = AccountClass::firstOrCreate(
            ['company_id' => $company->id, 'number' => $classNumber],
            ['name' => 'Classe ' . $classNumber]
        )->id;

        return Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'account_class_id' => $classId,
                'name'             => $name,
                'type'             => $type,
                'is_detail'        => true,
                'is_active'        => true,
                'debit_balance'    => 0,
                'credit_balance'   => 0,
            ]
        );
    }
}
