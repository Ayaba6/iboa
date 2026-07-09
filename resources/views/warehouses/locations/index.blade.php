@extends('layouts.erp')
@section('title', 'Emplacements — ' . $warehouse->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.index') }}" class="hover:text-gray-700">Entrepôts</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.show', $warehouse) }}" class="hover:text-gray-700">{{ $warehouse->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Emplacements</span>
@endsection

@section('content')
<div class="space-y-3 max-w-4xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Emplacements</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $warehouse->name }} · {{ $locations->count() }} emplacement(s)</p>
        </div>
        @can('stocks.adjust')
        <a href="{{ route('stocks.warehouses.locations.create', $warehouse) }}"
           class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvel emplacement
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Code</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nom</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Zone</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Allée</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Rack</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Niveau</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Articles</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($locations as $loc)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-1.5">
                        <span class="font-mono text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">{{ $loc->code }}</span>
                    </td>
                    <td class="px-3 py-1.5 font-medium text-gray-900">{{ $loc->name }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $loc->zone ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $loc->aisle ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $loc->rack ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $loc->level ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center tabular-nums text-gray-700">{{ $loc->product_stocks_count }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $loc->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $loc->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @can('stocks.adjust')
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('stocks.warehouses.locations.edit', [$warehouse, $loc]) }}"
                               class="text-xs text-gray-500 hover:text-gray-700 font-medium">Modifier</a>
                            <form method="POST" action="{{ route('stocks.warehouses.locations.destroy', [$warehouse, $loc]) }}"
                                  onsubmit="return confirm('Supprimer cet emplacement ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium">Supprimer</button>
                            </form>
                        </div>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                        Aucun emplacement défini pour cet entrepôt.
                        @can('stocks.adjust')
                        <br>
                        <a href="{{ route('stocks.warehouses.locations.create', $warehouse) }}" class="text-emerald-600 hover:underline text-sm mt-2 inline-block">
                            + Créer le premier emplacement
                        </a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($locations->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $locations->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
