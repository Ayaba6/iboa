<?php

namespace App\Repositories;

use App\Models\CreditNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CreditNoteRepository extends BaseRepository
{
    public function __construct(CreditNote $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CreditNote::query()
            ->leftJoin('clients', 'credit_notes.client_id', '=', 'clients.id')
            ->select('credit_notes.*')
            ->with(['client', 'invoice'])
            ->when($filters['client_id'] ?? null, fn($q, $v) => $q->where('credit_notes.client_id', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('credit_notes.status', $v))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $like = "%$s%";
                    $q2->where('credit_notes.number', 'like', $like)
                       ->orWhere('clients.name', 'like', $like);
                });
            })
            ->orderByDesc('credit_notes.issued_at')
            ->orderByDesc('credit_notes.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findWithDetails(int $id): CreditNote
    {
        return CreditNote::with([
            'client',
            'invoice',
            'items.product',
            'items.product.unit',
            'items.unit',
            'createdBy',
            'validatedBy',
            'replacementDelivery',
        ])->findOrFail($id);
    }
}
