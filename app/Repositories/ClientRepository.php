<?php
namespace App\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository extends BaseRepository
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Client::query()
            ->when(isset($filters['search']), fn($q) => $q->where(function($q2) use ($filters) {
                $q2->where('name', 'like', "%{$filters['search']}%")
                   ->orWhere('code', 'like', "%{$filters['search']}%")
                   ->orWhere('phone', 'like', "%{$filters['search']}%")
                   ->orWhere('email', 'like', "%{$filters['search']}%");
            }))
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['category']), fn($q) => $q->where('category', $filters['category']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): Client
    {
        return Client::with(['contacts', 'addresses', 'assignedCommercial', 'interactions.user'])->findOrFail($id);
    }

    /**
     * Prochain code client libre au format CLI-#####.
     * Basé sur le plus grand numéro existant (pas le dernier créé) + boucle
     * anti-collision — robuste face aux trous de séquence et codes manuels.
     */
    public function generateCode(): string
    {
        $maxNum = (int) Client::withTrashed()
            ->where('code', 'like', 'CLI-%')
            ->pluck('code')
            ->map(fn ($code) => (int) substr($code, 4))
            ->max();

        do {
            $maxNum++;
            $code = 'CLI-' . str_pad((string) $maxNum, 5, '0', STR_PAD_LEFT);
        } while (Client::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
