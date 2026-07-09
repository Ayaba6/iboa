@extends('layouts.erp')
@section('title', 'Effets de commerce')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Effets de commerce</span>
@endsection

@section('content')
<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Effets de commerce</h1>
        @can('treasury.write')
        <a href="{{ route('tresorerie.effets.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvel effet
        </a>
        @endcan
    </div>

    {{-- KPI portefeuille --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">À recevoir (en cours)</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums mt-1">{{ number_format($stats['a_recevoir'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">À payer (en cours)</p>
            <p class="text-[16px] font-bold text-red-600 tabular-nums mt-1">{{ number_format($stats['a_payer'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">En attente</p>
            <p class="text-[16px] font-bold text-amber-600 mt-1">{{ $stats['en_attente'] }}</p>
        </div>
        <div class="rounded-[4px] border shadow-sm p-4 {{ $stats['echus'] > 0 ? 'bg-red-50 border-red-200' : 'bg-white border-gray-100' }}">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Échus non dénoués</p>
            <p class="text-[16px] font-bold {{ $stats['echus'] > 0 ? 'text-red-700' : 'text-gray-400' }} mt-1">{{ $stats['echus'] }}</p>
        </div>
    </div>

    {{-- Alerts: due soon --}}
    @if($upcomingDue->isNotEmpty())
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4">
        <p class="text-sm font-semibold text-amber-800 mb-2">⚠ {{ $upcomingDue->count() }} effet(s) à échéance dans les 7 prochains jours</p>
        <div class="flex flex-wrap gap-2">
            @foreach($upcomingDue as $eff)
            <a href="{{ route('tresorerie.effets.show', $eff) }}"
               class="text-xs bg-amber-100 border border-amber-300 text-amber-800 px-2 py-1 rounded-[4px] hover:bg-amber-200">
                {{ $eff->number }} · {{ number_format($eff->amount, 0, ',', ' ') }} FCFA · ech. {{ $eff->due_date?->format('d/m/Y') }}
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <select name="type" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous types</option>
                <option value="cheque"       {{ ($filters['type'] ?? '') === 'cheque'       ? 'selected' : '' }}>Chèque</option>
                <option value="lcr"          {{ ($filters['type'] ?? '') === 'lcr'          ? 'selected' : '' }}>LCR</option>
                <option value="billet_ordre" {{ ($filters['type'] ?? '') === 'billet_ordre' ? 'selected' : '' }}>Billet à ordre</option>
                <option value="traite"       {{ ($filters['type'] ?? '') === 'traite'       ? 'selected' : '' }}>Traite</option>
            </select>
            <select name="direction" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Toutes directions</option>
                <option value="a_recevoir" {{ ($filters['direction'] ?? '') === 'a_recevoir' ? 'selected' : '' }}>À recevoir</option>
                <option value="a_payer"    {{ ($filters['direction'] ?? '') === 'a_payer'    ? 'selected' : '' }}>À payer</option>
            </select>
            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous statuts</option>
                <option value="en_attente"  {{ ($filters['status'] ?? '') === 'en_attente'  ? 'selected' : '' }}>En attente</option>
                <option value="accepte"     {{ ($filters['status'] ?? '') === 'accepte'     ? 'selected' : '' }}>Accepté</option>
                <option value="remis_banque"{{ ($filters['status'] ?? '') === 'remis_banque'? 'selected' : '' }}>Remis banque</option>
                <option value="encaisse"    {{ ($filters['status'] ?? '') === 'encaisse'    ? 'selected' : '' }}>Encaissé</option>
                <option value="rejete"      {{ ($filters['status'] ?? '') === 'rejete'      ? 'selected' : '' }}>Rejeté</option>
                <option value="proteste"    {{ ($filters['status'] ?? '') === 'proteste'    ? 'selected' : '' }}>Protesté</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" placeholder="Éch. début"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" placeholder="Éch. fin"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="N°, réf, tireur..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Filtrer</button>
            @if(array_filter($filters))
            <a href="{{ route('tresorerie.effets.index') }}" class="border border-gray-300 text-gray-600 text-sm px-2.5 py-1.5 rounded-[4px]">✕</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N°</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Direction</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Tiers</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Émission</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Échéance</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($effects as $eff)
                    @php
                        $statusColors = [
                            'en_attente'  => 'bg-yellow-100 text-yellow-700',
                            'accepte'     => 'bg-blue-100 text-blue-700',
                            'remis_banque'=> 'bg-emerald-100 text-emerald-800',
                            'encaisse'    => 'bg-green-100 text-green-700',
                            'rejete'      => 'bg-red-100 text-red-700',
                            'proteste'    => 'bg-red-100 text-red-700',
                            'annule'      => 'bg-gray-100 text-gray-500',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors {{ $eff->isDue() ? 'bg-red-50' : '' }}">
                        <td class="px-3 py-1.5 font-mono font-semibold text-emerald-700">{{ $eff->number }}</td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $eff->typeLabel() }}</td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $eff->direction === 'a_recevoir' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $eff->directionLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $eff->client?->name ?? $eff->supplier?->name ?? $eff->drawer ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $eff->issue_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5 text-xs {{ $eff->isDue() ? 'font-bold text-red-600' : 'text-gray-500' }}">
                            {{ $eff->due_date?->format('d/m/Y') ?? '—' }}
                            @if($eff->isDue())<span class="ml-1">⚠</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-800">{{ number_format($eff->amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusColors[$eff->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $eff->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ route('tresorerie.effets.show', $eff) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-16 text-center text-gray-400">Aucun effet de commerce.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($effects->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $effects->links() }}</div>
        @endif
    </div>
</div>
@endsection
