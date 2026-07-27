<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\Coil;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [RÈGLE A — division physique d'une bobine] Une bobine physique est une unité
 * qualité INDIVISIBLE. Lorsqu'une partie identifiable est non conforme, la seule
 * façon de traiter séparément les portions est une opération PHYSIQUE réelle
 * (découpe / refendage / séparation) qui crée des bobines FILLES traçables :
 *
 *   Bobine mère → fille conforme + fille non conforme (+ chutes / pertes)
 *
 * La mère passe alors au statut SPLIT (elle ne porte plus de disposition propre)
 * et chaque fille porte sa propre disposition, son poids, son coût et sa
 * traçabilité vers la mère.
 *
 * Invariant de réconciliation :
 *   poids mère = Σ poids filles + chutes + pertes
 */
class CoilSplitService
{
    /**
     * @param  array<int,array{weight:float,quality_status?:string,reference?:string}>  $children
     * @param  float  $scrap  chutes/pertes assumées (non réattribuées à une fille)
     * @return array<int,Coil>  bobines filles créées
     *
     * @throws \RuntimeException
     */
    public function split(Coil $mother, array $children, float $scrap = 0.0, ?string $reason = null): array
    {
        if ($children === []) {
            throw new \RuntimeException('Division de bobine : au moins une bobine fille est requise.');
        }

        return DB::transaction(function () use ($mother, $children, $scrap, $reason) {
            $mother = Coil::lockForUpdate()->findOrFail($mother->id);

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
            if ((float) $mother->remaining_weight <= 0) {
                throw new \RuntimeException("Bobine {$mother->reference} : entièrement consommée — division impossible.");
            }

            // Réconciliation : Σ filles + chutes = poids restant de la mère.
            $sum = 0.0;
            foreach ($children as $c) {
                $sum += (float) ($c['weight'] ?? 0);
            }
            $motherWeight = (float) $mother->remaining_weight;
            if (abs(($sum + $scrap) - $motherWeight) > 0.001) {
                throw new \RuntimeException(sprintf(
                    'Division de bobine %s : Σ filles (%s) + chutes (%s) = %s ≠ poids mère (%s).',
                    $mother->reference, $sum, $scrap, $sum + $scrap, $motherWeight
                ));
            }

            $created = [];
            $i = 0;
            foreach ($children as $c) {
                $weight = (float) ($c['weight'] ?? 0);
                if ($weight <= 0) {
                    continue;
                }
                $i++;
                $status = $c['quality_status'] ?? Coil::QUALITY_QUARANTINED;

                $child = Coil::create([
                    'company_id'     => $mother->company_id,
                    'product_id'     => $mother->product_id,
                    'supplier_id'    => $mother->supplier_id,
                    'reception_id'   => $mother->reception_id,
                    'warehouse_id'   => $mother->warehouse_id,
                    'stock_lot_id'   => $mother->stock_lot_id,
                    'parent_coil_id' => $mother->id,
                    'reference'      => $c['reference'] ?? ($mother->reference . '-' . $i),
                    'lot_number'     => $mother->lot_number,
                    'initial_weight' => $weight,
                    'remaining_weight' => $weight,
                    // Coût TRANSFÉRÉ de la mère (jamais recalculé au cours du jour).
                    'cost_per_kg'    => $mother->cost_per_kg,
                    'purchase_price' => (int) round($weight * (float) $mother->cost_per_kg),
                    'received_at'    => $mother->received_at,
                    'status'         => 'disponible',
                    'quality_status' => $status,
                    'transformation_status' => Coil::TRANSFO_INTACT,
                    // Soldes quantitatifs de la fille : disposition unique (règle A).
                    'qty_released'   => $status === Coil::QUALITY_RELEASED ? $weight : 0,
                    'qty_quarantine' => $status === Coil::QUALITY_QUARANTINED ? $weight : 0,
                    'qty_rejected'   => $status === Coil::QUALITY_REJECTED ? $weight : 0,
                    'created_by'     => Auth::id(),
                ]);
                $created[] = $child;
            }

            // [#3] La mère est TRANSFORMÉE (axe transformation), pas « dans une
            // disposition qualité » : sa disposition qualité passe à NULL — elle
            // appartient désormais aux bobines filles. Poids transféré aux filles.
            $mother->update([
                'transformation_status' => Coil::TRANSFO_SPLIT,
                'quality_status'   => null,
                'remaining_weight' => 0,
                'status'           => 'epuisee',
                'qty_quarantine'   => 0,
                'qty_released'     => 0,
                'notes'            => trim(($mother->notes ?? '') . "\n" . sprintf(
                    '[DIVISION %s] %d fille(s), chutes %s. %s',
                    now()->format('d/m/Y H:i'), count($created), $scrap, $reason ?? ''
                )),
            ]);

            app(AuditService::class)->log('bobine.division', $mother, [], [
                'filles'  => array_map(fn ($c) => ['ref' => $c->reference, 'poids' => (float) $c->initial_weight, 'qualite' => $c->quality_status], $created),
                'chutes'  => $scrap,
                'motif'   => $reason,
            ]);

            return $created;
        });
    }
}
