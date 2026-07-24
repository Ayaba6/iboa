<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [R2 §2 — sortie de quarantaine par décision qualité]
 *
 * Les retours en disposition « quarantaine » entrent au DÉPÔT QUARANTAINE
 * (DEP-QUAR), JAMAIS en stock vendable. La remise en vente n'est jamais
 * automatique : elle exige une décision qualité explicite (un décideur habilité
 * `quality.manage`, un motif, une trace d'audit). Ce service est le SEUL chemin
 * pour faire sortir de la quarantaine.
 *
 * Deux issues possibles de la décision qualité :
 *   - release() : libération → transfert DEP-QUAR → dépôt vendable ;
 *   - scrap()   : rebut définitif → sortie de DEP-QUAR (pas de retour vendable).
 */
class QuarantineService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Dépôt de quarantaine de la société (par code %QUAR% ou nom « quarantaine »).
     * @throws \RuntimeException si absent
     */
    public function quarantineWarehouse(int $companyId): Warehouse
    {
        return Warehouse::where('company_id', $companyId)
            ->where(fn ($q) => $q->where('code', 'like', '%QUAR%')->orWhere('name', 'like', '%uarantaine%'))
            ->firstOr(fn () => throw new \RuntimeException('Aucun dépôt de quarantaine configuré.'));
    }

    /**
     * Quantité actuellement en quarantaine pour un article.
     */
    public function quarantinedQuantity(int $productId, int $companyId): float
    {
        return (float) ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $this->quarantineWarehouse($companyId)->id)
            ->value('quantity');
    }

    /**
     * [Décision qualité — LIBÉRATION] Transfère `qty` de la quarantaine vers un
     * dépôt vendable après contrôle qualité conforme. Refuse si la quantité en
     * quarantaine est insuffisante ou si la destination est elle-même un dépôt
     * de quarantaine. Décideur et motif obligatoires, tracés.
     *
     * @throws \RuntimeException
     */
    public function release(int $productId, int $companyId, float $qty, ?int $destinationWarehouseId, string $motif, ?int $decidedBy = null): void
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \RuntimeException('Motif de décision qualité obligatoire pour libérer de la quarantaine.');
        }
        if ($qty <= 0) {
            throw new \RuntimeException('La quantité à libérer doit être positive.');
        }

        DB::transaction(function () use ($productId, $companyId, $qty, $destinationWarehouseId, $motif, $decidedBy) {
            $quar = $this->quarantineWarehouse($companyId);

            $dest = $destinationWarehouseId
                ? Warehouse::where('company_id', $companyId)->findOrFail($destinationWarehouseId)
                : Warehouse::where('company_id', $companyId)->where('is_default', true)->first();
            if (! $dest) {
                throw new \RuntimeException('Aucun dépôt vendable de destination.');
            }
            // Garde : ne jamais « libérer » vers un autre dépôt de quarantaine.
            $isQuar = str_contains(strtoupper($dest->code ?? ''), 'QUAR')
                || str_contains(mb_strtolower($dest->name ?? ''), 'uarantaine');
            if ($isQuar) {
                throw new \RuntimeException('La destination d\'une libération ne peut pas être un dépôt de quarantaine.');
            }

            $available = (float) ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $quar->id)
                ->lockForUpdate()
                ->value('quantity');
            if ($available < $qty) {
                throw new \RuntimeException(sprintf(
                    'Quantité en quarantaine insuffisante : %s disponible(s), %s demandée(s).',
                    rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.')
                ));
            }

            $this->stockService->recordMovement([
                'product_id'       => $productId,
                'warehouse_id'     => $quar->id,
                'dest_warehouse_id'=> $dest->id,
                'type'             => 'transfert',
                'quantity'         => $qty,
                'occurred_at'      => now(),
                'reference_type'   => 'quarantine_release',
                'notes'            => 'Libération quarantaine — ' . $motif,
            ]);

            app(AuditService::class)->log('quarantaine.liberation', null, [], [
                'product_id'  => $productId,
                'quantite'    => $qty,
                'de'          => $quar->code,
                'vers'        => $dest->code,
                'decideur'    => $decidedBy ?? Auth::id(),
                'motif'       => $motif,
            ]);
        });
    }

    /**
     * [Décision qualité — REBUT] Sort définitivement `qty` de la quarantaine sans
     * retour vendable (non conforme, détruit). Décideur et motif obligatoires,
     * tracés. La valorisation de la perte est portée par le registre de rebut.
     *
     * @throws \RuntimeException
     */
    public function scrap(int $productId, int $companyId, float $qty, string $motif, ?int $decidedBy = null): void
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \RuntimeException('Motif de décision qualité obligatoire pour rebuter depuis la quarantaine.');
        }
        if ($qty <= 0) {
            throw new \RuntimeException('La quantité à rebuter doit être positive.');
        }

        DB::transaction(function () use ($productId, $companyId, $qty, $motif, $decidedBy) {
            $quar = $this->quarantineWarehouse($companyId);

            $this->stockService->recordMovement([
                'product_id'     => $productId,
                'warehouse_id'   => $quar->id,
                'type'           => 'sortie',
                'quantity'       => $qty,
                'occurred_at'    => now(),
                'reference_type' => 'quarantine_scrap',
                'notes'          => 'Rebut depuis quarantaine — ' . $motif,
            ]);

            app(AuditService::class)->log('quarantaine.rebut', null, [], [
                'product_id' => $productId,
                'quantite'   => $qty,
                'de'         => $quar->code,
                'decideur'   => $decidedBy ?? Auth::id(),
                'motif'      => $motif,
            ]);
        });
    }
}
