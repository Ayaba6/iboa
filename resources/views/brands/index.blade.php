@extends('layouts.erp')
@section('title', 'Marques')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Marques</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Marques</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $brands->total() }} marque(s) enregistrée(s)</p>
        </div>
        <a href="{{ route('brands.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle marque
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" class="flex gap-2">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Rechercher une marque..."
                   class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-[4px] focus:outline-none focus:ring-1 focus:ring-gray-400 focus:border-transparent">
        </div>
        <button type="submit" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-[4px] transition-colors">
            Filtrer
        </button>
        @if(request('search'))
        <a href="{{ route('brands.index') }}" class="px-3 py-1.5 text-gray-500 hover:text-gray-700 text-sm rounded-[4px] transition-colors">
            Réinitialiser
        </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @if($brands->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            <p class="text-sm font-medium">Aucune marque trouvée</p>
            <p class="text-xs mt-1">Créez votre première marque pour commencer</p>
        </div>
        @else
        <table data-dt="simple" class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th data-sortable class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nom</th>
                    <th data-sortable class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                    <th data-sortable class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Articles</th>
                    <th data-sortable class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($brands as $brand)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $brand->name }}</td>
                    <td class="px-5 py-3 text-gray-500 max-w-xs truncate">{{ $brand->description ?? '—' }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">
                            {{ $brand->products_count }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($brand->is_active)
                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Actif
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Inactif
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('brands.edit', $brand) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-[3px] transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Modifier
                            </a>
                            <form method="POST" action="{{ route('brands.destroy', $brand) }}"
                                  onsubmit="return confirm('Supprimer la marque « {{ addslashes($brand->name) }} » ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1 rounded-md transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Pagination --}}
    @if($brands->hasPages())
    <div class="flex justify-center">
        {{ $brands->links() }}
    </div>
    @endif

</div>
@endsection
