<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionOrder;

class ProductionSnapshotService
{
    /** @return array<string, mixed> */
    public function capture(ProductionOrder $order): array
    {
        $order->loadMissing([
            'billOfMaterial.lines.product',
            'billOfMaterial.lines.unit',
            'billOfMaterial.lines.substitute',
            'billOfMaterial.routing.operations.workCenter',
        ]);

        $bom = $order->billOfMaterial;
        $routing = $bom?->routing;
        $bomSnapshot = null;
        $routingSnapshot = null;

        if ($bom) {
            $bomSnapshot = [
                'schema_version' => 1,
                'captured_at' => now()->toISOString(),
                'header' => $bom->only([
                    'id', 'company_id', 'product_id', 'scrap_product_id', 'defect_product_id', 'code', 'name', 'site', 'alternative',
                    'version_majeure', 'version_mineure', 'date_reference', 'date_debut_validite',
                    'date_fin_validite', 'statut', 'quantite_base', 'standard_waste_rate',
                    'consumption_per_meter', 'machine_time_per_unit', 'labor_per_unit',
                    'packaging_per_unit', 'std_material_cost', 'std_labor_cost', 'std_machine_cost',
                    'std_energy_cost', 'std_maintenance_cost', 'std_packaging_cost',
                    'std_overhead_cost', 'rendement_standard', 'controle_qualite',
                ]),
                'lines' => $bom->lines->map(fn ($line) => [
                    ...$line->only([
                        'id', 'product_id', 'label', 'quantity_per_meter', 'unit_id', 'waste_rate',
                        'sort_order', 'sequence', 'groupe', 'type_composant', 'coef',
                        'depot_sortie_id', 'lot_obligatoire', 'statut', 'substitute_product_id',
                        'substitute_note',
                    ]),
                    'product' => $line->product?->only(['id', 'reference', 'name', 'type']),
                    'unit' => $line->unit?->only(['id', 'code', 'name']),
                    'substitute' => $line->substitute?->only(['id', 'reference', 'name']),
                ])->values()->all(),
            ];
        }

        if ($routing) {
            $routingSnapshot = [
                'schema_version' => 1,
                'captured_at' => now()->toISOString(),
                'header' => $routing->only([
                    'id', 'company_id', 'bill_of_material_id', 'product_id', 'code', 'name',
                    'site', 'alternative', 'version_majeure', 'version_mineure', 'date_reference',
                    'date_debut_validite', 'date_fin_validite', 'statut', 'unite_temps',
                    'quantite_base', 'uo', 'rendement_standard', 'controle_qualite',
                    'type_gamme', 'methode_suivi', 'allow_time_overrun', 'tolerance_rendement',
                    'gestion_rebuts', 'auto_transfer', 'block_on_control_fail',
                ]),
                'operations' => $routing->operations->map(fn ($operation) => [
                    ...$operation->only([
                        'id', 'work_center_id', 'sequence', 'name', 'setup_minutes',
                        'run_minutes_per_unit', 'operation_number', 'labor_minutes',
                        'quantite_base', 'uo', 'rendement', 'controle_qualite',
                        'sous_traitance', 'statut', 'code', 'type_operation',
                        'waiting_minutes', 'point_controle', 'is_critical',
                    ]),
                    'work_center' => $operation->workCenter?->toArray(),
                ])->values()->all(),
            ];
        }

        return [
            'bom_version' => $this->version($bomSnapshot['header'] ?? null),
            'routing_version' => $this->version($routingSnapshot['header'] ?? null),
            'bom_snapshot' => $bomSnapshot,
            'bom_snapshot_sha256' => $this->hash($bomSnapshot),
            'routing_snapshot' => $routingSnapshot,
            'routing_snapshot_sha256' => $this->hash($routingSnapshot),
            'snapshotted_at' => now(),
        ];
    }

    /** @param array<string, mixed>|null $snapshot */
    public function hash(?array $snapshot): ?string
    {
        return $snapshot === null
            ? null
            : hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed>|null $header */
    private function version(?array $header): ?string
    {
        if ($header === null) {
            return null;
        }

        $major = $header['version_majeure'] ?? null;
        $minor = $header['version_mineure'] ?? null;

        return $major === null && $minor === null ? null : trim((string) $major.'.'.(string) $minor, '.');
    }
}
