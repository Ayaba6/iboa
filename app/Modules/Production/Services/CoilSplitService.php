<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\Coil;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [RÈGLE A — division physique d'une bobine] Une bobine physique est une unité
 * qualité INDIVISIBLE. Traiter séparément des portions exige une opération
 * PHYSIQUE réelle (découpe / refendage) créant des bobines FILLES traçables.
 *
 * PRÉSERVATION DE L'HISTORIQUE (règle absolue) :
 *   « division physique ≠ suppression de l'historique »
 *   - `quality_status` de la mère est CONSERVÉ (statut certifié avant division)
 *     et figé dans `quality_status_before_transformation` ;
 *   - `initial_weight` (poids reçu) et le coût historique (`cost_per_kg`,
 *     `purchase_price`) ne sont JAMAIS modifiés ;
 *   - seuls les SOLDES ACTIFS tombent à zéro (remaining_weight, qty_*), la mère
 *     devenant non consommable / non réservable ;
 *   - `transferred_to_children_qty` trace la matière passée aux filles.
 *
 * Réconciliations imposées :
 *   quantité : poids divisible mère = Σ poids filles + chutes + pertes (± tolérance)
 *   valeur   : coût historique mère = Σ coûts filles + valeur chutes + valeur
 *              pertes + écart d'arrondi documenté
 *
 * Chaque division produit un DOCUMENT append-only (coil_split_operations +
 * items) : jamais une simple relation parent_coil_id.
 */
class CoilSplitService
{
    /** Tolérance de pesée par défaut (kg) — paramétrable par appel. */
    public const DEFAULT_WEIGHING_TOLERANCE = 0.001;

    /**
     * @param  array<int,array{weight:float,quality_status?:string,reference?:string}>  $children
     * @param  array{scrap?:float,loss?:float,tolerance?:float,reason?:string,idempotency_key?:string,requires_post_split_qc?:bool}  $opts
     * @return array<int,Coil>
     *
     * @throws \RuntimeException
     */
    public function split(Coil $mother, array $children, float $scrap = 0.0, ?string $reason = null, array $opts = []): array
    {
        if ($children === []) {
            throw new \RuntimeException('Division de bobine : au moins une bobine fille est requise.');
        }

        return DB::transaction(function () use ($mother, $children, $scrap, $reason, $opts) {
            $mother = Coil::lockForUpdate()->findOrFail($mother->id);

            // [#13] Idempotence AVANT les gardes : un rejeu de la même opération
            // doit renvoyer les mêmes enfants, pas buter sur « déjà divisée ».
            $key = trim((string) ($opts['idempotency_key'] ?? ''));
            if ($key !== '') {
                $existing = DB::table('coil_split_operations')
                    ->where('coil_id', $mother->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    // Même clé + contenu DIFFÉRENT → refus métier explicite.
                    $replayHash = self::canonicalHash(
                        $mother, (float) $existing->mother_qty_before, $children,
                        (float) ($opts['scrap'] ?? $scrap), (float) ($opts['loss'] ?? 0),
                        (float) ($opts['tolerance'] ?? self::DEFAULT_WEIGHING_TOLERANCE)
                    );
                    if ($existing->calculation_hash !== null && $existing->calculation_hash !== $replayHash) {
                        throw new \RuntimeException(
                            'Clé d\'idempotence de division réutilisée avec un contenu différent — refus.'
                        );
                    }

                    return Coil::where('parent_coil_id', $mother->id)->orderBy('id')->get()->all();
                }
            }

            // [#8] Gardes d'opérations incompatibles.
            if ($mother->isSplit() || $mother->transformation_status === Coil::TRANSFO_TRANSFORMED) {
                throw new \RuntimeException("Bobine {$mother->reference} : déjà divisée ou transformée.");
            }
            if (Coil::where('parent_coil_id', $mother->id)->exists()) {
                throw new \RuntimeException("Bobine {$mother->reference} : des bobines filles existent déjà.");
            }
            if (in_array($mother->quality_status, [
                Coil::QUALITY_RETURN_PENDING, Coil::QUALITY_RETURNED, Coil::QUALITY_CANCELLED,
            ], true)) {
                throw new \RuntimeException(sprintf(
                    'Bobine %s : statut qualité « %s » — division interdite.',
                    $mother->reference, $mother->quality_status
                ));
            }
            // [#6] Bobine REFUSÉE : division en filles utilisables interdite sans
            // contre-décision / requalification approuvée.
            if ($mother->quality_status === Coil::QUALITY_REJECTED) {
                throw new \RuntimeException(sprintf(
                    'Bobine %s : refusée — la division en bobines utilisables exige une '
                    . 'contre-décision ou une requalification approuvée.',
                    $mother->reference
                ));
            }

            // [#10] Seul le SOLDE physique restant est divisible (jamais la
            // quantité historique totale déjà partiellement consommée).
            $divisible = (float) $mother->remaining_weight;
            if ($divisible <= 0) {
                throw new \RuntimeException("Bobine {$mother->reference} : aucun solde divisible (entièrement consommée).");
            }

            $tolerance = (float) ($opts['tolerance'] ?? self::DEFAULT_WEIGHING_TOLERANCE);
            $loss      = (float) ($opts['loss'] ?? 0);

            // [#8] Permission d'EXÉCUTION de la division.
            $this->assertCan('coils.split.execute', 'exécuter une division de bobine');

            // [#8] Perte au-delà du seuil : permission dédiée + maker-checker
            // (l'exécutant ne peut pas approuver seul sa propre perte).
            $lossValue = (int) round($loss * (float) $mother->cost_per_kg);
            $seuilVal  = (int) config('security.maker_checker.coil_split.loss_value_threshold', 50000);
            $seuilQty  = (float) config('security.maker_checker.coil_split.loss_qty_threshold', 50);
            // `approved` : l'approbation a déjà été tracée sur une PROPOSITION
            // persistée (CoilSplitProposalService) — on ne redemande pas ici.
            $alreadyApproved = (bool) ($opts['approved'] ?? false);
            if (! $alreadyApproved && $loss > 0 && ($lossValue > $seuilVal || $loss > $seuilQty)) {
                $this->assertCan('coils.split.approve_loss', sprintf(
                    'approuver une perte de %s kg (%s FCFA) sur la division de la bobine %s',
                    $loss, $lossValue, $mother->reference
                ));
                app(\App\Services\MakerCheckerService::class)->assert(
                    $opts['proposed_by'] ?? null,
                    'coil_split.approve_loss',
                    sprintf('la perte de division de la bobine %s', $mother->reference),
                    $mother
                );
            }

            // ── Réconciliation QUANTITÉ ──────────────────────────────────────
            $sum = 0.0;
            foreach ($children as $c) {
                $w = (float) ($c['weight'] ?? 0);
                if ($w <= 0) {
                    throw new \RuntimeException('Division : poids de bobine fille nul ou négatif refusé.');
                }
                $sum += $w;
            }
            $ecart = ($sum + $scrap + $loss) - $divisible;
            if (abs($ecart) > $tolerance) {
                throw new \RuntimeException(sprintf(
                    'Division de bobine %s : Σ filles (%s) + chutes (%s) + pertes (%s) = %s ≠ poids divisible mère (%s), '
                    . 'écart %s au-delà de la tolérance de pesée (%s).',
                    $mother->reference, $sum, $scrap, $loss, $sum + $scrap + $loss, $divisible, round($ecart, 4), $tolerance
                ));
            }

            // ── Politique d'HÉRITAGE qualité des filles [#6] ─────────────────
            $requiresQc = (bool) ($opts['requires_post_split_qc'] ?? false);

            // ── Snapshot AVANT division + valeur RÉSIDUELLE [#3/#5] ──────────
            // Seule la valeur NON ENCORE CONSOMMÉE est répartissable : répartir le
            // coût historique total redistribuerait une valeur déjà passée en
            // consommation. Coût historique de la bobine uniquement — jamais le
            // CMP courant, ni le dernier prix d'achat.
            $costPerKg       = (float) $mother->cost_per_kg;
            $historicalCost  = (int) $mother->purchase_price;
            $initialWeight   = (float) $mother->initial_weight;
            $consumedBefore  = max(0.0, $initialWeight - $divisible);
            $consumedCost    = (int) round($consumedBefore * $costPerKg);
            $residualCost    = (int) round($divisible * $costPerKg);

            $operationId = DB::table('coil_split_operations')->insertGetId([
                'mother_initial_weight'      => $initialWeight,
                'consumed_before_split'      => $consumedBefore,
                'returned_before_split'      => (float) ($mother->qty_returned ?? 0),
                'released_before_split'      => (float) ($mother->qty_released ?? 0),
                'quarantine_before_split'    => (float) ($mother->qty_quarantine ?? 0),
                'residual_cost_before_split' => $residualCost,
                'consumed_cost_before_split' => $consumedCost,
                'warehouse_before_split'     => $mother->warehouse_id,
                'company_id'                   => $mother->company_id,
                'coil_id'                      => $mother->id,
                // Référence bornée : le numéro reste sous la limite de colonne
                // (MySQL rejette au-delà, SQLite tronquerait silencieusement).
                'number'                       => 'DIV-' . mb_substr((string) $mother->reference, 0, 50) . '-' . now()->format('YmdHis'),
                'mother_qty_before'            => $divisible,
                'mother_quality_status_before' => $mother->quality_status,
                'mother_cost_per_kg'           => $costPerKg,
                'mother_historical_cost'       => $historicalCost,
                'allocation_method'            => 'proportion_poids',
                'weighing_tolerance'           => $tolerance,
                'scrap_qty'                    => $scrap,
                'loss_qty'                     => $loss,
                'scrap_value'                  => (int) round($scrap * $costPerKg),
                'loss_value'                   => (int) round($loss * $costPerKg),
                'requires_post_split_quality_control' => $requiresQc,
                'reason'                       => $reason,
                'created_by'                   => Auth::id(),
                'idempotency_key'              => $key !== '' ? $key : null,
                'calculation_hash'             => self::canonicalHash($mother, $divisible, $children, $scrap, $loss, $tolerance),
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ]);

            // ── Création des filles ──────────────────────────────────────────
            $created = [];
            $i = 0;
            $transferredCost = 0;
            foreach ($children as $c) {
                $weight = (float) $c['weight'];
                $i++;
                $status = $this->childQualityStatus($mother, $c['quality_status'] ?? null, $requiresQc);
                // Coût transféré au prorata du poids (méthode figée sur l'opération).
                $childCost = (int) round($weight * $costPerKg);
                $transferredCost += $childCost;

                $child = Coil::create([
                    'company_id'       => $mother->company_id,
                    'product_id'       => $mother->product_id,
                    'supplier_id'      => $mother->supplier_id,
                    'reception_id'     => $mother->reception_id,
                    'warehouse_id'     => $mother->warehouse_id,
                    'stock_lot_id'     => $mother->stock_lot_id,
                    'parent_coil_id'   => $mother->id,
                    'reference'        => $c['reference'] ?? ($mother->reference . '-' . $i),
                    'lot_number'       => $mother->lot_number,
                    'initial_weight'   => $weight,
                    'remaining_weight' => $weight,
                    // Coût TRANSFÉRÉ de la mère — jamais recalculé au CMP courant.
                    'cost_per_kg'      => $mother->cost_per_kg,
                    'purchase_price'   => $childCost,
                    'received_at'      => $mother->received_at,
                    'status'           => 'disponible',
                    'quality_status'   => $status,
                    'transformation_status' => Coil::TRANSFO_INTACT,
                    'qty_released'     => $status === Coil::QUALITY_RELEASED ? $weight : 0,
                    'qty_quarantine'   => $status === Coil::QUALITY_QUARANTINED ? $weight : 0,
                    'qty_rejected'     => $status === Coil::QUALITY_REJECTED ? $weight : 0,
                    'created_by'       => Auth::id(),
                ]);
                $created[] = $child;

                DB::table('coil_split_operation_items')->insert([
                    'split_operation_id'  => $operationId,
                    'child_coil_id'       => $child->id,
                    'weight'              => $weight,
                    'transferred_cost'    => $childCost,
                    'quality_disposition' => $status,
                    'warehouse_id'        => $child->warehouse_id,
                    'sort_order'          => $i,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            // ── Réconciliation VALEUR sur le RÉSIDUEL [#5] ───────────────────
            // valeur résiduelle = Σ coûts filles + chutes + nouvelles pertes + arrondi
            // (et NON le coût historique total : la part déjà consommée n'est
            //  jamais redistribuée aux filles).
            $scrapValue = (int) round($scrap * $costPerKg);
            $lossValue  = (int) round($loss * $costPerKg);
            $rounding   = $residualCost - ($transferredCost + $scrapValue + $lossValue);
            DB::table('coil_split_operations')->where('id', $operationId)
                ->update(['rounding_difference' => $rounding, 'transferred_cost' => $transferredCost]);

            // ── Clôture LOGISTIQUE de la mère — historique PRÉSERVÉ ──────────
            $mother->update([
                'transformation_status'                => Coil::TRANSFO_SPLIT,
                // statut qualité CONSERVÉ + figé explicitement
                'quality_status_before_transformation' => $mother->quality_status,
                'transferred_to_children_qty'          => $sum,
                'transformed_at'                       => now(),
                'transformed_by'                       => Auth::id(),
                // soldes ACTIFS à zéro (la matière est passée aux filles)
                'remaining_weight'                     => 0,
                'qty_quarantine'                       => 0,
                'qty_released'                         => 0,
                'status'                               => 'epuisee',
                // initial_weight, cost_per_kg, purchase_price : INCHANGÉS
                'notes' => trim(($mother->notes ?? '') . "\n" . sprintf(
                    '[DIVISION %s] %d fille(s), chutes %s, pertes %s. %s',
                    now()->format('d/m/Y H:i'), count($created), $scrap, $loss, $reason ?? ''
                )),
            ]);

            app(AuditService::class)->log('bobine.division', $mother, [], [
                'operation'        => $operationId,
                'qte_divisible'    => $divisible,
                'filles'           => array_map(fn ($c) => ['ref' => $c->reference, 'poids' => (float) $c->initial_weight, 'qualite' => $c->quality_status], $created),
                'chutes'           => $scrap,
                'pertes'           => $loss,
                'cout_transfere'   => $transferredCost,
                'ecart_arrondi'    => $rounding,
                'statut_avant'     => $mother->quality_status_before_transformation,
                'motif'            => $reason,
            ]);

            return $created;
        });
    }

    /**
     * [#8] Garde de permission — silencieuse si aucun utilisateur authentifié
     * (jobs, commandes artisan, imports système).
     */
    private function assertCan(string $permission, string $action): void
    {
        // [#1] Délégué au contexte d'exécution : l'absence d'utilisateur n'autorise
        // RIEN — un traitement automatique doit déclarer un acteur système autorisé
        // (ExecutionContext::asSystem), sinon l'exécution est refusée.
        \App\Services\ExecutionContext::assertCan($permission, $action);
    }

    /**
     * [#7] Empreinte CANONIQUE et STABLE de l'opération de division.
     *
     * Contraintes respectées :
     *  - ordre des champs FIXE (aucune dépendance à l'ordre des clés d'un tableau) ;
     *  - décimales NORMALISÉES (number_format à précision fixe) — jamais de float
     *    brut dont la représentation varie ;
     *  - enfants sérialisés dans l'ORDRE de saisie, champs figés ;
     *  - valeurs nulles représentées de façon stable ('~') ;
     *  - encodage UTF-8, aucun horodatage (variable selon l'environnement).
     *
     * Recalculable à l'identique sous SQLite et MySQL.
     */
    public static function canonicalHash(Coil $mother, float $divisible, array $children, float $scrap, float $loss, float $tolerance): string
    {
        $n = fn ($v) => number_format((float) $v, 3, '.', '');
        $s = fn ($v) => $v === null || $v === '' ? '~' : (string) $v;

        $parts = [
            'v1',                                   // version d'algorithme
            'mother:' . $mother->id,
            'ref:' . $s($mother->reference),
            'qty_before:' . $n($divisible),
            'quality_before:' . $s($mother->quality_status),
            'cost_per_kg:' . $n($mother->cost_per_kg),
            'historical_cost:' . $n($mother->purchase_price),
            'method:proportion_poids',
            'tolerance:' . $n($tolerance),
            'scrap:' . $n($scrap),
            'loss:' . $n($loss),
        ];
        // Enfants dans l'ordre de saisie, champs figés.
        $i = 0;
        foreach ($children as $c) {
            $i++;
            $parts[] = 'child:' . $i
                . '|w:' . $n($c['weight'] ?? 0)
                . '|q:' . $s($c['quality_status'] ?? null)
                . '|r:' . $s($c['reference'] ?? null);
        }

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * [#6] Politique CENTRALE d'héritage du statut qualité des filles —
     * l'appelant ne choisit pas librement :
     *   mère libérée   → filles libérées, SAUF si un contrôle post-division est
     *                    requis (refendage, découpe de zone défectueuse…) → quarantaine ;
     *   mère quarantaine → filles en quarantaine (jamais libérées automatiquement) ;
     *   mère refusée   → division déjà bloquée en amont ;
     *   mère inconnue  → filles en quarantaine (jamais libérées automatiquement).
     * Un statut demandé ne peut qu'être PLUS restrictif que la politique.
     */
    private function childQualityStatus(Coil $mother, ?string $requested, bool $requiresQc): string
    {
        $policy = match (true) {
            $mother->quality_status === Coil::QUALITY_RELEASED && ! $requiresQc => Coil::QUALITY_RELEASED,
            default => Coil::QUALITY_QUARANTINED, // quarantaine, inconnu, ou contrôle requis
        };

        // Le demandeur peut durcir (quarantaine/refus), jamais assouplir.
        if ($requested !== null && $requested !== $policy) {
            $moreRestrictive = [Coil::QUALITY_QUARANTINED, Coil::QUALITY_REJECTED];
            if (in_array($requested, $moreRestrictive, true)) {
                return $requested;
            }
            throw new \RuntimeException(sprintf(
                'Division : disposition « %s » demandée pour une fille alors que la politique '
                . 'd\'héritage impose « %s » (mère : %s). Une fille ne peut pas être moins '
                . 'restrictive que sa mère.',
                $requested, $policy, $mother->quality_status ?? 'inconnu'
            ));
        }

        return $policy;
    }
}
