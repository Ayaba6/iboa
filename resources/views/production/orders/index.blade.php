@extends('layouts.erp')
@section('title', 'Ordres de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Ordres de fabrication</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-2 py-1.5 text-[11px] font-semibold uppercase whitespace-nowrap';
    $originLabels = ['manuel' => 'Manuel', 'commande_client' => 'Cde client', 'stock_minimum' => 'Stock mini', 'mrp' => 'MRP'];
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Ordres de fabrication</h1>
            <p class="text-[12px] text-gray-500">Lancement, suivi &amp; clôture de la production tôle bac</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.orders.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvel OF
        </a>
        @endcan
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 xl:grid-cols-5 gap-3">
        @foreach([
            ['label' => 'Brouillons',      'value' => number_format($stats['brouillon'], 0, ',', ' '),         'color' => 'text-gray-900',    'bg' => 'bg-gray-100'],
            ['label' => 'En production',   'value' => number_format($stats['en_cours'], 0, ',', ' '),          'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
            ['label' => 'En retard',       'value' => number_format($stats['en_retard'], 0, ',', ' '),         'color' => $stats['en_retard'] > 0 ? 'text-red-700' : 'text-gray-900', 'bg' => 'bg-red-50'],
            ['label' => 'Terminés',        'value' => number_format($stats['termine'], 0, ',', ' '),           'color' => 'text-gray-900',    'bg' => 'bg-blue-50'],
            ['label' => 'Mètres produits', 'value' => number_format($stats['metres'], 0, ',', ' ') . ' m',     'color' => 'text-gray-900',    'bg' => 'bg-amber-50'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg style="width:18px;height:18px" class="{{ $kpi['color'] === 'text-emerald-700' ? 'text-emerald-600' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 truncate">{{ $kpi['label'] }}</p>
                <p class="text-[16px] font-bold {{ $kpi['color'] }} tabular-nums leading-tight">{{ $kpi['value'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filtres [X3] --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 space-y-3">
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">N° OF</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="OF-2026-0109" class="{{ $inp }} font-mono">
            </div>
            <div>
                <label class="{{ $lbl }}">Commande</label>
                <input type="text" name="commande" value="{{ request('commande') }}" placeholder="CMD-2026-001" class="{{ $inp }} font-mono">
            </div>
            <div>
                <label class="{{ $lbl }}">Client</label>
                <div class="relative"><select name="client_id" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->trade_name ?? $c->name }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Article fabriqué</label>
                <div class="relative"><select name="product_id" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($produits as $p)<option value="{{ $p->id }}" @selected(request('product_id')==$p->id)>{{ $p->name }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative"><select name="status" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(['brouillon'=>'Brouillon','matiere_allouee'=>'Matière allouée','attente_chef'=>'Attente Chef','attente_responsable'=>'Attente Resp.','lance'=>'Lancé','en_cours'=>'En cours','suspendu'=>'Suspendu','termine_partiellement'=>'Terminé part.','termine'=>'Terminé','annule'=>'Annulé'] as $k=>$v)
                        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Vue</label>
                <div class="relative"><select name="vue" class="{{ $lk }}">
                    <option value="">Standard</option>
                    <option value="en_retard" @selected(request('vue')==='en_retard')>OF en retard</option>
                    <option value="a_lancer" @selected(request('vue')==='a_lancer')>OF à lancer</option>
                    <option value="clotures" @selected(request('vue')==='clotures')>OF clôturés</option>
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Ligne</label>
                <div class="relative"><select name="production_line_id" class="{{ $lk }}">
                    <option value="">Toutes</option>
                    @foreach($lignes as $l)<option value="{{ $l->id }}" @selected(request('production_line_id')==$l->id)>{{ $l->name }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Responsable</label>
                <div class="relative"><select name="responsible_id" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($responsables as $r)<option value="{{ $r->id }}" @selected(request('responsible_id')==$r->id)>{{ $r->name }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Priorité</label>
                <div class="relative"><select name="priorite" class="{{ $lk }}">
                    <option value="">Toutes</option>
                    @foreach(['basse'=>'Basse','normale'=>'Normale','haute'=>'Haute','urgente'=>'Urgente'] as $k=>$v)
                        <option value="{{ $k }}" @selected(request('priorite')===$k)>{{ $v }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Origine</label>
                <div class="relative"><select name="origin" class="{{ $lk }}">
                    <option value="">Toutes</option>
                    @foreach($originLabels as $k=>$v)
                        <option value="{{ $k }}" @selected(request('origin')===$k)>{{ $v }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Fabrication du</label>
                <input type="date" name="from" value="{{ request('from') }}" class="{{ $inp }}">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="{{ $lbl }}">au</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="{{ $inp }}">
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] shrink-0 transition-colors">Rechercher</button>
                @if(request()->hasAny(['q','client_id','status','product_id','commande','production_line_id','responsible_id','priorite','origin','from','to','vue']))
                <a href="{{ route('production.orders.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-3 h-8 rounded-[4px] flex items-center shrink-0 transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table [X3] --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">N° OF</th>
                        <th class="{{ $th }} text-left hidden lg:table-cell">Origine</th>
                        <th class="{{ $th }} text-left hidden lg:table-cell">Commande</th>
                        <th class="{{ $th }} text-left">Client</th>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-right">Qté</th>
                        <th class="{{ $th }} text-right hidden md:table-cell">Produite</th>
                        <th class="{{ $th }} text-right hidden md:table-cell">Reste</th>
                        <th class="{{ $th }} text-right hidden xl:table-cell">Métrage</th>
                        <th class="{{ $th }} text-center hidden xl:table-cell">Priorité</th>
                        <th class="{{ $th }} text-left hidden 2xl:table-cell">Ligne</th>
                        <th class="{{ $th }} text-left hidden xl:table-cell">Prévue</th>
                        <th class="{{ $th }} text-left hidden 2xl:table-cell">Responsable</th>
                        <th class="{{ $th }} text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    @php
                        $reste    = max(0, (float) $o->quantity_requested - (float) $o->quantity_produced);
                        $enRetard = in_array($o->status, ['lance', 'en_cours', 'termine_partiellement'], true)
                                    && $o->date_fin_prevue && $o->date_fin_prevue->isPast();
                    @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $o->status === 'annule' ? 'opacity-50' : '' }}">
                        <td class="px-2 py-1.5 whitespace-nowrap">
                            <a href="{{ route('production.orders.show', $o) }}" class="font-mono text-emerald-800 hover:underline">{{ $o->number }}</a>
                            @if($enRetard)<span class="ml-1 inline-block w-2 h-2 rounded-full bg-red-500" title="En retard"></span>@endif
                        </td>
                        <td class="px-2 py-1.5 text-gray-500 text-[11.5px] hidden lg:table-cell whitespace-nowrap">{{ $originLabels[$o->origin] ?? ($o->origin ?: '—') }}</td>
                        <td class="px-2 py-1.5 hidden lg:table-cell whitespace-nowrap">
                            @if($o->order)<a href="{{ route('ventes.commandes.show', $o->order_id) }}" class="font-mono text-[11.5px] text-blue-700 hover:underline">{{ $o->order->number }}</a>@else<span class="text-gray-300">—</span>@endif
                        </td>
                        <td class="px-2 py-1.5 text-gray-900 max-w-[130px] truncate">{{ $o->client?->trade_name ?? $o->client?->name ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-gray-600 max-w-[170px] truncate" title="{{ $o->product?->name }}">{{ $o->product?->name ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($o->quantity_requested, 0, ',', ' ') }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-emerald-700 hidden md:table-cell">{{ number_format($o->quantity_produced, 0, ',', ' ') }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums hidden md:table-cell {{ $reste > 0 ? 'text-amber-700' : 'text-gray-400' }}">{{ number_format($reste, 0, ',', ' ') }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-gray-600 hidden xl:table-cell">{{ $o->total_meters ? number_format($o->total_meters, 0, ',', ' ') . ' m' : '—' }}</td>
                        <td class="px-2 py-1.5 text-center hidden xl:table-cell">
                            @php [$pl, $pc] = match($o->priorite){
                                'urgente' => ['Urgente', 'bg-red-100 text-red-700'],
                                'haute'   => ['Haute', 'bg-amber-100 text-amber-700'],
                                'basse'   => ['Basse', 'bg-gray-100 text-gray-500'],
                                default   => ['Normale', 'bg-gray-50 text-gray-400'],
                            }; @endphp
                            <span class="inline-flex px-1.5 py-0.5 rounded-[2px] text-[10.5px] font-semibold {{ $pc }}">{{ $pl }}</span>
                        </td>
                        <td class="px-2 py-1.5 text-gray-500 text-[12px] hidden 2xl:table-cell max-w-[100px] truncate">{{ $o->productionLine?->name ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-gray-600 tabular-nums hidden xl:table-cell whitespace-nowrap {{ $enRetard ? 'text-red-600 font-semibold' : '' }}">{{ $o->date_fabrication_prevue?->format('d/m/y') ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-gray-500 text-[12px] hidden 2xl:table-cell max-w-[100px] truncate">{{ $o->responsible?->name ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                @switch($o->status)
                                    @case('brouillon') bg-gray-100 text-gray-600 @break
                                    @case('lance') bg-blue-100 text-blue-700 @break
                                    @case('en_cours') bg-emerald-100 text-emerald-700 @break
                                    @case('suspendu') bg-orange-100 text-orange-700 @break
                                    @case('termine') bg-gray-200 text-gray-700 @break
                                    @case('annule') bg-red-100 text-red-700 @break
                                    @default bg-amber-100 text-amber-700
                                @endswitch">{{ $o->statusLabel() }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="14" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun ordre de fabrication.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $orders->total() }} ordre(s) de fabrication — {{ $stats['en_cours'] }} en production — {{ number_format($stats['metres'], 0, ',', ' ') }} m produits</span>
            @if($orders->hasPages())<div>{{ $orders->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Ordres de fabrication</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
