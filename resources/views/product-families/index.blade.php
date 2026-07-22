@extends('layouts.erp')
@section('title', 'Familles de produits')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Familles d'articles</span>
@endsection

@section('content')
@php
    $niveau = $niveau ?? 'categorie';
    $isFam  = $niveau === 'famille';
@endphp

<div class="space-y-3">

    {{-- ═══ Bandeau SAGE X3 (même squelette que fiche/modification) ═══ --}}
    @php $statutToutes = request('statut') === 'toutes'; @endphp
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Familles d'articles</h2>
                <p class="text-[11.5px] text-gray-400">{{ $isFam ? 'Sous-familles rattachées à leur famille parente' : 'Classement commercial et statistique — la gestion relève des catégories' }}</p>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                {{-- Bascule Familles (arborescence) / Sous-familles (à plat) --}}
                <div class="inline-flex rounded-[4px] border border-gray-300 overflow-hidden text-[13px] font-semibold">
                    <a href="{{ route('product-families.index', array_filter(['statut' => request('statut')])) }}"
                       class="px-3 py-2 {{ ! $isFam ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">Familles</a>
                    <a href="{{ route('product-families.index', array_filter(['niveau' => 'famille', 'statut' => request('statut')])) }}"
                       class="px-3 py-2 border-l border-gray-300 {{ $isFam ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">Sous-familles</a>
                </div>
                {{-- Bascule Actives / Toutes (archivées incluses) --}}
                <div class="inline-flex rounded-[4px] border border-gray-300 overflow-hidden text-[13px] font-semibold">
                    <a href="{{ route('product-families.index', array_filter(['niveau' => request('niveau')])) }}"
                       class="px-3 py-2 {{ ! $statutToutes ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">Actives</a>
                    <a href="{{ route('product-families.index', array_filter(['niveau' => request('niveau'), 'statut' => 'toutes'])) }}"
                       class="px-3 py-2 border-l border-gray-300 {{ $statutToutes ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">Toutes</a>
                </div>
                <a href="{{ route('product-families.create') }}"
                   class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">
                    {{ $isFam ? 'Nouvelle sous-famille' : 'Nouvelle famille' }}
                </a>
                <a href="{{ route('articles.categories.index') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">
                    Catégories
                </a>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-[4px] bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-[16px] font-bold text-gray-900">{{ $stats['racines'] }}</p>
                <p class="text-xs text-gray-500">Famille(s)</p>
            </div>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-[4px] bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
            <div>
                <p class="text-[16px] font-bold text-gray-900">{{ $stats['sous'] }}</p>
                <p class="text-xs text-gray-500">Sous-famille(s)</p>
            </div>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-[4px] bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
            </div>
            <div>
                <p class="text-[16px] font-bold text-gray-900">{{ $stats['articles'] }}</p>
                <p class="text-xs text-gray-500">Articles classifiés</p>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @if($isFam)
        {{-- Vue Familles : sous-familles à plat avec catégorie parente --}}
        @if($families->isEmpty())
        <div class="text-center py-16">
            <p class="text-sm font-medium text-gray-600">Aucune sous-famille créée</p>
            <p class="text-xs mt-1 text-gray-400">Créez une sous-famille en la rattachant à sa famille parente.</p>
        </div>
        @else
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#3b4248]">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide">Nom</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide">Code</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide">Famille parente</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-semibold text-white uppercase tracking-wide">Articles</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-semibold text-white uppercase tracking-wide">Statut</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($families as $fam)
                <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('product-families.show', $fam) }}" class="font-medium text-gray-900 hover:text-emerald-700 hover:underline">{{ $fam->name }}</a>
                        @unless($fam->is_active)<span class="text-xs text-gray-400 italic ml-1">(inactif)</span>@endunless
                    </td>
                    <td class="px-3 py-1.5">
                        @if($fam->code)<code class="font-mono text-emerald-800 text-[12px]">{{ $fam->code }}</code>@else<span class="text-gray-300">—</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-gray-600">
                        @if($fam->parent)
                            <a href="{{ route('product-families.show', $fam->parent) }}" class="hover:underline">{{ $fam->parent->name }}</a>
                            @if($fam->parent->code)<code class="font-mono text-[11px] text-gray-400 ml-1">{{ $fam->parent->code }}</code>@endif
                        @else — @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($fam->sub_products_count > 0)
                            <a href="{{ route('products.index', ['family_id' => $fam->id]) }}" class="font-semibold text-emerald-800 tabular-nums hover:underline">{{ $fam->sub_products_count }}</a>
                        @else<span class="text-gray-400 text-xs">0</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($fam->is_active)
                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-green-100"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Actif</span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-0.5 rounded-full"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Inactif</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('product-families.edit', $fam) }}" class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('product-families.destroy', $fam) }}"
                                  onsubmit="return confirm('Supprimer la famille « {{ addslashes($fam->name) }} » ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @elseif($families->isEmpty())
        <div class="text-center py-16">
            <svg class="w-14 h-14 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            <p class="text-sm font-medium text-gray-600">Aucune famille créée</p>
            <p class="text-xs mt-1 text-gray-400">Commencez par créer votre première famille de produits</p>
            <a href="{{ route('product-families.create') }}"
               class="mt-4 inline-flex items-center gap-2 text-sm text-emerald-700 hover:text-emerald-800 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Créer une famille
            </a>
        </div>
        @else
        {{--
            Alpine.js scope fix : x-data doit être sur un ANCÊTRE des éléments qui utilisent x-show.
            Dans un <table>, les <tr> sont siblings — ils ne partagent pas le scope d'un autre <tr>.
            Solution : un <tbody> par famille avec x-data="{ open: true }".
            HTML5 autorise plusieurs <tbody> dans une même <table>.
        --}}
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#3b4248]">
                <tr>
                    <th class="w-12 px-3 py-1.5"></th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide">Nom</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide">Code</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-semibold text-white uppercase tracking-wide">Sous-familles</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-semibold text-white uppercase tracking-wide">Articles</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-semibold text-white uppercase tracking-wide">Statut</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide">Actions</th>
                </tr>
            </thead>

            {{-- Un <tbody> par famille racine — Alpine.js scope correct --}}
            @foreach($families as $family)
            @php $childCount = $family->children->count(); @endphp

            <tbody x-data="{ open: true }" class="divide-y divide-gray-100 border-b border-gray-100">

                {{-- Ligne famille racine --}}
                <tr class="bg-[#f4f9f6] hover:bg-emerald-50/70 transition-colors">
                    <td class="px-3 py-1.5 text-center">
                        @if($childCount > 0)
                        <button type="button" @click="open = !open"
                                class="inline-flex items-center justify-center w-5 h-5 rounded text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 transition-colors"
                                :title="open ? 'Réduire' : 'Développer'">
                            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <a href="{{ route('product-families.show', $family) }}" class="font-semibold text-gray-900 hover:text-emerald-700 hover:underline">{{ $family->name }}</a>
                            @unless($family->is_active)
                                <span class="text-xs text-gray-400 italic">(inactif)</span>
                            @endunless
                        </div>
                        @if($family->description)
                            <p class="text-xs text-gray-400 mt-0.5 ml-6 truncate max-w-xs">{{ $family->description }}</p>
                        @endif
                    </td>
                    <td class="px-3 py-1.5">
                        @if($family->code)
                            <code class="font-mono text-emerald-800 text-[12px]">{{ $family->code }}</code>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($childCount > 0)
                            <span class="font-semibold text-gray-900 tabular-nums">{{ $childCount }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @php $totalFamilyProducts = $family->products_count; @endphp
                        @if($totalFamilyProducts > 0)
                            <a href="{{ route('products.index', ['family_id' => $family->id]) }}"
                               class="font-semibold text-emerald-800 tabular-nums hover:underline"
                               title="Voir les articles">{{ $totalFamilyProducts }}</a>
                        @else
                            <span class="text-gray-400 text-xs">0</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($family->is_active)
                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-green-100">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Actif
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Inactif
                        </span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('product-families.edit', $family) }}"
                               class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                               title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('product-families.destroy', $family) }}"
                                  onsubmit="return confirm('Supprimer la famille « {{ addslashes($family->name) }} » ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                        title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                {{-- Lignes enfants — x-show="open" accède au scope du <tbody> parent ✓ --}}
                @foreach($family->children as $child)
                <tr x-show="open"
                    x-transition:enter="transition-opacity duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="hover:bg-emerald-50/40 transition-colors">
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 text-gray-400 text-xs font-semibold">2</span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-2 pl-5">
                            <svg class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <a href="{{ route('product-families.show', $child) }}" class="text-gray-700 hover:text-emerald-700 hover:underline">{{ $child->name }}</a>
                            @unless($child->is_active)
                                <span class="text-xs text-gray-400 italic">(inactif)</span>
                            @endunless
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        @if($child->code)
                            <code class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded font-mono">{{ $child->code }}</code>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-300">—</td>
                    <td class="px-3 py-2.5 text-center">
                        @if($child->sub_products_count > 0)
                            <a href="{{ route('products.index', ['family_id' => $child->id]) }}"
                               class="font-semibold text-emerald-800 tabular-nums hover:underline"
                               title="Voir les articles">{{ $child->sub_products_count }}</a>
                        @else
                            <span class="text-gray-400 text-xs">0</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($child->is_active)
                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-green-100">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Actif
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Inactif
                        </span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('product-families.edit', $child) }}"
                               class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                               title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('product-families.destroy', $child) }}"
                                  onsubmit="return confirm('Supprimer la sous-famille « {{ addslashes($child->name) }} » ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                        title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach

            </tbody>
            @endforeach

        </table>
        @endif

        @if($families->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $families->links() }}
        </div>
        @endif
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Référentiel : <span class="text-white font-semibold">familles d'articles</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
