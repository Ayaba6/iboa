<?php
namespace App\Services;

use App\Models\Client;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\DB;

class ClientService
{
    public function __construct(private ClientRepository $repository) {}

    public function search(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = $this->repository->generateCode();
            }
            $data['credit_limit']     = (float) ($data['credit_limit']     ?? 0);
            $data['payment_days']     = (int)   ($data['payment_days']     ?? 0);
            $data['default_discount'] = (float) ($data['default_discount'] ?? 0);

            $taxRateIds = $data['tax_rate_ids'] ?? [];
            unset($data['tax_rate_ids']);

            $client = Client::create($data);

            $client->taxRates()->sync(array_filter((array) $taxRateIds));

            if (!empty($data['contacts'])) {
                foreach ($data['contacts'] as $contact) {
                    $client->contacts()->create($contact);
                }
            }
            if (!empty($data['addresses'])) {
                foreach ($data['addresses'] as $address) {
                    $client->addresses()->create($address);
                }
            }
            return $client;
        });
    }

    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $contacts    = $data['contacts']     ?? null;
            $addresses   = $data['addresses']    ?? null;
            $taxRateIds  = $data['tax_rate_ids'] ?? [];
            unset($data['contacts'], $data['addresses'], $data['tax_rate_ids']);

            $data['credit_limit']     = (float) ($data['credit_limit']     ?? 0);
            $data['payment_days']     = (int)   ($data['payment_days']     ?? 0);
            $data['default_discount'] = (float) ($data['default_discount'] ?? 0);

            $client->update($data);

            $client->taxRates()->sync(array_filter((array) $taxRateIds));

            if ($contacts !== null) {
                $client->contacts()->delete();
                foreach ($contacts as $contact) {
                    if (!empty($contact['last_name'])) {
                        $client->contacts()->create($contact);
                    }
                }
            }

            if ($addresses !== null) {
                $client->addresses()->delete();
                foreach ($addresses as $address) {
                    if (!empty($address['address'])) {
                        $client->addresses()->create($address);
                    }
                }
            }

            return $client->fresh();
        });
    }

    public function delete(Client $client): bool
    {
        // Vérifie pas de factures impayées
        $unpaidInvoices = $client->invoices()->whereNotIn('status', ['payee', 'annulee'])->count();
        if ($unpaidInvoices > 0) {
            throw new \RuntimeException("Impossible de supprimer : le client a {$unpaidInvoices} facture(s) non soldée(s).");
        }
        return $client->delete();
    }

    /**
     * [Parametrage Vente] Refuse tout document commercial pour un client bloque.
     * Appele en tete de creation devis / commande / facture.
     */
    public static function assertSellable(?\App\Models\Client $client): void
    {
        if (!$client) {
            return;
        }
        if ($client->is_blocked) {
            throw new \RuntimeException(
                "Client {$client->name} bloque" . ($client->blocked_reason ? " : {$client->blocked_reason}" : '') .
                " - aucun devis, commande ou facture ne peut etre cree."
            );
        }
    }
}
