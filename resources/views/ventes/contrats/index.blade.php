@extends('layouts.erp')
@section('title', 'Contrats commerciaux')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Contrats</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $badges = [
        'brouillon' => 'bg-gray-100 text-gray-600', 'actif' => 'bg-emerald-100 text-emerald-800',
        'suspendu' => 'bg-amber-100 text-amber-700', 'termine' => 'bg-blue-100 text-blue-700', 'annule' => 'bg-red-100 text-red-700',
    ];
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-1.5 flex items-center justify-between">
        <div>
            <h1 class="text-[20px] font-bold text-emerald-900">Contrats commerciaux</h1>
            <p class="text-[11px] text-gray-500">{{ $contracts->total() }} contrat(s) — engagements pluriannuels vente / achat</p>
        </div>
        <a href="{{ route('ventes.contrats.create') }}"
           class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">+ Nouveau contrat</a>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3 py-1.5 rounded-[4px] text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-1.5 rounded-[4px] text-[13px]">{{ session('error') }}</div>
    @endif

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-3 flex flex-wrap items-center gap-2">
        <select name="status" class="h-8 py-0 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white">
            <option value="">— Tous statuts —</option>
            @foreach(['brouillon' => 'Brouillon', 'actif' => 'Actif', 'suspendu' => 'Suspendu', 'termine' => 'Terminé', 'annule' => 'Annulé'] as $val => $label)
            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="N° contrat, description, client…"
               class="flex-1 min-w-[200px] h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white">
        <button type="submit" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">Filtrer</button>
        @if(request()->hasAny(['status', 'search']))
        <a href="{{ route('ventes.contrats.index') }}" class="text-[13px] text-gray-500 hover:text-gray-700 px-2">✕</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left">N° contrat</th>
                <th class="{{ $th }} text-left">Description</th>
                <th class="{{ $th }} text-left">Client / Fournisseur</th>
                <th class="{{ $th }} text-left w-20">Type</th>
                <th class="{{ $th }} text-left w-28">Début</th>
                <th class="{{ $th }} text-left w-28">Fin</th>
                <th class="{{ $th }} text-right w-36">Total HT</th>
                <th class="{{ $th }} text-center w-24">Statut</th>
                <th class="{{ $th }} text-right w-28">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($contracts as $ct)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('ventes.contrats.show', $ct) }}" class="font-mono font-semibold text-emerald-800 hover:underline">{{ $ct->number }}</a>
                        @if($ct->is_framework)<span class="ml-1 inline-flex px-1.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-700 align-middle">cadre</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-gray-700 truncate max-w-[240px]">{{ $ct->description }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $ct->client?->name ?? $ct->supplier?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5"><span class="text-[11px] font-semibold uppercase {{ $ct->contract_type === 'vente' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $ct->contract_type }}</span></td>
                    <td class="px-3 py-1.5 font-mono tabular-nums text-gray-500">{{ $ct->starts_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 font-mono tabular-nums text-gray-500">{{ $ct->ends_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums font-semibold text-gray-800">{{ number_format((float) $ct->total_ht, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badges[$ct->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($ct->status) }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        <a href="{{ route('ventes.contrats.edit', $ct) }}" class="text-[12px] font-semibold text-emerald-700 hover:underline">Modifier</a>
                        @if($ct->status === 'brouillon')
                        <span class="text-gray-300 mx-1">|</span>
                        <form method="POST" action="{{ route('ventes.contrats.destroy', $ct) }}" class="inline"
                              data-confirm="Supprimer le contrat {{ $ct->number }} ?">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[12px] font-medium text-red-400 hover:text-red-600 hover:underline">Supprimer</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-5 py-16 text-center text-gray-400">
                    Aucun contrat — <a href="{{ route('ventes.contrats.create') }}" class="text-emerald-700 hover:underline">créer le premier</a>.
                </td></tr>
                @endforelse
            </tbody>
        </table>
        @if($contracts->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $contracts->links() }}</div>
        @endif
    </div>

</div>
@endsection
