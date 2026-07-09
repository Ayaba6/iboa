<?php

namespace App\Services;

use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(public readonly SupplierRepository $repository) {}

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    /**
     * Génère le prochain code fournisseur libre au format FOUR-#####.
     *
     * Basé sur le plus grand numéro existant (pas le dernier créé), puis
     * incrémente jusqu'à trouver un code réellement libre — robuste face aux
     * trous de séquence, codes manuels et enregistrements soft-deleted.
     * Portable (calcul en PHP, pas de SQL spécifique).
     */
    public function generateCode(): string
    {
        $maxNum = (int) Supplier::withTrashed()
            ->where('code', 'like', 'FOUR-%')
            ->pluck('code')
            ->map(fn ($code) => (int) substr($code, 5))
            ->max();

        do {
            $maxNum++;
            $code = 'FOUR-' . str_pad((string) $maxNum, 5, '0', STR_PAD_LEFT);
        } while (Supplier::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $contacts  = $data['contacts']  ?? [];
            $addresses = $data['addresses'] ?? [];
            unset($data['contacts'], $data['addresses']);

            // Auto-génère le code fournisseur si absent (colonne NOT NULL + UNIQUE)
            if (empty($data['code'])) {
                $data['code'] = $this->generateCode();
            }

            /** @var Supplier $supplier */
            $supplier = $this->repository->create($data);

            foreach ($contacts as $c) {
                if (!empty($c['last_name'])) {
                    $supplier->contacts()->create($c);
                }
            }

            foreach ($addresses as $a) {
                if (!empty($a['address'])) {
                    $supplier->addresses()->create($a);
                }
            }

            return $supplier;
        });
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $contacts  = $data['contacts']  ?? null;
            $addresses = $data['addresses'] ?? null;
            unset($data['contacts'], $data['addresses']);

            $this->repository->update($supplier->id, $data);

            if ($contacts !== null) {
                $supplier->contacts()->delete();
                foreach ($contacts as $c) {
                    if (!empty($c['last_name'])) {
                        $supplier->contacts()->create($c);
                    }
                }
            }

            if ($addresses !== null) {
                $supplier->addresses()->delete();
                foreach ($addresses as $a) {
                    if (!empty($a['address'])) {
                        $supplier->addresses()->create($a);
                    }
                }
            }

            return $supplier->fresh();
        });
    }

    public function delete(Supplier $supplier): void
    {
        // Check for open purchase orders that are not yet fully received/cancelled
        $openOrders = $supplier->purchaseOrders()
            ->whereIn('status', ['brouillon', 'envoye', 'confirme', 'partiellement_recu'])
            ->count();

        if ($openOrders > 0) {
            throw new \RuntimeException(
                "Impossible de supprimer ce fournisseur : il a {$openOrders} commande(s) en cours."
            );
        }

        $this->repository->delete($supplier->id);
    }
}
