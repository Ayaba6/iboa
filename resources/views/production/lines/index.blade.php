@extends('layouts.erp')
@section('title', 'Lignes de production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Lignes de production</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Lignes de production</h1>
            <p class="text-[12px] text-gray-500">Postes de fabrication rattachés à une machine</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.lines.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle ligne
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
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $l)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $l->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $l->code }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $l->name }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $l->machine?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $l->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $l->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.lines.edit', $l) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.lines.destroy', $l) }}" class="inline ml-2" data-confirm="Supprimer cette ligne ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune ligne de production.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $lines->total() }} ligne(s)</span>
            @if($lines->hasPages())<div>{{ $lines->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
