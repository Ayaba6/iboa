@extends('layouts.erp')
@section('title', 'Gammes opératoires')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Gammes opératoires</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Gammes opératoires</h1>
            <p class="text-[12px] text-gray-500">Séquences d'opérations par nomenclature — temps &amp; centres de travail</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.routings.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle gamme
        </a>
        @endcan
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Code</th>
                        <th class="{{ $th }} text-left">Nom</th>
                        <th class="{{ $th }} text-left">Nomenclature</th>
                        <th class="{{ $th }} text-right">Opérations</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routings as $r)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $r->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $r->code }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $r->name }}</td>
                        <td class="px-3 py-1.5 text-gray-600 max-w-[260px] truncate">{{ $r->billOfMaterial?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ $r->operations_count }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $r->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')<a href="{{ route('production.routings.edit', $r) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>@endcan
                            @can('production.delete')<form method="POST" action="{{ route('production.routings.destroy', $r) }}" class="inline ml-2" data-confirm="Supprimer cette gamme ?">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button></form>@endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune gamme opératoire.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $routings->total() }} gamme(s)</span>
            @if($routings->hasPages())<div>{{ $routings->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
