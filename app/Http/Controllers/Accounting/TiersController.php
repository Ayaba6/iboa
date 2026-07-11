<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * [Maquette X3] Annuaire des tiers — vue unifiée clients + fournisseurs.
 * Encours client = restant dû des factures de vente ; fournisseur = factures d'achat.
 */
class TiersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.view');
    }

    public function index(Request $request): View
    {
        $f = [
            'type'          => $request->input('type'),          // client | fournisseur
            'category'      => $request->input('category'),
            'country'       => $request->input('country'),
            'city'          => $request->input('city'),
            'status'        => $request->input('status'),        // actif | bloque | inactif
            'collectif'     => $request->input('collectif'),
            'search'        => $request->input('search'),
            'ifu'           => $request->input('ifu'),
            'phone'         => $request->input('phone'),
            'email'         => $request->input('email'),
            'show_inactive' => $request->boolean('show_inactive'),
        ];

        // Encours par tiers (une requête agrégée par famille)
        $encoursClients = \App\Models\Invoice::selectRaw('client_id, SUM(remaining_amount) as encours')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->groupBy('client_id')->pluck('encours', 'client_id');

        $encoursFournisseurs = \App\Models\SupplierInvoice::selectRaw('supplier_id, SUM(remaining_amount) as encours')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->groupBy('supplier_id')->pluck('encours', 'supplier_id');

        $mapClient = fn (Client $c) => (object) [
            'kind'       => 'client',
            'id'         => $c->id,
            'code'       => $c->code,
            'name'       => $c->name,
            'category'   => $c->category ?: ($c->type ?: '—'),
            'ifu'        => $c->ifu,
            'rccm'       => $c->rccm,
            'city'       => $c->city,
            'country'    => $c->country ?: 'Burkina Faso',
            'phone'      => $c->phone ?: $c->mobile,
            'email'      => $c->email,
            'collectif'  => $c->compte_collectif ?: '411100',
            'encours'    => (int) ($encoursClients[$c->id] ?? 0),
            'solde'      => (int) ($c->balance ?? 0),
            'plafond'    => (int) ($c->credit_limit ?? 0),
            'statut'     => $c->is_blocked ? 'bloque' : ($c->is_active ? 'actif' : 'inactif'),
            'url'        => route('clients.show', $c),
        ];

        $mapSupplier = fn (Supplier $s) => (object) [
            'kind'       => 'fournisseur',
            'id'         => $s->id,
            'code'       => $s->code,
            'name'       => $s->name,
            'category'   => $s->category ?: ($s->groupe_fournisseur ?: '—'),
            'ifu'        => $s->ifu,
            'rccm'       => $s->rccm,
            'city'       => $s->city,
            'country'    => $s->country ?: 'Burkina Faso',
            'phone'      => $s->phone ?: $s->mobile,
            'email'      => $s->email,
            'collectif'  => $s->compte_collectif ?: '401100',
            'encours'    => (int) ($encoursFournisseurs[$s->id] ?? 0),
            'solde'      => (int) -($s->balance ?? 0),
            'plafond'    => (int) ($s->credit_limit ?? 0),
            'statut'     => $s->blocage_achat ? 'bloque' : ($s->is_active ? 'actif' : 'inactif'),
            'url'        => route('suppliers.show', $s),
        ];

        $tiers = collect();
        if ($f['type'] !== 'fournisseur') {
            $tiers = $tiers->concat(Client::query()->get()->map($mapClient));
        }
        if ($f['type'] !== 'client') {
            $tiers = $tiers->concat(Supplier::query()->get()->map($mapSupplier));
        }

        // Filtres en mémoire (référentiel de taille raisonnable)
        $tiers = $tiers
            ->when(! $f['show_inactive'], fn ($c) => $c->reject(fn ($t) => $t->statut === 'inactif'))
            ->when($f['category'], fn ($c, $v) => $c->filter(fn ($t) => stripos($t->category ?? '', $v) !== false))
            ->when($f['country'],  fn ($c, $v) => $c->filter(fn ($t) => stripos($t->country ?? '', $v) !== false))
            ->when($f['city'],     fn ($c, $v) => $c->filter(fn ($t) => stripos($t->city ?? '', $v) !== false))
            ->when($f['status'],   fn ($c, $v) => $c->filter(fn ($t) => $t->statut === $v))
            ->when($f['collectif'],fn ($c, $v) => $c->filter(fn ($t) => str_starts_with($t->collectif ?? '', $v)))
            ->when($f['ifu'],      fn ($c, $v) => $c->filter(fn ($t) => stripos($t->ifu ?? '', $v) !== false))
            ->when($f['phone'],    fn ($c, $v) => $c->filter(fn ($t) => str_contains(preg_replace('/\D/', '', $t->phone ?? ''), preg_replace('/\D/', '', $v))))
            ->when($f['email'],    fn ($c, $v) => $c->filter(fn ($t) => stripos($t->email ?? '', $v) !== false))
            ->when($f['search'],   fn ($c, $v) => $c->filter(fn ($t) =>
                stripos($t->code ?? '', $v) !== false || stripos($t->name ?? '', $v) !== false))
            ->sortBy('code')->values();

        // KPIs sur l'ensemble filtré
        $stats = [
            'total'        => $tiers->count(),
            'clients'      => $tiers->where('kind', 'client')->count(),
            'clients_enc'  => $tiers->where('kind', 'client')->sum('encours'),
            'fourn'        => $tiers->where('kind', 'fournisseur')->count(),
            'fourn_enc'    => $tiers->where('kind', 'fournisseur')->sum('encours'),
            'encours_glob' => $tiers->sum('encours'),
            'solde_glob'   => $tiers->sum('solde'),
        ];

        // Pagination manuelle
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $page    = max((int) $request->input('page', 1), 1);
        $paginated = new LengthAwarePaginator(
            $tiers->forPage($page, $perPage)->values(),
            $tiers->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $categories = $tiers->pluck('category')->filter()->unique()->sort()->values();

        return view('comptabilite.tiers.index', [
            'tiers'      => $paginated,
            'stats'      => $stats,
            'filters'    => $f,
            'categories' => $categories,
            'company'    => currentCompany(),
        ]);
    }
}
