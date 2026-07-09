@extends('layouts.erp')
@section('title', 'Fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Fournisseurs</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-2 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1">
            <p class="text-xs text-gray-500">Total fournisseurs</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1">
            <p class="text-xs text-gray-500">Actifs</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $summary['active'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $suppliers->total() }} fournisseur(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('exports.suppliers', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('exports.suppliers-pdf', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('import.index', ['type' => 'suppliers']) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-[4px] text-[12px] font-semibold hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4 0l4-4m0 0l4 4m-4-4V4"/>
                </svg>
                Importer
            </a>
            <a href="{{ route('suppliers.create') }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau fournisseur
            </a>
        </div>
    </div>

    {{-- Reports quick links --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach([
            ['route' => 'suppliers.releve',            'label' => 'Relevé',           'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['route' => 'suppliers.balance',           'label' => 'Balance',          'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['route' => 'suppliers.balance-agee',      'label' => 'Balance âgée',     'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'suppliers.factures-impayees', 'label' => 'Fact. impayées',   'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['route' => 'suppliers.journal-achats',    'label' => 'Journal achats',   'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            ['route' => 'suppliers.grand-livre',       'label' => 'Grand livre',      'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ] as $item)
        <a href="{{ route($item['route']) }}" class="flex flex-col items-center gap-2 bg-white border border-amber-200 hover:border-amber-400 hover:bg-amber-50 rounded-[4px] p-3 text-center transition-colors group">
            <svg class="w-6 h-6 text-amber-700 group-hover:text-amber-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/>
            </svg>
            <span class="text-xs font-medium text-amber-800">{{ $item['label'] }}</span>
        </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Nom, code, téléphone, email..."
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

            <select name="is_active" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>Actif</option>
                <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>Inactif</option>
            </select>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(array_filter($filters ?? []))
                <a href="{{ route('suppliers.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] border-b border-gray-300">
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Code</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nom</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Email</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Téléphone</th>
                        <th class="px-3 py-1 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden sm:table-cell">Contacts</th>
                        <th class="px-3 py-1 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Solde dû</th>
                        <th class="px-3 py-1 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($suppliers as $supplier)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-emerald-800">
                            {{ $supplier->code ?: '—' }}
                        </td>
                        <td class="px-3 py-1">
                            <a href="{{ route('suppliers.show', $supplier) }}"
                               class="font-medium text-gray-900 hover:text-emerald-700 transition-colors">
                                {{ $supplier->name }}
                            </a>
                            @if($supplier->city)
                                <p class="text-xs text-gray-400">{{ $supplier->city }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-sm text-gray-600 hidden md:table-cell">
                            @if($supplier->email)
                                <a href="mailto:{{ $supplier->email }}" class="hover:text-emerald-700 transition-colors">
                                    {{ $supplier->email }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-sm text-gray-600 hidden lg:table-cell">
                            {{ $supplier->phone ?: ($supplier->phone2 ?: '—') }}
                        </td>
                        <td class="px-3 py-1 text-center hidden sm:table-cell">
                            @if($supplier->contacts_count > 0)
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                    {{ $supplier->contacts_count }}
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-right text-sm font-medium tabular-nums hidden lg:table-cell {{ ($supplier->balance ?? 0) > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ ($supplier->balance ?? 0) > 0 ? number_format($supplier->balance, 0, ',', ' ').' FCFA' : '—' }}
                        </td>
                        <td class="px-3 py-1 text-center">
                            @if($supplier->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-green-100 text-green-700 border border-green-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Actif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-gray-100 text-gray-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactif
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('suppliers.show', $supplier) }}"
                                   class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                   title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('suppliers.edit', $supplier) }}"
                                   class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                                   title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST"
                                      onsubmit="return confirm('Supprimer le fournisseur « {{ addslashes($supplier->name) }} » ?')">
                                    @csrf
                                    @method('DELETE')
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
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <p class="text-sm font-medium">Aucun fournisseur trouvé</p>
                                @if(array_filter($filters ?? []))
                                    <a href="{{ route('suppliers.index') }}" class="text-emerald-700 hover:text-emerald-800 text-sm">Effacer les filtres</a>
                                @else
                                    <a href="{{ route('suppliers.create') }}" class="text-emerald-700 hover:text-emerald-800 text-sm">Créer le premier fournisseur</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $suppliers->total() }} fournisseur(s)</span>
            @if($suppliers->hasPages())<div>{{ $suppliers->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
