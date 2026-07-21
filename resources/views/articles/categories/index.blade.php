@extends('layouts.erp')
@section('title', 'Catégories d\'article')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Catégories d'article</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Catégories d'article</h1>
            <p class="text-[11.5px] text-gray-400">Modèles de gestion (X3) — déterminent le fonctionnement des articles. Distinctes des familles (classement commercial).</p>
        </div>
        <div class="flex gap-2">
            @can('categories.create')
            <a href="{{ route('articles.categories.create') }}" class="text-[12.5px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-3 py-1.5 rounded-[4px]">+ Nouvelle catégorie</a>
            @endcan
            <a href="{{ route('product-families.index') }}" class="text-[12.5px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-[4px]">Familles</a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="flex flex-wrap items-end gap-2 bg-white border border-gray-300 rounded-[4px] px-3 py-2">
        <div>
            <label class="block text-[10.5px] font-bold text-gray-500 mb-0.5">Recherche</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Code ou intitulé…" class="h-8 border border-gray-300 rounded-[3px] px-2 text-[12.5px] w-48">
        </div>
        <div>
            <label class="block text-[10.5px] font-bold text-gray-500 mb-0.5">Stratégie</label>
            <select name="strategy" class="h-8 py-0 border border-gray-300 rounded-[3px] px-2 text-[12.5px]">
                <option value="">Toutes</option>
                @foreach(['mto'=>'MTO','mts'=>'MTS','achat_revente'=>'Achat-revente','service'=>'Service','conso_interne'=>'Conso interne'] as $v=>$l)
                <option value="{{ $v }}" @selected(request('strategy')===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10.5px] font-bold text-gray-500 mb-0.5">Flux</label>
            <select name="flux" class="h-8 py-0 border border-gray-300 rounded-[3px] px-2 text-[12.5px]">
                <option value="">Tous</option>
                <option value="achete" @selected(request('flux')==='achete')>Acheté</option>
                <option value="vendu" @selected(request('flux')==='vendu')>Vendu</option>
                <option value="fabrique" @selected(request('flux')==='fabrique')>Fabriqué</option>
            </select>
        </div>
        <div>
            <label class="block text-[10.5px] font-bold text-gray-500 mb-0.5">Statut</label>
            <select name="actif" class="h-8 py-0 border border-gray-300 rounded-[3px] px-2 text-[12.5px]">
                <option value="">Tous</option>
                <option value="1" @selected(request('actif')==='1')>Actives</option>
                <option value="0" @selected(request('actif')==='0')>Inactives</option>
            </select>
        </div>
        <button class="h-8 text-[12.5px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 rounded-[3px]">Filtrer</button>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    @foreach(['Code','Intitulé','Nature','Stratégie','Acheté','Vendu','Stocké','Fabriqué','CQ','Articles','Statut',''] as $h)
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($categories as $c)
                <tr class="hover:bg-[#eef5f0]/40 {{ $c->is_active ? '' : 'opacity-50' }}">
                    <td class="px-3 py-1.5"><a href="{{ route('articles.categories.show', $c) }}" class="font-mono font-semibold text-emerald-800 hover:underline">{{ $c->code }}</a></td>
                    <td class="px-3 py-1.5 text-gray-900">{{ $c->name }}</td>
                    <td class="px-3 py-1.5 text-gray-600 text-[12px]">{{ str_replace('_',' ',$c->nature) }}</td>
                    <td class="px-3 py-1.5">@if($c->strategy)<span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $c->strategy==='mto'?'bg-blue-100 text-blue-800':($c->strategy==='mts'?'bg-purple-100 text-purple-800':'bg-gray-100 text-gray-700') }}">{{ $c->strategy }}</span>@else — @endif</td>
                    @foreach(['is_purchasable','is_sellable','is_stockable','is_manufactured','qc_required'] as $flag)
                    <td class="px-3 py-1.5">{!! $c->$flag ? '<span class="text-emerald-600 font-bold">✓</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                    @endforeach
                    <td class="px-3 py-1.5 tabular-nums font-semibold">{{ $c->products_count }}</td>
                    <td class="px-3 py-1.5">{!! $c->is_active ? '<span class="inline-flex px-2 py-0.5 rounded text-[11px] bg-green-100 text-green-800">Active</span>' : '<span class="inline-flex px-2 py-0.5 rounded text-[11px] bg-gray-100 text-gray-600">Inactive</span>' !!}</td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        @can('categories.update')<a href="{{ route('articles.categories.edit', $c) }}" class="text-[12px] text-gray-500 hover:text-emerald-700 mr-2">Modifier</a>@endcan
                        @can('categories.create')
                        <form method="POST" action="{{ route('articles.categories.duplicate', $c) }}" class="inline">@csrf
                            <button class="text-[12px] text-gray-500 hover:text-emerald-700">Dupliquer</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="px-3 py-6 text-center text-gray-400 text-[12.5px]">Aucune catégorie.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
