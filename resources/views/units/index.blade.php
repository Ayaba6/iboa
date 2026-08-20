@extends('layouts.erp')
@section('title', 'Unités de mesure')

{{-- [Perf] DataTables n'est chargé QUE par les vues portant un `data-dt`.
     Auparavant bundlé dans app.js, il pesait sur chaque page de l'ERP — dont les
     écrans de saisie qui n'ont aucun tableau. --}}
@push('head_scripts')
    @vite('resources/js/datatables.js')
@endpush


@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Unités de mesure</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Unités de mesure</h1>
            <p class="text-[11.5px] text-gray-500">{{ $units->total() }} unité(s) — appliquées aux articles et documents</p>
        </div>
        <a href="{{ route('units.create') }}"
           class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">+ Nouvelle unité</a>
    </div>

    {{-- Filtre --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher par nom ou abréviation…"
                   class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if(request('search'))
            <a href="{{ route('units.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table data-dt="simple" data-col-filter class="w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th data-sortable class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Code</th>
                    <th data-sortable class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nom</th>
                    <th data-sortable class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Abréviation</th>
                    <th data-sortable class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                    <th data-sortable class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Décimales</th>
                    <th data-sortable class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($units as $unit)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-mono text-emerald-800 font-semibold">{{ $unit->code ?? '—' }}</td>
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $unit->name }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center bg-emerald-50 text-emerald-800 text-xs font-mono font-bold px-2.5 py-1 rounded-[4px]">
                            {{ $unit->abbreviation }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600 capitalize">
                        @php
                            $typeLabels = [
                                'quantite' => 'Quantité', 'poids' => 'Poids', 'volume' => 'Volume',
                                'longueur' => 'Longueur', 'surface' => 'Surface', 'temps' => 'Temps', 'autre' => 'Autre'
                            ];
                        @endphp
                        {{ $typeLabels[$unit->type] ?? ($unit->type ?? '—') }}
                    </td>
                    <td class="px-5 py-3 text-center text-gray-600">{{ $unit->decimal_places ?? 2 }}</td>
                    <td class="px-5 py-3 text-center">
                        @if($unit->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">Inactif</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('units.edit', $unit) }}"
                               class="p-1.5 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors"
                               title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form action="{{ route('units.destroy', $unit) }}" method="POST"
                                  onsubmit="return confirm('Supprimer l\'unité {{ addslashes($unit->name) }} ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
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
                    <td colspan="7" class="px-5 py-16 text-center text-gray-400 text-sm">
                        Aucune unité de mesure trouvée.
                        <a href="{{ route('units.create') }}" class="text-emerald-700 hover:underline ml-1">Créer la première</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($units->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $units->links() }}</div>
        @endif
    </div>
</div>
@endsection
