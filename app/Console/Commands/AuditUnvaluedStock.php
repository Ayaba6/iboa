<?php

namespace App\Console\Commands;

use App\Models\StockLot;
use App\Modules\Production\Models\Coil;
use Illuminate\Console\Command;

class AuditUnvaluedStock extends Command
{
    protected $signature = 'a3:audit-unvalued-stock';

    protected $description = 'Détecte tout lot ou bobine utilisable avec une valorisation absente';

    public function handle(): int
    {
        $lots = StockLot::with(['product', 'warehouse'])->where('quantity', '>', 0)
            ->where(fn ($query) => $query->where('unit_cost', '<=', 0)
                ->orWhereIn('valuation_status', ['valorisation_manquante', 'bloque_comptabilite']))->get();
        $coils = Coil::with(['product'])->where('remaining_weight', '>', 0)
            ->where(fn ($query) => $query->where('cost_per_kg', '<=', 0)
                ->orWhereIn('valuation_status', ['valorisation_manquante', 'bloque_comptabilite']))->get();

        foreach ($lots as $lot) {
            $this->error("LOT {$lot->lot_number} | {$lot->product?->reference} | {$lot->warehouse?->code} | {$lot->quantity} | {$lot->valuation_status}");
        }
        foreach ($coils as $coil) {
            $this->error("BOBINE {$coil->reference} | {$coil->product?->reference} | {$coil->remaining_weight} KG | {$coil->valuation_status}");
        }
        if ($lots->isNotEmpty() || $coils->isNotEmpty()) {
            $this->error('ECHEC : stock non valorisé encore physiquement présent.');

            return self::FAILURE;
        }
        $this->info('OK : aucun stock physique non valorisé.');

        return self::SUCCESS;
    }
}
