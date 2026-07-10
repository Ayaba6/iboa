@extends('layouts.erp')
@section('title', 'Import de données')

@section('breadcrumb')
    <span class="text-gray-500">Paramètres</span>
    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-900 font-semibold">Import de données</span>
@endsection

@section('content')
@php $defaultType = in_array(request('type'), ['products','clients','suppliers']) ? request('type') : 'products'; @endphp
<div class="max-w-2xl mx-auto space-y-3" x-data="{ type: '{{ $defaultType }}' }">

    <div>
        <h1 class="text-[17px] font-bold text-gray-900">Import de données</h1>
        <p class="text-sm text-gray-500 mt-1">Importez vos données depuis un fichier Excel ou CSV.</p>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-6 space-y-3">

        {{-- Type selector --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-3">Type de données</label>
            <div class="grid grid-cols-3 gap-3">
                @foreach([
                    ['products',  'Produits',      'archive',      'emerald'],
                    ['clients',   'Clients',        'user-group',   'blue'],
                    ['suppliers', 'Fournisseurs',   'truck',        'orange'],
                ] as [$val, $label, $icon, $color])
                <label :class="type === '{{ $val }}' ? 'ring-2 ring-{{ $color }}-500 bg-{{ $color }}-50 border-{{ $color }}-200' : 'border-gray-200 hover:border-gray-300'"
                       class="relative flex flex-col items-center gap-2 p-4 rounded-[4px] border cursor-pointer transition-all">
                    <input type="radio" name="type_preview" value="{{ $val }}" x-model="type" class="sr-only">
                    <svg class="w-6 h-6" :class="type === '{{ $val }}' ? 'text-{{ $color }}-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($icon === 'archive')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        @elseif($icon === 'user-group')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        @elseif($icon === 'truck')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM17 12h2l2 2v2h-4v-4z"/>
                        @endif
                    </svg>
                    <span class="text-xs font-semibold" :class="type === '{{ $val }}' ? 'text-{{ $color }}-700' : 'text-gray-600'">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Template download --}}
        <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-[4px] border border-gray-300">
            <svg class="w-8 h-8 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">Télécharger le modèle CSV</p>
                <p class="text-xs text-gray-500 mt-0.5">Remplissez ce modèle avec vos données puis importez-le.</p>
            </div>
            {{-- url() préserve le base path (déploiement sous /iboa/public) --}}
            <a :href="`{{ url('/import/template') }}/${type}`"
               class="px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-100 hover:bg-emerald-200 rounded-[4px] transition-colors">
                Télécharger
            </a>
        </div>

        {{-- Upload form --}}
        <form action="{{ route('import.process') }}" method="POST" enctype="multipart/form-data" data-turbo="false" class="space-y-4">
            @csrf
            <input type="hidden" name="type" :value="type">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Fichier (Excel ou CSV)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv"
                       class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-[4px] file:border-0 file:text-sm file:font-semibold file:bg-[#eef5f0] file:text-emerald-800 hover:file:bg-emerald-100 border border-gray-300 rounded-[4px] p-1 cursor-pointer">
                @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="p-4 bg-amber-50 rounded-[4px] border border-amber-200 text-xs text-amber-800 space-y-1">
                <p class="font-semibold">⚠ Règles d'import :</p>
                <ul class="list-disc list-inside space-y-0.5 text-amber-700">
                    <li>La première ligne doit contenir les en-têtes (voir modèle)</li>
                    <li>Les lignes avec un nom vide sont ignorées</li>
                    <li>Si la référence/code existe déjà, la ligne est mise à jour</li>
                    <li>Taille max : 5 Mo</li>
                </ul>
            </div>

            <button type="submit"
                    class="w-full py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-[4px] transition-colors shadow-sm">
                Importer
            </button>
        </form>
    </div>

    {{-- Export section --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-6 space-y-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">Export de données</h2>
            <p class="text-xs text-gray-500 mt-0.5">Téléchargez vos données au format Excel (.xlsx)</p>
        </div>

        @can('invoices.view')
        <div class="border border-gray-300 rounded-[4px] p-4 space-y-3">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="text-sm font-semibold text-gray-800">Factures</span>
            </div>
            <form action="{{ route('exports.invoices') }}" method="GET" class="grid grid-cols-2 gap-2">
                <input type="date" name="date_from" placeholder="Du" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs">
                <input type="date" name="date_to"   placeholder="Au" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs">
                <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs col-span-2">
                    <option value="">Tous les statuts</option>
                    <option value="emise">Émise</option>
                    <option value="envoyee">Envoyée</option>
                    <option value="partiellement_payee">Partiellement payée</option>
                    <option value="payee">Payée</option>
                    <option value="en_retard">En retard</option>
                    <option value="annulee">Annulée</option>
                </select>
                <button type="submit" class="col-span-2 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-[4px] transition-colors flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exporter Excel
                </button>
            </form>
        </div>
        @endcan

        @can('clients.view')
        <div class="border border-gray-300 rounded-[4px] p-4 space-y-3">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="text-sm font-semibold text-gray-800">Clients</span>
            </div>
            <form action="{{ route('exports.clients') }}" method="GET" class="grid grid-cols-2 gap-2">
                <select name="is_active" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs">
                    <option value="">Tous les statuts</option>
                    <option value="1">Actifs</option>
                    <option value="0">Inactifs</option>
                </select>
                <select name="type" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs">
                    <option value="">Tous les types</option>
                    <option value="entreprise">Entreprise</option>
                    <option value="particulier">Particulier</option>
                </select>
                <button type="submit" class="col-span-2 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-[4px] transition-colors flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exporter Excel
                </button>
            </form>
        </div>
        @endcan

        @can('products.view')
        <div class="border border-gray-300 rounded-[4px] p-4 space-y-3">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <span class="text-sm font-semibold text-gray-800">Produits</span>
            </div>
            <form action="{{ route('exports.products') }}" method="GET">
                <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-[4px] transition-colors flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exporter Excel (tous produits actifs)
                </button>
            </form>
        </div>
        @endcan

        @can('stocks.view')
        <div class="border border-gray-300 rounded-[4px] p-4 space-y-3">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
                <span class="text-sm font-semibold text-gray-800">Mouvements de stock</span>
            </div>
            <form action="{{ route('exports.stock-movements') }}" method="GET" class="grid grid-cols-2 gap-2">
                <input type="date" name="date_from" placeholder="Du" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs">
                <input type="date" name="date_to"   placeholder="Au" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs">
                <button type="submit" class="col-span-2 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-[4px] transition-colors flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exporter Excel
                </button>
            </form>
        </div>
        @endcan
    </div>
</div>
@endsection
