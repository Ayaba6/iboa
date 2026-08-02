<?php

namespace App\Services;

use App\Http\Traits\HasOptimisticLocking;
use App\Models\Company;
use App\Models\Order;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    use HasOptimisticLocking;

    public function __construct(
        public readonly QuoteRepository $repository,
        private DocumentSequenceService $sequenceService,
        private OrderService $orderService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }


    public function create(array $data): Quote
    {
        // [Parametrage Vente] client bloque = aucun document commercial
        \App\Services\ClientService::assertSellable(
            !empty($data['client_id']) ? \App\Models\Client::find($data['client_id']) : null
        );

        // [D2] Un article vendable sans stratégie d'approvisionnement est refusé
        // DÈS LA LIGNE DE DEVIS. Une erreur connue à la saisie ne doit pas
        // attendre la confirmation de commande pour se manifester.
        app(\App\Services\Sales\FulfillmentStrategyResolver::class)
            ->assertLines($data['items'] ?? []);

        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id']   = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number']       = $this->sequenceService->nextNumber($company, 'devis');
            $data['created_by']   = Auth::id();
            $data['status']       = $data['status'] ?? 'brouillon';

            // [UI — doublon retiré] `default_tax_label` était saisi dans un champ
            // « Taxes » distinct de « TVA par défaut », stocké, jamais lu, et pouvait
            // contredire la TVA réellement appliquée aux lignes. Le champ a disparu de
            // l'écran ; la valeur est DÉRIVÉE de l'état réel par un service UNIQUE,
            // partagé par devis, commande et facture.
            $data['default_tax_label'] = app(\App\Services\Sales\SalesTaxLabelService::class)->derive($data, $items);

            // Validité par défaut : 30 jours après émission si non renseignée —
            // évite les devis sans expiration (badge « Expire dans Nj » inopérant).
            if (empty($data['expires_at'])) {
                $issued = ! empty($data['issued_at']) ? \Illuminate\Support\Carbon::parse($data['issued_at']) : now();
                $data['expires_at'] = $issued->copy()->addDays(30)->toDateString();
            }

            // [TVA-EXEMPT] Défense serveur : forcer TVA=0 si client exonéré
            $client = isset($data['client_id'])
                ? \App\Models\Client::find($data['client_id'])
                : null;
            if ($client?->isTaxExempt()) {
                $items = $this->zeroOutTax($items);
            }

            // Calculate totals from items
            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $discount = (int) ($data['global_discount_amount'] ?? 0);

            $data['subtotal_ht']            = $subtotal;
            $data['total_discount']         = $discount;
            $data['total_tax']              = $taxTotal;
            $data['total_ttc']              = $subtotal + $taxTotal - $discount;
            $data['global_discount_amount'] = $discount;

            $quote = Quote::create($data);
            $this->syncItems($quote, $items);
            $this->recalculate($quote);

            return $quote->fresh();
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data) {
            // [CONCURRENCE] Verrou optimiste
            $this->assertVersion($quote, $data['_lock_version'] ?? null);
            unset($data['_lock_version'], $data['_idempotency_key']);

            // [LOT 3 — modification post-validation] Un devis CONVERTI en commande
            // est figé (sinon le devis signé et la commande divergent) ; un devis
            // validé/accepté ne se modifie pas silencieusement — repasser par un
            // nouveau devis ou une révision explicite.
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if ($quote->converted_to_order_id) {
                throw new \RuntimeException(
                    'Ce devis a été converti en commande ' . ($quote->convertedOrder?->number ?? '') .
                    ' — il est figé. Modifiez la commande ou créez un nouveau devis.'
                );
            }
            if (! in_array($quote->status, [Quote::STATUS_DRAFT, 'en_attente_validation', 'refuse'], true)) {
                throw new \RuntimeException(sprintf(
                    'Le devis %s est « %s » — un devis validé ne se modifie pas silencieusement. Créez une nouvelle version.',
                    $quote->number,
                    $quote->status
                ));
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            // [TVA-EXEMPT] Défense serveur sur update
            $clientId = $data['client_id'] ?? $quote->client_id;
            $client   = \App\Models\Client::find($clientId);
            if ($client?->isTaxExempt() && $items !== null) {
                $items = $this->zeroOutTax($items);
            }

            $quote->update($data);

            if ($items !== null) {
                $quote->items()->delete();
                $this->syncItems($quote, $items);
            }

            $this->recalculate($quote);
            return $quote->fresh();
        });
    }

    /**
     * [VENTES-PRO] Duplique un devis : clone avec lignes + nouveau numéro, statut brouillon.
     * Utile pour devis récurrents ou variantes (équivalent Odoo "Duplicate").
     */
    public function duplicate(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $quote->load('items');
            $company = currentCompany();

            $new = Quote::create([
                'company_id'             => $company->id,
                'fiscal_year_id'         => $company->current_fiscal_year_id,
                'client_id'              => $quote->client_id,
                'number'                 => $this->sequenceService->nextNumber($company, 'devis'),
                'reference'              => $quote->reference,
                'status'                 => 'brouillon',
                'issued_at'              => now()->toDateString(),
                'expires_at'             => now()->addDays(15)->toDateString(),
                'currency_code'          => $quote->currency_code,
                'exchange_rate'          => $quote->exchange_rate,
                'subtotal_ht'            => $quote->subtotal_ht,
                'total_discount'         => $quote->total_discount,
                'total_tax'              => $quote->total_tax,
                'total_ttc'              => $quote->total_ttc,
                'global_discount_percent'=> $quote->global_discount_percent,
                'global_discount_amount' => $quote->global_discount_amount,
                'notes'                  => $quote->notes,
                'terms'                  => $quote->terms,
                'footer_note'            => $quote->footer_note,
                'created_by'             => Auth::id(),
            ]);

            foreach ($quote->items as $i => $item) {
                $new->items()->create([
                    'product_id'       => $item->product_id,
                    'description'      => $item->description,
                    'unit_id'          => $item->unit_id,
                    'quantity'         => $item->quantity,
                    'unit_price'       => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate_id'      => $item->tax_rate_id,
                    'tax_rate_value'   => $item->tax_rate_value,
                    'line_total_ht'    => $item->line_total_ht,
                    'line_tax'         => $item->line_tax,
                    'line_total_ttc'   => $item->line_total_ttc,
                    'sort_order'       => $i,
                ]);
            }

            return $new->fresh('items');
        });
    }

    public function delete(Quote $quote): bool
    {
        if (!in_array($quote->status, ['brouillon', 'refuse', 'annule'])) {
            throw new \RuntimeException('Seuls les devis en brouillon, refusés ou annulés peuvent être supprimés.');
        }
        return $quote->delete();
    }

    // ── Workflow transitions ──────────────────────────────────────────────────

    /** brouillon → envoye */
    public function send(Quote $quote): Quote
    {
        if ($quote->status !== 'brouillon') {
            throw new \RuntimeException('Seul un devis en brouillon peut être marqué comme envoyé.');
        }
        $quote->update(['status' => 'envoye']);
        return $quote->fresh();
    }

    /** envoye → accepte (validation client) */
    public function accept(Quote $quote): Quote
    {
        if (!in_array($quote->status, ['envoye', 'brouillon'])) {
            throw new \RuntimeException('Le devis doit être envoyé avant d\'être accepté.');
        }
        $quote->update([
            'status'       => 'accepte',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);
        return $quote->fresh();
    }

    /** envoye → refuse */
    public function refuse(Quote $quote): Quote
    {
        if (!in_array($quote->status, ['envoye', 'brouillon'])) {
            throw new \RuntimeException('Seul un devis envoyé peut être refusé.');
        }
        $quote->update(['status' => 'refuse']);
        return $quote->fresh();
    }

    /** any → annule */
    public function cancel(Quote $quote): Quote
    {
        // [FIX-VENTES-08] 'converti' must also be blocked: the order already exists and
        // cancelling the quote would leave the order in an inconsistent parent-less state.
        if (in_array($quote->status, ['annule', 'accepte', 'converti'])) {
            throw new \RuntimeException('Ce devis ne peut pas être annulé (statut : ' . $quote->status . ').');
        }
        $quote->update(['status' => 'annule']);
        return $quote->fresh();
    }

    /**
     * Accept the quote AND immediately convert it to a sales order in one atomic step.
     * Works from status brouillon or envoye.
     * Returns the newly created Order.
     */
    public function acceptAndConvert(Quote $quote): Order
    {
        if (!in_array($quote->status, ['brouillon', 'envoye'])) {
            throw new \RuntimeException(
                'Le devis doit être en brouillon ou envoyé pour être validé. Statut actuel : ' . $quote->status . '.'
            );
        }
        if ($quote->converted_to_order_id) {
            throw new \RuntimeException('Ce devis a déjà été converti en commande.');
        }
        // [CDC §7] Gardes AVANT le passage en 'accepte' : si la conversion est
        // impossible, le devis ne doit pas changer d'état.
        $this->assertConvertible($quote);

        // Accept first, then convert — all in one DB transaction
        $quote->update([
            'status'       => 'accepte',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        return $this->convertToOrder($quote);
    }

    /**
     * Convert this quote into a confirmed sales order.
     * Requires status = 'valide' (nouveau workflow interne) ou 'accepte' (ancien workflow client).
     */
    public function convertToOrder(Quote $quote): Order
    {
        $allowedStatuses = ['valide', 'accepte'];
        if (! in_array($quote->status, $allowedStatuses, true)) {
            throw new \RuntimeException(
                'Le devis doit être validé avant d\'être transformé en commande. Statut actuel : ' . $quote->status . '.'
            );
        }
        if ($quote->converted_to_order_id) {
            throw new \RuntimeException('Ce devis a déjà été converti en commande.');
        }
        $this->assertConvertible($quote);

        // [D2 — défense en profondeur] L'article a pu devenir incomplet APRÈS la
        // création du devis : reprise de données, changement de catégorie. Le
        // devis reste consultable et corrigeable, mais ne se convertit plus.
        $resolver = app(\App\Services\Sales\FulfillmentStrategyResolver::class);
        $resolver->assertLines($resolver->lignesDe($quote));

        return DB::transaction(function () use ($quote) {
            $company = currentCompany();
            $orderNumber = $this->sequenceService->nextNumber($company, 'commande');

            // [CDC §16.1] La commande issue d'un devis démarre en brouillon : elle doit
            // passer par le même circuit Validation commerciale → Validation financière
            // que toute commande créée directement, avant préparation/livraison.
            //
            // [BUG-A3-SALES-CONV-002] Les TERMES CONTRACTUELS sont recopiés au même
            // titre que les montants. Ils étaient omis, et l'omission ne laissait pas
            // la commande vide : les colonnes de `orders` portent des défauts, si bien
            // qu'une valeur plausible remplaçait la valeur négociée sans rien signaler.
            // Trois défauts réécrivaient le devis — `price_mode` → 'ttc' (un devis en
            // HT devenait une commande en TTC), `default_tax_label` → 'TVA 18%' (portée
            // fiscale : un libellé EXO était écrasé) et `priority` → 'normale'.
            // Constaté sur DEV-2026-00005 → CMD-2026-006.
            //
            // Règle : une valeur par défaut ne remplace jamais une valeur du devis.
            $order = Order::create([
                'company_id'             => $company->id,
                'client_id'              => $quote->client_id,
                'fiscal_year_id'         => $quote->fiscal_year_id,
                'quote_id'               => $quote->id,
                'number'                 => $orderNumber,
                'issued_at'              => now()->toDateString(),
                'status'                 => 'brouillon',
                'subtotal_ht'            => $quote->subtotal_ht,
                'total_discount'         => $quote->total_discount,
                'total_tax'              => $quote->total_tax,
                'total_ttc'              => $quote->total_ttc,
                'global_discount_amount' => $quote->global_discount_amount,
                'global_discount_percent'=> $quote->global_discount_percent,
                'currency_code'          => $quote->currency_code ?? 'XOF',
                'exchange_rate'          => $quote->exchange_rate  ?? 1,
                'reference'              => $quote->reference,
                'notes'                  => $quote->notes,
                'terms'                  => $quote->terms,
                'footer_note'            => $quote->footer_note,
                'created_by'             => Auth::id(),

                // Termes tarifaires et fiscaux.
                'price_mode'             => $quote->price_mode,
                'net_prices'             => $quote->net_prices,
                'price_list'             => $quote->price_list,
                'default_tax_label'      => $quote->default_tax_label,
                'fiscal_regime'          => $quote->fiscal_regime,
                'fiscal_representative'  => $quote->fiscal_representative,

                // Conditions de règlement.
                'payment_terms'          => $quote->payment_terms,
                'payment_method'         => $quote->payment_method,

                // Logistique. `quotes.warehouse_id` est le dépôt d'expédition prévu ;
                // il porte le nom `delivery_warehouse_id` côté commande.
                'delivery_warehouse_id'  => $quote->warehouse_id,
                'delivery_address'       => $quote->delivery_address,
                'delivery_location'      => $quote->delivery_location,
                'delivery_date'          => $quote->desired_delivery_date,
                'incoterm'               => $quote->incoterm,

                // Suivi commercial.
                'contact_id'             => $quote->contact_id,
                'sales_rep_id'           => $quote->sales_rep_id,
                'project_reference'      => $quote->project_reference,
                'priority'               => $quote->priority,
            ]);

            foreach ($quote->items as $item) {
                $order->items()->create([
                    'product_id'       => $item->product_id,
                    'description'      => $item->description,
                    'unit_id'          => $item->unit_id,
                    'quantity'         => $item->quantity,
                    'nb_toles'         => $item->nb_toles,
                    'metrage_par_tole' => $item->metrage_par_tole,
                    'unit_price'       => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate_id'      => $item->tax_rate_id,
                    'tax_rate_value'   => $item->tax_rate_value,
                    'line_total_ht'    => $item->line_total_ht,
                    'line_tax'         => $item->line_tax,
                    'line_total_ttc'   => $item->line_total_ttc,
                    'sort_order'       => $item->sort_order,
                ]);
            }

            // [CDC §16.1] Pas de réservation stock ici : la commande est en brouillon.
            // La réservation se fait via OrderConfirmed, déclenché par
            // CommercialWorkflowService::validateOrder() une fois les deux validations
            // (commerciale puis financière) effectuées.

            // [FIX-MAJEUR] Mark quote as 'converti' (distinct from simply 'accepte')
            $quote->update([
                'status'               => 'converti',
                'converted_to_order_id'=> $order->id,
            ]);

            return $order;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * [CDC §7 — versionnement] Gardes communes de conversion :
     *  - un devis expiré n'engage plus la société → le réviser pour re-valider ;
     *  - un devis remplacé par une révision active se convertit via la révision.
     */
    private function assertConvertible(Quote $quote): void
    {
        if ($quote->isExpired()) {
            throw new \RuntimeException(sprintf(
                'Le devis %s a expiré le %s — il ne peut plus être converti. Créez une révision pour proposer une offre à jour.',
                $quote->number,
                $quote->expires_at->format('d/m/Y')
            ));
        }
        if ($quote->hasActiveRevision()) {
            $rev = $quote->revisions()->whereNotIn('status', ['annule', 'refuse'])->latest('id')->first();
            throw new \RuntimeException(sprintf(
                'Le devis %s a été remplacé par la révision %s — convertissez la révision.',
                $quote->number,
                $rev->number
            ));
        }
    }

    /**
     * [CDC §7 — versionnement] Crée une révision d'un devis figé : nouvelle
     * version en brouillon liée au devis d'origine (revision_of_id), numéro de
     * révision incrémenté. L'original reste intact et consultable (son PDF est
     * reproductible) ; il devient non convertible tant que la révision est active.
     */
    public function revise(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);

            if ($quote->converted_to_order_id) {
                throw new \RuntimeException('Ce devis a été converti en commande — il ne peut plus être révisé.');
            }
            if (in_array($quote->status, ['brouillon', 'annule'], true)) {
                throw new \RuntimeException('Un devis en brouillon se modifie directement ; un devis annulé ne se révise pas.');
            }
            if ($quote->hasActiveRevision()) {
                throw new \RuntimeException('Une révision active existe déjà pour ce devis.');
            }

            $revision = $this->duplicate($quote);
            $revision->update([
                'revision_of_id'  => $quote->id,
                'revision_number' => (int) $quote->revision_number + 1,
                'reference'       => $quote->reference,
            ]);

            return $revision->fresh('items');
        });
    }

    private function syncItems(Quote $quote, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            // [§5 TÔLE BAC] Métrage linéaire = nb tôles × longueur unitaire (centralisé).
            $nbToles = isset($item['nb_toles']) ? (float) $item['nb_toles'] : null;
            $metrage = isset($item['metrage_par_tole']) ? (float) $item['metrage_par_tole'] : null;
            $qty   = \App\Support\SheetConversion::resolveQuantity($nbToles, $metrage, $item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc  = (float) ($item['discount_percent'] ?? 0);
            $tax   = (float) ($item['tax_rate_value'] ?? 0);
            $ht    = (int) round($qty * $price * (1 - $disc / 100));
            $lineTax = (int) round($ht * ($tax / 100));
            $ttc   = $ht + $lineTax;

            // [Ventes] Unité et coût dérivés de l'article quand ils ne sont pas
            // fournis. Sans cela, `unit_id` restait NULL sur 100 % des lignes et
            // le coût n'existait qu'à la facture — donc aucune marge au devis.
            $item = app(\App\Services\Sales\SalesLineDefaultsService::class)->apply($item);

            $quote->items()->create([
                'product_id'       => $item['product_id'] ?? null,
                'description'      => $item['description'] ?? '',
                'unit_id'          => $item['unit_id'] ?? null,
                'unit_cost'        => $item['unit_cost'] ?? null,
                'quantity'         => $qty,
                'nb_toles'         => $nbToles,
                'metrage_par_tole' => $metrage,
                'unit_price'       => (int) $price,
                'discount_percent' => $disc,
                'tax_rate_id'      => $item['tax_rate_id'] ?? null,
                'tax_rate_value'   => $tax,
                'line_total_ht'    => $ht,
                'line_tax'         => $lineTax,
                'line_total_ttc'   => $ttc,
                'sort_order'       => $i,
            ]);
        }
    }

    /** [TVA-EXEMPT] Met tous les taux TVA à 0 sur un tableau d'items. */
    private function zeroOutTax(array $items): array
    {
        return array_map(function (array $item) {
            $item['tax_rate_value'] = 0;
            $item['tax_rate_id']    = null;
            $item['line_tax']       = 0;
            return $item;
        }, $items);
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty   = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc  = (float) ($item['discount_percent'] ?? 0);
            $tax   = (float) ($item['tax_rate_value'] ?? 0);
            $ht    = $qty * $price * (1 - $disc / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    private function recalculate(Quote $quote): void
    {
        $quote->load('items');

        $subtotal = (int) $quote->items->sum('line_total_ht');
        $taxTotal = (int) $quote->items->sum('line_tax');
        $discount = (int) ($quote->global_discount_amount ?? 0);
        $total    = $subtotal + $taxTotal - $discount;

        $quote->update([
            'subtotal_ht'    => $subtotal,
            'total_tax'      => $taxTotal,
            'total_discount' => $discount,
            'total_ttc'      => $total,
        ]);
    }
}
