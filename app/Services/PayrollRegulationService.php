<?php

namespace App\Services;

use App\Models\PayrollBaremeBracket;
use App\Models\PayrollParameterVersion;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des versions réglementaires des paramètres fiscaux et sociaux.
 *
 * Toute modification crée une NOUVELLE version : l'ancienne est archivée
 * (date_fin posée, statut archive) mais reste consultable. Un barème
 * référencé par le snapshot d'une paie validée n'est jamais supprimé.
 */
class PayrollRegulationService
{
    /**
     * Publie une nouvelle version d'un paramètre.
     *
     * @param array $data [code, libelle, valeur, type_valeur, date_debut,
     *                     date_fin?, reference_legale?, commentaire?,
     *                     brackets? [[borne_min, borne_max|null, taux], ...]]
     * @throws \RuntimeException chevauchement ou barème invalide
     */
    public function publish(array $data, string $pays = 'BF'): PayrollParameterVersion
    {
        return DB::transaction(function () use ($data, $pays) {
            $code      = $data['code'];
            $dateDebut = \Carbon\Carbon::parse($data['date_debut'])->startOfDay();
            $dateFin   = isset($data['date_fin']) ? \Carbon\Carbon::parse($data['date_fin'])->endOfDay() : null;

            if ($dateFin && $dateFin->lessThan($dateDebut)) {
                throw new \RuntimeException('La date de fin est antérieure à la date de début.');
            }

            if (! empty($data['brackets'])) {
                $this->assertBracketsValid($data['brackets']);
            }

            // Chevauchement : aucune autre version active ne doit couvrir la période.
            $overlap = PayrollParameterVersion::where('code', $code)
                ->where('pays', $pays)
                ->where('statut', 'actif')
                ->whereDate('date_debut', '<=', $dateFin ?? '9999-12-31')
                ->where(fn($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $dateDebut))
                ->lockForUpdate()
                ->get();

            foreach ($overlap as $other) {
                // Clôturer automatiquement la version courante ouverte (date_fin NULL)
                // à la veille de la nouvelle — c'est le flux normal de succession.
                if ($other->date_fin === null && $other->date_debut->lessThan($dateDebut)) {
                    $other->update([
                        'date_fin'   => $dateDebut->copy()->subDay(),
                        'statut'     => 'archive',
                        'updated_by' => Auth::id(),
                    ]);
                    continue;
                }
                throw new \RuntimeException(
                    "Chevauchement de versions pour {$code} : la version #{$other->version} "
                    . "({$other->date_debut->format('d/m/Y')} → "
                    . ($other->date_fin?->format('d/m/Y') ?? '∞') . ') couvre déjà cette période.'
                );
            }

            $lastVersion = (int) PayrollParameterVersion::where('code', $code)
                ->where('pays', $pays)->max('version');

            $version = PayrollParameterVersion::create([
                'code'             => $code,
                'libelle'          => $data['libelle'],
                'pays'             => $pays,
                'valeur'           => $data['valeur'],
                'type_valeur'      => $data['type_valeur'] ?? 'montant',
                'date_debut'       => $dateDebut,
                'date_fin'         => $dateFin,
                'statut'           => 'actif',
                'version'          => $lastVersion + 1,
                'reference_legale' => $data['reference_legale'] ?? null,
                'commentaire'      => $data['commentaire'] ?? null,
                'created_by'       => Auth::id(),
            ]);

            foreach ($data['brackets'] ?? [] as $i => [$min, $max, $taux]) {
                PayrollBaremeBracket::create([
                    'bareme_id' => $version->id,
                    'borne_min' => $min,
                    'borne_max' => $max,
                    'taux'      => $taux,
                    'ordre'     => $i + 1,
                ]);
            }

            Log::info('[AUDIT] Nouvelle version réglementaire publiée', [
                'code' => $code, 'version' => $version->version,
                'date_debut' => $dateDebut->toDateString(),
                'user_id' => Auth::id(),
            ]);

            return $version->fresh('brackets');
        });
    }

    /**
     * Supprime une version — refusé si elle est référencée par le snapshot
     * d'un run validé ou payé, ou si elle est la version active courante.
     */
    public function delete(PayrollParameterVersion $version): void
    {
        if ($this->isUsedByValidatedRun($version)) {
            throw new \RuntimeException(
                "La version #{$version->version} de {$version->code} est utilisée par une paie "
                . 'validée — suppression interdite. Archivez-la (statut inactif) à la place.'
            );
        }

        DB::transaction(function () use ($version) {
            $version->brackets()->delete();
            $version->delete();
            Log::warning('[AUDIT] Version réglementaire supprimée', [
                'code' => $version->code, 'version' => $version->version, 'user_id' => Auth::id(),
            ]);
        });
    }

    /** Une paie validée/payée référence-t-elle cette version dans son snapshot ? */
    public function isUsedByValidatedRun(PayrollParameterVersion $version): bool
    {
        return PayrollRun::whereIn('status', ['valide', 'paye'])
            ->whereNotNull('calculation_parameters_snapshot')
            ->where('calculation_parameters_snapshot', 'like',
                '%"regulation_version":"' . ($version->valeur['regulation_version'] ?? "BF-{$version->date_debut?->format('Y-m-d')}") . '"%')
            ->exists()
            // Défense large : si le run a été calculé pendant la période de
            // validité de cette version, on considère la version utilisée.
            || PayrollRun::whereIn('status', ['valide', 'paye'])
                ->whereBetween('created_at', [
                    $version->date_debut ?? '1900-01-01',
                    $version->date_fin ?? now()->addCentury(),
                ])->exists();
    }

    /**
     * Valide la cohérence d'un barème : bornes croissantes, sans trou,
     * sans chevauchement, dernière tranche illimitée autorisée (max null).
     *
     * @param array $brackets [[borne_min, borne_max|null, taux], ...]
     */
    public function assertBracketsValid(array $brackets): void
    {
        if (empty($brackets)) {
            throw new \RuntimeException('Un barème doit contenir au moins une tranche.');
        }

        $prev = null;
        foreach (array_values($brackets) as $i => [$min, $max, $taux]) {
            if ($max !== null && $max <= $min) {
                throw new \RuntimeException(
                    'Tranche ' . ($i + 1) . " : la borne maximale ({$max}) doit être supérieure à la borne minimale ({$min})."
                );
            }
            if ($taux < 0 || $taux > 100) {
                throw new \RuntimeException('Tranche ' . ($i + 1) . " : taux invalide ({$taux} %).");
            }
            if ($prev !== null) {
                if ($prev['max'] === null) {
                    throw new \RuntimeException('Une tranche illimitée doit être la dernière du barème.');
                }
                if ($min !== $prev['max'] + 1) {
                    $type = $min <= $prev['max'] ? 'chevauchement' : 'trou';
                    throw new \RuntimeException(
                        'Tranche ' . ($i + 1) . " : {$type} avec la tranche précédente "
                        . "(fin {$prev['max']}, début {$min} — attendu " . ($prev['max'] + 1) . ').'
                    );
                }
            } elseif ($min !== 0) {
                throw new \RuntimeException('La première tranche doit commencer à 0.');
            }
            $prev = ['min' => $min, 'max' => $max];
        }
    }
}
