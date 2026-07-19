<?php

namespace App\Repositories;

use App\Models\ClientPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientPaymentRepository extends BaseRepository
{
    public function __construct(ClientPayment $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of client payments.
     *
     * Accepted filters:
     *   client_id         – exact match
     *   payment_method_id – exact match
     *   date_from         – payment_date >= date_from
     *   date_to           – payment_date <= date_to
     *   search            – matches reference or client name
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ClientPayment::query()
            ->leftJoin('clients', 'client_payments.client_id', '=', 'clients.id')
            ->select('client_payments.*')
            ->with(['client', 'paymentMethod', 'allocations.invoice'])
            ->when(
                !empty($filters['client_id']),
                fn ($q) => $q->where('client_payments.client_id', $filters['client_id'])
            )
            ->when(
                !empty($filters['payment_method_id']),
                fn ($q) => $q->where('client_payments.payment_method_id', $filters['payment_method_id'])
            )
            ->when(
                !empty($filters['date_from']),
                fn ($q) => $q->where('client_payments.payment_date', '>=', $filters['date_from'])
            )
            ->when(
                !empty($filters['date_to']),
                fn ($q) => $q->where('client_payments.payment_date', '<=', $filters['date_to'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('client_payments.number', 'like', $s)
                       ->orWhere('client_payments.reference', 'like', $s)
                       ->orWhere('clients.name', 'like', $s)
                       ->orWhere('clients.trade_name', 'like', $s);
                })
            )
            ->orderByDesc('client_payments.payment_date')
            ->orderByDesc('client_payments.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single client payment with all related data for display.
     */
    public function findWithDetails(int $id): ClientPayment
    {
        return ClientPayment::with([
            'client',
            'paymentMethod',
            'cashAccount',
            // [§2 Règlement] documents sources liés à chaque facture imputée
            'allocations.invoice.order',
            'allocations.invoice.deliveryNote',
            'createdBy',
        ])->findOrFail($id);
    }
}
