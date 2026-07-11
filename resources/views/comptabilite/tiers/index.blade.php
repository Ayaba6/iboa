@extends('layouts.erp')
@section('title', 'Tiers')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Tiers</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $fmt   = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $statutBadges = [
        'actif'   => ['Actif',      'bg-emerald-100 text-emerald-700'],
        'bloque'  => ['Bloqué',     'bg-red-100 text-red-700'],
        'inactif' => ['Inactif',    'bg-gray-100 text-gray-500'],
    ];
@endphp
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Tiers</h1>
        <div class="flex items-center gap-1.5">
            <button type="submit" form="tiers-filter"
                    class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Rechercher</button>
            <button type="button" onclick="window.print()"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Nouveau ▾</button>
                <div x-show="open" x-cloak class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-[4px] shadow-lg z-20 min-w-[160px]">
                    <a href="{{ route('clients.create') }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50">Nouveau client</a>
                    <a href="{{ route('suppliers.create') }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50 border-t border-gray-100">Nouveau fournisseur</a>
                </div>
            </div>
            <a href="{{ route('comptabilite.dashboard') }}"
               class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

    {{-- Zone de filtres — fiche maquette --}}
    <form method="GET" id="tiers-filter" class="bg-white rounded-[4px] border border-gray-200 p-4">
        <div class="grid grid-cols-12 gap-x-3 gap-y-3">
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Société principale</p>
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                <input type="text" value="01" class="{{ $inpRo }} font-mono" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Type de tiers</label>
                <div class="relative">
                    <select name="type" class="{{ $lk }}">
                        <option value="">Tous</option>
                        <option value="client" @selected($filters['type'] === 'client')>Clients</option>
                        <option value="fournisseur" @selected($filters['type'] === 'fournisseur')>Fournisseurs</option>
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Catégorie</label>
                <div class="relative">
                    <select name="category" class="{{ $lk }}">
                        <option value="">Toutes</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($filters['category'] === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Pays</label>
                <input type="text" name="country" value="{{ $filters['country'] }}" placeholder="Tous les pays" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Ville</label>
                <input type="text" name="city" value="{{ $filters['city'] }}" placeholder="Toutes" class="{{ $inp }}">
            </div>

            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative">
                    <select name="status" class="{{ $lk }}">
                        <option value="">Tous</option>
                        <option value="actif" @selected($filters['status'] === 'actif')>Actif</option>
                        <option value="bloque" @selected($filters['status'] === 'bloque')>Bloqué</option>
                        <option value="inactif" @selected($filters['status'] === 'inactif')>Inactif</option>
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Compte collectif</label>
                <input type="text" name="collectif" value="{{ $filters['collectif'] }}" placeholder="411 / 401" class="{{ $inp }} font-mono">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Devise</label>
                <input type="text" value="XOF — Franc CFA" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">IFU / NIF</label>
                <input type="text" name="ifu" value="{{ $filters['ifu'] }}" placeholder="Entrez l'IFU" class="{{ $inp }} font-mono">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Téléphone</label>
                <input type="text" name="phone" value="{{ $filters['phone'] }}" placeholder="+226…" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Email</label>
                <input type="text" name="email" value="{{ $filters['email'] }}" placeholder="Entrez l'email" class="{{ $inp }}">
            </div>

            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Rechercher un tiers</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Code ou raison sociale" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-3 flex items-end pb-1">
                <label class="flex items-center gap-1.5 text-[12.5px] text-gray-700 cursor-pointer">
                    <input type="hidden" name="show_inactive" value="0">
                    <input type="checkbox" name="show_inactive" value="1" @checked($filters['show_inactive'])
                           class="w-3.5 h-3.5 rounded border-gray-400 text-emerald-600 focus:ring-emerald-400">
                    Afficher les tiers inactifs
                </label>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gray-50">
            <span class="text-[12px] text-gray-500">Total : <span class="font-semibold text-gray-900">{{ $stats['total'] }}</span> tiers</span>
            <span class="text-[11px] text-gray-400">Encours = restant dû factures · XOF</span>
        </div>
        <div class="overflow-x-auto">
        <table class="min-w-full text-[13px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">Code tiers</th>
                    <th class="px-3 py-1.5 text-left">Raison sociale</th>
                    <th class="px-3 py-1.5 text-left">Type</th>
                    <th class="px-3 py-1.5 text-left">Catégorie</th>
                    <th class="px-3 py-1.5 text-left">IFU / NIF</th>
                    <th class="px-3 py-1.5 text-left">RCCM</th>
                    <th class="px-3 py-1.5 text-left">Ville</th>
                    <th class="px-3 py-1.5 text-left">Pays</th>
                    <th class="px-3 py-1.5 text-left">Téléphone</th>
                    <th class="px-3 py-1.5 text-left">Compte collectif</th>
                    <th class="px-3 py-1.5 text-right">Encours (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Solde (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Plafond crédit (XOF)</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tiers as $t)
                @php [$sLabel, $sClass] = $statutBadges[$t->statut] ?? ['?', 'bg-gray-100 text-gray-500']; @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1 whitespace-nowrap">
                        <a href="{{ $t->url }}" class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[12px]">{{ $t->code }}</a>
                    </td>
                    <td class="px-3 py-1 font-medium text-gray-900 whitespace-nowrap">{{ $t->name }}</td>
                    <td class="px-3 py-1">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $t->kind === 'client' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $t->kind === 'client' ? 'Client' : 'Fournisseur' }}
                        </span>
                    </td>
                    <td class="px-3 py-1 text-gray-600 text-[12px]">{{ $t->category }}</td>
                    <td class="px-3 py-1 font-mono text-[11.5px] text-gray-500 whitespace-nowrap">{{ $t->ifu ?: '—' }}</td>
                    <td class="px-3 py-1 font-mono text-[11.5px] text-gray-500 whitespace-nowrap">{{ $t->rccm ?: '—' }}</td>
                    <td class="px-3 py-1 text-gray-600">{{ $t->city ?: '—' }}</td>
                    <td class="px-3 py-1 text-gray-600 text-[12px] whitespace-nowrap">{{ $t->country }}</td>
                    <td class="px-3 py-1 text-gray-600 tabular-nums text-[12px] whitespace-nowrap">{{ $t->phone ?: '—' }}</td>
                    <td class="px-3 py-1 font-mono text-[12px] text-gray-700">{{ $t->collectif }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium whitespace-nowrap">{{ $t->encours ? $fmt($t->encours) : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium whitespace-nowrap {{ $t->solde < 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $t->solde < 0 ? '-' : '' }}{{ $fmt(abs($t->solde)) }}
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-600">{{ $t->plafond ? $fmt($t->plafond) : '—' }}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $sClass }}">{{ $sLabel }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="14" class="px-4 py-10 text-center text-gray-400 text-[13px]">Aucun tiers ne correspond aux filtres.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="px-3 py-1.5 border-t border-gray-100 flex items-center justify-between">
            <div class="text-[12px] text-gray-500">
                {{ $tiers->firstItem() ?? 0 }} – {{ $tiers->lastItem() ?? 0 }} sur {{ $tiers->total() }}
            </div>
            {{ $tiers->links() }}
        </div>
    </div>

    {{-- Bandeau 4 zones (maquette) --}}
    <div class="bg-white border border-gray-200 rounded-[4px] p-3 grid grid-cols-2 lg:grid-cols-4 gap-3 items-center text-center">
        <div>
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total tiers</p>
            <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight">{{ $stats['total'] }}</p>
            <p class="text-[11px] text-gray-400">100% du référentiel filtré</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Clients</p>
            <p class="text-[17px] font-bold tabular-nums text-emerald-700 leading-tight">{{ $stats['clients'] }}</p>
            <p class="text-[11px] text-gray-400">Encours : <span class="font-semibold text-emerald-700">{{ $fmt($stats['clients_enc']) }}</span> XOF</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Fournisseurs</p>
            <p class="text-[17px] font-bold tabular-nums text-amber-600 leading-tight">{{ $stats['fourn'] }}</p>
            <p class="text-[11px] text-gray-400">Encours : <span class="font-semibold text-amber-600">{{ $fmt($stats['fourn_enc']) }}</span> XOF</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Encours global</p>
            <p class="text-[17px] font-bold tabular-nums text-violet-700 leading-tight">{{ $fmt($stats['encours_glob']) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] text-gray-400">Solde global : {{ $fmt($stats['solde_glob']) }} XOF</p>
        </div>
    </div>

    {{-- Barre de contexte pied de page --}}
    @php
        $activeFilters = collect($filters)->filter(fn ($v, $k) => $v && $k !== 'show_inactive')->keys()->implode(', ');
    @endphp
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Filtre actif : <span class="text-white font-semibold">{{ $activeFilters ?: 'Aucun' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
