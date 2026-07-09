<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CrmContact;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const MAX_PER_TYPE = 4;

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $like    = '%'.$q.'%';
        $results = [];
        $user    = auth()->user();

        // [Recherche pro] mots-clés métier : « facture » liste les dernières factures
        // + un accès direct à la liste complète, idem devis/commandes/BL/avoirs…
        array_push($results, ...$this->keywordResults($q, $user));

        // Clients
        if ($user?->can('clients.view')) {
            $items = Client::where('is_active', true)
                ->where(fn($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->get(['id', 'name', 'code', 'email'])
                ->map(fn($c) => [
                    'type'     => 'Client',
                    'icon'     => 'user-group',
                    'color'    => 'blue',
                    'label'    => $c->name,
                    'sublabel' => $c->code ?? $c->email,
                    'url'      => route('clients.show', $c),
                ])->all();
            array_push($results, ...$items);
        }

        // Fournisseurs
        if ($user?->can('suppliers.view')) {
            $items = Supplier::where('is_active', true)
                ->where(fn($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->get(['id', 'name', 'code', 'email'])
                ->map(fn($s) => [
                    'type'     => 'Fournisseur',
                    'icon'     => 'truck',
                    'color'    => 'orange',
                    'label'    => $s->name,
                    'sublabel' => $s->code ?? $s->email,
                    'url'      => route('suppliers.show', $s),
                ])->all();
            array_push($results, ...$items);
        }

        // Produits
        if ($user?->can('products.view')) {
            $items = Product::where('is_active', true)
                ->where(fn($query) => $query->where('name', 'like', $like)->orWhere('reference', 'like', $like)->orWhere('barcode', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->get(['id', 'name', 'reference'])
                ->map(fn($p) => [
                    'type'     => 'Produit',
                    'icon'     => 'archive',
                    'color'    => 'emerald',
                    'label'    => $p->name,
                    'sublabel' => $p->reference,
                    'url'      => route('products.show', $p),
                ])->all();
            array_push($results, ...$items);
        }

        // Factures clients
        if ($user?->can('invoices.view')) {
            $items = Invoice::where('number', 'like', $like)
                ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->with('client:id,name')
                ->get(['id', 'number', 'client_id', 'total_ttc', 'status'])
                ->map(fn($inv) => [
                    'type'     => 'Facture',
                    'icon'     => 'document-text',
                    'color'    => 'indigo',
                    'label'    => $inv->number,
                    'sublabel' => $inv->client?->name,
                    'url'      => route('ventes.factures.show', $inv),
                ])->all();
            array_push($results, ...$items);
        }

        // Commandes
        if ($user?->can('orders.view')) {
            $items = Order::where('number', 'like', $like)
                ->limit(self::MAX_PER_TYPE)
                ->with('client:id,name')
                ->get(['id', 'number', 'client_id', 'status'])
                ->map(fn($o) => [
                    'type'     => 'Commande',
                    'icon'     => 'shopping-cart',
                    'color'    => 'violet',
                    'label'    => $o->number,
                    'sublabel' => $o->client?->name,
                    'url'      => route('ventes.commandes.show', $o),
                ])->all();
            array_push($results, ...$items);
        }

        // Factures fournisseurs
        if ($user?->can('supplier_invoices.view')) {
            $items = SupplierInvoice::where('number', 'like', $like)
                ->orWhere('supplier_invoice_number', 'like', $like)
                ->limit(self::MAX_PER_TYPE)
                ->with('supplier:id,name')
                ->get(['id', 'number', 'supplier_id', 'total_ttc'])
                ->map(fn($inv) => [
                    'type'     => 'Fact. fourn.',
                    'icon'     => 'document',
                    'color'    => 'red',
                    'label'    => $inv->number,
                    'sublabel' => $inv->supplier?->name,
                    'url'      => route('achats.factures-fournisseurs.show', $inv),
                ])->all();
            array_push($results, ...$items);
        }

        // CRM — Contacts & Prospects
        $items = CrmContact::where('company_id', $user?->company_id)
            ->where(fn($q) => $q->where('name', 'like', $like)
                                ->orWhere('company_name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('phone', 'like', $like))
            ->limit(self::MAX_PER_TYPE)
            ->get(['id', 'name', 'company_name', 'type', 'status'])
            ->map(fn($c) => [
                'type'     => 'CRM',
                'icon'     => 'user-circle',
                'color'    => 'cyan',
                'label'    => $c->name,
                'sublabel' => implode(' · ', array_filter([$c->company_name, $c->typeLabel()])),
                'url'      => route('crm.contacts.show', $c),
            ])->all();
        array_push($results, ...$items);

        return response()->json([
            'results' => $results,
            'total'   => count($results),
        ]);
    }

    /**
     * [Recherche pro] « facture », « devis », « bl »… → derniers documents du type
     * + lien direct vers la liste complète. Respecte les permissions.
     */
    private function keywordResults(string $q, $user): array
    {
        $needle = mb_strtolower(str_replace(['é', 'è', 'ê'], 'e', $q));

        $types = [
            'facture' => [
                'aliases' => ['facture', 'factures', 'fac'],
                'perm'    => 'invoices.view',
                'type'    => 'Facture', 'color' => 'indigo',
                'index'   => fn () => route('ventes.factures.index'),
                'items'   => fn () => Invoice::with('client:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'client_id', 'total_ttc'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => trim(($d->client?->name ?? '') . ' · ' . number_format((float) $d->total_ttc, 0, ',', ' ') . ' F', ' ·'), 'url' => route('ventes.factures.show', $d)]),
            ],
            'devis' => [
                'aliases' => ['devis', 'dev'],
                'perm'    => 'quotes.view',
                'type'    => 'Devis', 'color' => 'violet',
                'index'   => fn () => route('ventes.devis.index'),
                'items'   => fn () => \App\Models\Quote::with('client:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'client_id'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => $d->client?->name, 'url' => route('ventes.devis.show', $d)]),
            ],
            'commande' => [
                'aliases' => ['commande', 'commandes', 'cmd'],
                'perm'    => 'orders.view',
                'type'    => 'Commande', 'color' => 'violet',
                'index'   => fn () => route('ventes.commandes.index'),
                'items'   => fn () => Order::with('client:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'client_id'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => $d->client?->name, 'url' => route('ventes.commandes.show', $d)]),
            ],
            'bl' => [
                'aliases' => ['bl', 'livraison', 'livraisons', 'bon de livraison'],
                'perm'    => 'delivery_notes.view',
                'type'    => 'Bon livraison', 'color' => 'emerald',
                'index'   => fn () => route('ventes.bons-livraison.index'),
                'items'   => fn () => \App\Models\DeliveryNote::with('client:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'client_id'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => $d->client?->name, 'url' => route('ventes.bons-livraison.show', $d)]),
            ],
            'avoir' => [
                'aliases' => ['avoir', 'avoirs'],
                'perm'    => 'credit_notes.view',
                'type'    => 'Avoir', 'color' => 'red',
                'index'   => fn () => route('ventes.avoirs.index'),
                'items'   => fn () => \App\Models\CreditNote::with('client:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'client_id'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => $d->client?->name, 'url' => route('ventes.avoirs.show', $d)]),
            ],
            'facture_fournisseur' => [
                'aliases' => ['facture fournisseur', 'factures fournisseurs', 'ff'],
                'perm'    => 'supplier_invoices.view',
                'type'    => 'Fact. fourn.', 'color' => 'red',
                'index'   => fn () => route('achats.factures-fournisseurs.index'),
                'items'   => fn () => SupplierInvoice::with('supplier:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'supplier_id'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => $d->supplier?->name, 'url' => route('achats.factures-fournisseurs.show', $d)]),
            ],
            'bon_commande' => [
                'aliases' => ['bon de commande', 'bc', 'commande achat', 'commandes achats'],
                'perm'    => 'purchase_orders.view',
                'type'    => 'BC fourn.', 'color' => 'amber',
                'index'   => fn () => route('achats.commandes.index'),
                'items'   => fn () => \App\Models\PurchaseOrder::with('supplier:id,name')->latest('id')->limit(5)
                    ->get(['id', 'number', 'supplier_id'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => $d->supplier?->name, 'url' => route('achats.commandes.show', $d)]),
            ],
            'demande_achat' => [
                'aliases' => ['demande achat', "demande d'achat", 'demandes achat', 'da'],
                'perm'    => 'purchase_requests.view',
                'type'    => 'Demande achat', 'color' => 'amber',
                'index'   => fn () => route('achats.demandes-achat.index'),
                'items'   => fn () => \App\Models\PurchaseRequest::latest('id')->limit(5)
                    ->get(['id', 'number'])
                    ->map(fn ($d) => ['label' => $d->number, 'sublabel' => null, 'url' => route('achats.demandes-achat.show', $d)]),
            ],
        ];

        foreach ($types as $def) {
            if (!in_array($needle, $def['aliases'], true)) {
                continue;
            }
            if ($def['perm'] && !$user?->can($def['perm'])) {
                return [];
            }

            $out = collect($def['items']())->map(fn ($d) => [
                'type'     => $def['type'],
                'icon'     => 'document-text',
                'color'    => $def['color'],
                'label'    => $d['label'],
                'sublabel' => $d['sublabel'],
                'url'      => $d['url'],
            ])->all();

            $out[] = [
                'type'     => $def['type'],
                'icon'     => 'list',
                'color'    => 'gray',
                'label'    => 'Voir tous les documents « ' . $def['type'] . ' » →',
                'sublabel' => 'Liste complète avec filtres',
                'url'      => $def['index'](),
            ];

            return $out;
        }

        return [];
    }
}
