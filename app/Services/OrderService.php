<?php

namespace App\Services;

use App\Events\OrderConfirmed;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Modules\Production\Services\ReservationService;
use App\Repositories\OrderRepository;
use App\Support\SheetConversion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        public readonly OrderRepository $repository,
        private DocumentSequenceService $sequenceService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function generateNumber(Company $company): string
    {
        return $this->sequenceService->nextNumber($company, 'commande');
    }


    public function create(array $data): Order
    {
        // [Parametrage Vente] client bloque = aucun document commercial
        ClientService::assertSellable(
            ! empty($data['client_id']) ? Client::find($data['client_id']) : null
        );

        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id'] = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;

            // [UI — doublon retiré] `default_tax_label` était saisi dans un champ
            // « Taxes » distinct de « TVA par défaut », stocké, jamais lu, et pouvait
            // contredire la TVA réellement appliquée aux lignes. Le champ a disparu de
            // l'écran ; la valeur est DÉRIVÉE de l'état réel par un service UNIQUE,
            // partagé par devis, commande et facture.
            $data['default_tax_label'] = app(\App\Services\Sales\SalesTaxLabelService::class)->derive($data, $items);
            $data['number'] = $this->sequenceService->nextNumber($company, 'commande');
            $data['created_by'] = Auth::id();
            $data['status'] = $data['status'] ?? 'brouillon';

            // [TVA-EXEMPT] Défense serveur : forcer TVA=0 si client exonéré
            $client = isset($data['client_id'])
                ? Client::find($data['client_id'])
                : null;
            if ($client?->isTaxExempt()) {
                $items = $this->zeroOutTax($items);
            }

            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $discount = (int) ($data['global_discount_amount'] ?? 0);

            $data['subtotal_ht'] = $subtotal;
            $data['total_discount'] = $discount;
            $data['total_tax'] = $taxTotal;
            $data['total_ttc'] = $subtotal + $taxTotal - $discount;
            $data['global_discount_amount'] = $discount;

            $order = Order::create($data);
            $this->syncItems($order, $items);
            $this->recalculate($order);

            return $order->fresh();
        });
    }

    public function update(Order $order, array $data): Order
    {
        // [CDC §13.1] Défense en profondeur : après validation financière,
        // toute modification tarifaire (prix unitaires, remises) exige
        // orders.edit_validated — même si l'appel contourne la policy HTTP.
        $user = auth()->user();
        if ($user
            && ! in_array($order->status, ['brouillon', 'en_attente_validation'], true)
            && ! $user->can('orders.edit_validated')
            && $this->touchesPricing($order, $data)) {
            throw new \RuntimeException(
                'Commande validée : les prix sont verrouillés (§13.1). Seul un responsable commercial peut les modifier.'
            );
        }

        return DB::transaction(function () use ($order, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $order->update($data);

            if ($items !== null) {
                // [FIX-QTE-01] When items are replaced on a confirmed/in-progress order,
                // release the reservations placed for the old quantities before deleting
                // the rows, then re-establish reservations for the new quantities.
                $reservingStatuses = ['confirme', 'en_preparation', 'partiellement_livre'];
                $needsResync = in_array($order->status, $reservingStatuses);

                if ($needsResync) {
                    $this->releaseStockReservations($order);
                }

                // [BUG-A3-SALES-LINE-IMMUTABLE-012] Synchronisation PAR IDENTITE.
                // La destruction/recreation renumerotait les lignes a chaque
                // edition et rompait tout ce qui les reference : OF, affectation
                // MTO, reservation, preparation, livraison, facture.
                app(\App\Services\Sales\OrderItemSynchronizer::class)->sync(
                    $order,
                    $items,
                    fn (array $ligne, int $position) => $this->buildItemValues($ligne, $position)
                );

                if ($needsResync) {
                    $this->reserveStock($order->fresh());
                }
            }

            $this->recalculate($order);

            return $order->fresh();
        });
    }

    public function delete(Order $order): bool
    {
        if (! in_array($order->status, ['brouillon', 'annule'])) {
            throw new \RuntimeException('Seules les commandes en brouillon ou annulées peuvent être supprimées.');
        }

        return $order->delete();
    }

    /**
     * [CDC §13.1] Détecte si la mise à jour change les prix : remise globale,
     * prix unitaire ou remise de ligne différents des lignes existantes.
     */
    private function touchesPricing(Order $order, array $data): bool
    {
        if (array_key_exists('global_discount_amount', $data)
            && (int) $data['global_discount_amount'] !== (int) $order->global_discount_amount) {
            return true;
        }
        if (array_key_exists('global_discount_percent', $data)
            && (float) $data['global_discount_percent'] !== (float) $order->global_discount_percent) {
            return true;
        }

        $items = $data['items'] ?? null;
        if ($items === null) {
            return false;
        }

        // Comparaison par produit : prix unitaire ou remise modifiés, ligne
        // ajoutée ou supprimée → considéré comme changement tarifaire.
        $existing = $order->items()->get(['product_id', 'unit_price', 'discount_percent'])
            ->keyBy('product_id');

        if (count($items) !== $existing->count()) {
            return true;
        }

        foreach ($items as $item) {
            $current = $existing->get($item['product_id'] ?? null);
            if (! $current) {
                return true; // nouvelle ligne
            }
            if ((int) ($item['unit_price'] ?? 0) !== (int) $current->unit_price) {
                return true;
            }
            if ((float) ($item['discount_percent'] ?? 0) !== (float) $current->discount_percent) {
                return true;
            }
        }

        return false;
    }

    // ── Workflow transitions ──────────────────────────────────────────────────

    /** brouillon → confirme */
    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // [ARCH-S2-01] Lock the order row inside the transaction to prevent
            // two simultaneous requests from double-confirming the same order.
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'brouillon') {
                throw new \RuntimeException('Seule une commande en brouillon peut être confirmée.');
            }

            // [BUG-A3-SALES-ZERO-PRICE-026] Circuit direct — mêmes gardes que le
            // circuit de validation.
            //
            // `CommercialWorkflowService::validateOrder()` contrôlait prix nul et
            // prix plancher ; cette méthode-ci, qui aboutit au MÊME statut
            // `confirme` et déclenche le même `OrderConfirmed`, n'en contrôlait
            // aucun. Confirmer directement au lieu de soumettre suffisait donc à
            // sortir du dispositif — un contournement, pas un raccourci.
            app(SalesFloorWaiverService::class)->assertDocumentMayProceed($order);

            $order->update([
                'status' => 'confirme',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            $fresh = $order->fresh();

            // Fire event — ReserveStockOnOrderConfirmed listener handles stock
            // reservation; other listeners can handle notifications, CRM updates, etc.
            // Running synchronously inside this transaction guarantees atomicity.
            event(new OrderConfirmed($fresh));

            return $fresh;
        });
    }

    /** any → annule */
    /**
     * [Ventes §17] Annulation d'une commande — motif OBLIGATOIRE et tracé.
     *
     * Cette méthode était la seule de tout l'ERP à annuler un document sans
     * motif : tous les autres services (facture, avoir, encaissement, virement,
     * OF, transfert de stock, préparation…) en exigent un. Une commande
     * confirmée passait donc à « annulé » sans auteur, sans date d'action, sans
     * raison — en contradiction avec la règle « ne jamais supprimer ni annuler
     * silencieusement un document métier validé ».
     *
     * L'écriture au journal passe par `cancelDocument()` (trait
     * HasCommercialWorkflow), qui enregistre ancien statut, nouveau statut,
     * utilisateur, rôle, IP et motif dans `commercial_validations`. C'est
     * l'implémentation UNIQUE : CommercialWorkflowService::cancel() délègue ici
     * pour les commandes, afin que les deux chemins ne divergent pas.
     */
    public function cancel(Order $order, string $motif): Order
    {
        // Précondition vérifiée AVANT d'ouvrir la transaction : `cancelDocument()`
        // rejette déjà un motif vide, mais il n'intervient qu'après la libération
        // des réservations. La correction ne tiendrait alors que par le rollback —
        // fonctionnel, mais coûteux et implicite. Message identique à celui du
        // trait pour que l'utilisateur voie la même phrase quel que soit le chemin.
        if (trim($motif) === '') {
            throw new \RuntimeException("Le motif d'annulation est obligatoire.");
        }

        // L'écriture d'audit exige un auteur : `commercial_validations.user_id`
        // est NOT NULL. Sans cette garde, un appel hors session — commande
        // console, tâche planifiée, job en file — échouerait sur un accès à une
        // propriété de null, message illisible et transaction à moitié jouée.
        // Une annulation sans auteur identifiable n'est de toute façon pas
        // traçable : la refuser est le comportement correct, pas une limitation.
        if (! Auth::check()) {
            throw new \RuntimeException(
                "L'annulation d'une commande doit être imputable à un utilisateur identifié. "
                .'Aucune session active : authentifiez-vous, ou utilisez Auth::login() pour un traitement automatisé.'
            );
        }

        return DB::transaction(function () use ($order, $motif) {
            // [ARCH-S2-02] Lock the order row to prevent concurrent cancel+confirm
            // or double-cancel races.
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (in_array($order->status, ['annule', 'facture', 'livre'])) {
                throw new \RuntimeException('Cette commande ne peut pas être annulée (statut : '.$order->status.').');
            }

            // [Unification réservations] Une seule libération : ferme toutes les lignes
            // stock_reservations actives de la commande (ventes + production) et remet le
            // stock disponible. Idempotent — sans effet si rien n'est réservé.
            app(ReservationService::class)->releaseForOrder($order);

            // Pose le statut ET écrit l'entrée d'audit (motif, auteur, rôle, IP).
            // Refuse un motif vide : c'est la garde qui rend la traçabilité
            // impossible à contourner, y compris pour les appels programmatiques.
            $order->cancelDocument('annule', $motif);

            return $order->fresh();
        });
    }

    /**
     * Check stock availability for all stockable items on this order.
     * Returns array: ['ok' => bool, 'lines' => [['description', 'required', 'available', 'ok']]]
     */
    public function checkStock(Order $order): array
    {
        $order->load('items.product');
        $lines = [];
        $allOk = true;

        foreach ($order->items as $item) {
            if (! $item->product_id || ! ($item->product?->is_stockable ?? false)) {
                continue;
            }

            $warehouseId = $order->delivery_warehouse_id;
            $query = ProductStock::where('product_id', $item->product_id);
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
            $stocks = $query->get(['quantity', 'reserved_quantity']);
            $available = (float) $stocks->sum('quantity') - (float) $stocks->sum('reserved_quantity');

            $required = (float) $item->quantity - (float) $item->delivered_quantity;
            $lineOk = $available >= $required;

            if (! $lineOk) {
                $allOk = false;
            }

            $lines[] = [
                'description' => $item->description,
                'required' => $required,
                'available' => $available,
                'ok' => $lineOk,
            ];
        }

        return ['ok' => $allOk, 'lines' => $lines];
    }

    /**
     * Convert the order to an invoice.
     */
    public function createInvoice(Order $order): Invoice
    {
        // Facturation interdite avant livraison : un bon de livraison doit exister
        // (statut ≥ en_preparation). Une commande seulement « confirmé » n'est pas facturable.
        if (! in_array($order->status, ['en_preparation', 'partiellement_livre', 'livre'])) {
            throw new \RuntimeException('La commande doit être livrée (bon de livraison créé) avant de générer une facture.');
        }

        return app(InvoiceService::class)->createFromOrder($order);
    }

    /**
     * Convert the order to a delivery note.
     */
    public function createDeliveryNote(Order $order): DeliveryNote
    {
        if (! in_array($order->status, ['confirme', 'en_preparation', 'partiellement_livre'])) {
            throw new \RuntimeException('La commande doit être confirmée avant de créer un bon de livraison.');
        }

        return app(DeliveryNoteService::class)->createFromOrder($order);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncItems(Order $order, array $items): void
    {
        foreach ($items as $i => $item) {
            $valeurs = $this->buildItemValues($item, $i);
            if ($valeurs !== null) {
                $order->items()->create($valeurs);
            }
        }
    }

    /**
     * [BUG-A3-SALES-LINE-IMMUTABLE-012] Valeurs d'une ligne, sans l'ecrire.
     *
     * Extrait pour que la CREATION et la SYNCHRONISATION differentielle
     * partagent exactement le meme calcul : deux constructions paralleles de la
     * meme ligne finiraient par diverger sur un arrondi ou une garde.
     *
     * Retourne null quand la ligne est vide et doit etre ignoree.
     */
    public function buildItemValues(array $item, int $i): ?array
    {
        {
            if (empty($item['description']) && empty($item['product_id'])) {
                return null;
            }

            // [§5 TÔLE BAC] Quantité en mètres linéaires = nombre de tôles × métrage
            // (règle et arrondi centralisés dans SheetConversion).
            $nbToles = isset($item['nb_toles']) ? (float) $item['nb_toles'] : null;
            $metrage = isset($item['metrage_par_tole']) ? (float) $item['metrage_par_tole'] : null;
            $qty = SheetConversion::resolveQuantity($nbToles, $metrage, $item['quantity'] ?? 1);

            $price = (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount_percent'] ?? 0);

            // [X3 §14] Vente d'un article non vendable interdite (catégorie prioritaire, repli article).
            if (! empty($item['product_id'])) {
                $prod = Product::with('itemCategory')->find($item['product_id']);
                $sellable = $prod?->itemCategory ? (bool) $prod->itemCategory->is_sellable : (bool) ($prod?->is_sellable ?? true);
                if ($prod && ! $sellable) {
                    throw new \RuntimeException(sprintf(
                        'Article « %s » non vendable (catégorie %s) — vente refusée.',
                        $prod->name, $prod->itemCategory?->code ?? 'article'
                    ));
                }
            }

            // [§5 PRIX PLANCHER] Vente sous le prix plancher interdite, sauf rôle autorisé.

            // [§6 DIMENSIONS] Longueur unitaire hors bornes fabricables interdite.
            $this->assertSheetLength($item['product_id'] ?? null, $metrage);

            $tax = (float) ($item['tax_rate_value'] ?? 0);
            $ht = (int) round($qty * $price * (1 - $disc / 100));
            $lineTax = (int) round($ht * ($tax / 100));
            $ttc = $ht + $lineTax;

            // [Ventes] Même dérivation que sur le devis, via le service partagé :
            // unité depuis l'article, coût figé à la saisie. La règle ne doit pas
            // exister en deux exemplaires susceptibles de diverger.
            $item = app(\App\Services\Sales\SalesLineDefaultsService::class)->apply($item);

            return [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'] ?? '',
                'unit_id' => $item['unit_id'] ?? null,
                'unit_cost' => $item['unit_cost'] ?? null,
                'quantity' => $qty,
                'nb_toles' => $nbToles,
                'metrage_par_tole' => $metrage,
                'unit_price' => (int) $price,
                'discount_percent' => $disc,
                'tax_rate_id' => $item['tax_rate_id'] ?? null,
                'tax_rate_value' => $tax,
                'line_total_ht' => $ht,
                'line_tax' => $lineTax,
                'line_total_ttc' => $ttc,
                'sort_order' => $i,
            ];
        }
    }

    /**
     * [§5] Refuse un prix de vente (net de remise) inférieur au prix plancher
     * de l'article. Les rôles direction/commercial peuvent déroger.
     */
    private function assertFloorPrice(?int $productId, float $price, float $discount): void
    {
        if (! $productId) {
            return;
        }
        $product = Product::find($productId);
        $floor = $product ? app(SalesPriceGuardService::class)->effectiveFloor($product) : 0;
        if ($floor <= 0) {
            return;
        }
        $net = $price * (1 - $discount / 100);
        if ($net + 0.5 >= $floor) {
            return;
        }
        $user = Auth::user();
        if ($user && $user->hasAnyRole(['super_admin', 'directeur', 'directeur_commercial'])) {
            return; // autorisation spéciale
        }
        throw new \RuntimeException(sprintf(
            'Prix de vente (%s) inférieur au prix plancher (%s) pour « %s ». Autorisation spéciale requise.',
            number_format($net, 0, ',', ' '), number_format($floor, 0, ',', ' '), $product->name
        ));
    }

    /**
     * [§6] Refuse une longueur unitaire (métrage par tôle) hors des bornes
     * fabricables de l'article (longueur_min / longueur_max, en mètres).
     * Article sans bornes = aucun contrôle.
     */
    private function assertSheetLength(?int $productId, ?float $length): void
    {
        if (! $productId || $length === null || $length <= 0) {
            return;
        }
        $product = Product::find($productId);
        if (! $product) {
            return;
        }
        $min = $product->longueur_min !== null ? (float) $product->longueur_min : null;
        $max = $product->longueur_max !== null ? (float) $product->longueur_max : null;

        if ($max !== null && $length > $max) {
            throw new \RuntimeException(sprintf(
                'Longueur unitaire (%s m) supérieure à la longueur maximale fabricable (%s m) pour « %s ».',
                rtrim(rtrim(number_format($length, 3, ',', ' '), '0'), ','),
                rtrim(rtrim(number_format($max, 3, ',', ' '), '0'), ','), $product->name
            ));
        }
        if ($min !== null && $length < $min) {
            throw new \RuntimeException(sprintf(
                'Longueur unitaire (%s m) inférieure à la longueur minimale fabricable (%s m) pour « %s ».',
                rtrim(rtrim(number_format($length, 3, ',', ' '), '0'), ','),
                rtrim(rtrim(number_format($min, 3, ',', ' '), '0'), ','), $product->name
            ));
        }
    }

    /** [TVA-EXEMPT] Met tous les taux TVA à 0 sur un tableau d'items. */
    private function zeroOutTax(array $items): array
    {
        return array_map(function (array $item) {
            $item['tax_rate_value'] = 0;
            $item['tax_rate_id'] = null;
            $item['line_tax'] = 0;

            return $item;
        }, $items);
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount_percent'] ?? 0);
            $tax = (float) ($item['tax_rate_value'] ?? 0);
            $ht = $qty * $price * (1 - $disc / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    /**
     * Increment reserved_quantity on ProductStock for every stockable item
     * that has not yet been fully delivered. Called when an order is confirmed.
     * Public so that QuoteService can call it when creating a confirmed order
     * directly from a quote (bypassing the brouillon → confirme transition).
     */
    public function reserveStock(Order $order): void
    {
        // [Unification réservations] Source unique = ReservationService, qui écrit
        // des lignes stock_reservations EN PLUS de reserved_quantity. On supprime
        // l'ancien incrément direct (sans ligne) qui provoquait :
        //  - un double-comptage avec le bouton « Réserver » manuel (même ReservationService),
        //  - des réservations fantômes non tracées, jamais libérées proprement.
        // reserveStockForOrder est idempotent par commande (netNeeded = commandé − déjà réservé)
        // et ne réserve que le stock RÉELLEMENT disponible (le reste part en production).
        app(ReservationService::class)->reserveStockForOrder($order);
    }

    /**
     * Libère les réservations d'une commande (annulation / re-synchro des lignes).
     * [Unification réservations] Délègue à ReservationService : ferme les lignes
     * stock_reservations actives ET décrémente reserved_quantity de façon cohérente,
     * plutôt qu'un décrément direct qui laissait les lignes ouvertes (fantômes).
     */
    private function releaseStockReservations(Order $order): void
    {
        app(ReservationService::class)->releaseForOrder($order);
    }

    private function recalculate(Order $order): void
    {
        $order->load('items');

        $subtotal = (int) $order->items->sum('line_total_ht');
        $taxTotal = (int) $order->items->sum('line_tax');
        $discount = (int) ($order->global_discount_amount ?? 0);
        $total = $subtotal + $taxTotal - $discount;

        // [FIX-MAJEUR] Include total_discount in recalculate update
        $order->update([
            'subtotal_ht' => $subtotal,
            'total_tax' => $taxTotal,
            'total_discount' => $discount,
            'total_ttc' => $total,
        ]);
    }
}
