@extends('layouts.erp')
@section('title', 'Relevé client')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Relevé client</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Relevé client</h1>
            <p class="text-sm text-gray-500 mt-0.5">Extrait de compte par client et période</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            {{-- Boutons export client unique --}}
            @if($client && $dateFrom && $dateTo)
            <a href="{{ route('clients.releve.export-excel', ['client_id' => $clientId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('clients.releve.export-pdf', ['client_id' => $clientId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            @endif
            {{-- Boutons export tous les clients --}}
            @if($clientId === 'all' && $dateFrom && $dateTo)
            <a href="{{ route('clients.releve.export-excel', ['client_id' => 'all', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel tous les clients
            </a>
            <a href="{{ route('clients.releve.export-pdf', ['client_id' => 'all', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF tous les clients
            </a>
            @endif
            <a href="{{ route('clients.balance-agee') }}" class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-200 hover:bg-emerald-50 px-2.5 py-1.5 rounded-[4px] transition-colors">Balance âgée</a>
            <a href="{{ route('clients.grand-livre') }}"  class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-200 hover:bg-emerald-50 px-2.5 py-1.5 rounded-[4px] transition-colors">Grand livre</a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <select name="client_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">— Choisir un client —</option>
                <option value="all" {{ $clientId === 'all' ? 'selected' : '' }}
                        class="font-semibold text-blue-700 bg-blue-50">
                    ★ Tous les clients
                </option>
                <optgroup label="─────────────────">
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
                </optgroup>
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom }}" required
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_to" value="{{ $dateTo }}" required
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Générer
            </button>
        </div>
    </form>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- MODE : TOUS LES CLIENTS                                        --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($allClientsData !== null)

    {{-- Bandeau synthèse --}}
    @php
        $totalClients  = count($allClientsData);
        $clientsActifs = collect($allClientsData)->filter(fn($d) => $d['lines']->count() > 0 || $d['soldeOuv'] != 0)->count();
    @endphp
    <div class="bg-[#f4f9f6] border border-emerald-200 rounded-[4px] p-4 flex flex-wrap gap-6 items-center">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-blue-500 uppercase font-semibold tracking-wider">Relevés générés</p>
                <p class="text-[17px] font-bold text-blue-900">{{ $totalClients }} clients</p>
            </div>
        </div>
        <div>
            <p class="text-xs text-blue-500 uppercase font-semibold tracking-wider">Avec activité</p>
            <p class="font-bold text-blue-800">{{ $clientsActifs }} / {{ $totalClients }}</p>
        </div>
        <div>
            <p class="text-xs text-blue-500 uppercase font-semibold tracking-wider">Période</p>
            <p class="font-bold text-blue-800">
                {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
            </p>
        </div>
    </div>

    {{-- Un bloc par client --}}
    @foreach($allClientsData as $data)
    @php
        $c        = $data['client'];
        $cLines   = $data['lines'];
        $cSoldeOuv = $data['soldeOuv'];
        $hasMouvt = $cLines->count() > 0 || $cSoldeOuv != 0;
        $soldeFin  = $cLines->count() ? $cLines->last()['solde'] : $cSoldeOuv;
    @endphp
    <div class="bg-white rounded-[4px] border {{ $hasMouvt ? 'border-gray-200' : 'border-gray-100 opacity-60' }} overflow-hidden">
        {{-- En-tête client --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-blue-600">{{ strtoupper(substr($c->name, 0, 2)) }}</span>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">{{ $c->name }}</p>
                    @if($c->code)<p class="text-xs text-gray-400">{{ $c->code }}</p>@endif
                </div>
            </div>
            <div class="flex items-center gap-6 text-sm">
                <div class="text-center">
                    <p class="text-xs text-gray-400 uppercase">Solde ouv.</p>
                    <p class="font-semibold {{ $cSoldeOuv >= 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format(abs($cSoldeOuv), 0, ',', ' ') }} F
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-400 uppercase">Mvts</p>
                    <p class="font-semibold text-gray-700">{{ $cLines->count() }}</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-400 uppercase">Solde fin.</p>
                    <p class="font-bold text-base {{ $soldeFin >= 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format(abs($soldeFin), 0, ',', ' ') }} F
                    </p>
                </div>
                <a href="{{ route('clients.releve', ['client_id' => $c->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                   class="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 hover:bg-blue-50 px-2 py-1 rounded-[4px] transition-colors whitespace-nowrap">
                    Détail seul
                </a>
            </div>
        </div>

        @if($cLines->count() || $cSoldeOuv != 0)
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-400 uppercase">Date</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-400 uppercase">Référence</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-400 uppercase">Type</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Échéance</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-gray-400 uppercase">Débit</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-gray-400 uppercase">Crédit</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-gray-400 uppercase">Solde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Solde ouverture --}}
                    <tr class="bg-blue-50/60">
                        <td colspan="4" class="px-4 py-1.5 text-xs font-semibold text-blue-700 uppercase">
                            Solde d'ouverture au {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-1.5 text-right text-xs font-semibold text-blue-800">
                            @if($cSoldeOuv > 0){{ number_format($cSoldeOuv, 0, ',', ' ') }}@else —@endif
                        </td>
                        <td class="px-4 py-1.5 text-right text-xs font-semibold text-blue-800">
                            @if($cSoldeOuv < 0){{ number_format(abs($cSoldeOuv), 0, ',', ' ') }}@else —@endif
                        </td>
                        <td class="px-4 py-1.5 text-right text-xs font-bold text-blue-900">{{ number_format($cSoldeOuv, 0, ',', ' ') }}</td>
                    </tr>
                    @forelse($cLines as $line)
                    <tr class="hover:bg-gray-50 transition-colors {{ $line['type'] === 'avoir' ? 'bg-orange-50/30' : ($line['type'] === 'reglement' ? 'bg-green-50/30' : '') }}">
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5 font-mono font-semibold text-blue-600">{{ $line['reference'] }}</td>
                        <td class="px-3 py-1.5">
                            @if($line['type'] === 'facture')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-blue-100 text-blue-700">Facture</span>
                            @elseif($line['type'] === 'avoir')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-100 text-orange-700">Avoir</span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Règlement</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden md:table-cell text-xs">{{ $line['echeance'] ? \Carbon\Carbon::parse($line['echeance'])->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">{{ $line['debit'] > 0 ? number_format($line['debit'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">{{ $line['credit'] > 0 ? number_format($line['credit'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold {{ $line['solde'] >= 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($line['solde'], 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-4 text-center text-gray-400 text-xs italic">Aucune transaction sur cette période.</td>
                    </tr>
                    @endforelse
                    @if($cLines->count())
                    <tr class="bg-gray-700 text-white text-xs">
                        <td colspan="4" class="px-3 py-1.5 font-bold uppercase">Totaux période</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($cLines->sum('debit'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($cLines->sum('credit'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-sm">{{ number_format($cLines->last()['solde'], 0, ',', ' ') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @else
        <p class="px-5 py-4 text-xs text-gray-400 italic">Aucune activité sur cette période.</p>
        @endif
    </div>
    @endforeach

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- MODE : CLIENT UNIQUE                                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @elseif($client && $dateFrom && $dateTo)
    {{-- Client info bar --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-6">
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Client</p>
            <p class="font-semibold text-gray-900">{{ $client->name }}</p>
            @if($client->code)<p class="text-xs text-gray-400">{{ $client->code }}</p>@endif
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Période</p>
            <p class="font-semibold text-gray-900">
                {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Solde d'ouverture</p>
            <p class="font-semibold {{ $soldeOuv >= 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ number_format(abs($soldeOuv), 0, ',', ' ') }} FCFA
                <span class="text-xs font-normal text-gray-400">({{ $soldeOuv >= 0 ? 'dû' : 'créditeur' }})</span>
            </p>
        </div>
        @if($lines->count())
        <div class="ml-auto">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Solde de clôture</p>
            <p class="font-bold text-lg {{ $lines->last()['solde'] >= 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ number_format(abs($lines->last()['solde']), 0, ',', ' ') }} FCFA
            </p>
        </div>
        @endif
    </div>

    {{-- Table transactions --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Échéance</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="bg-blue-50">
                        <td colspan="4" class="px-3 py-1.5 text-xs font-semibold text-blue-700 uppercase">
                            Solde d'ouverture au {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-1.5 text-right text-sm font-semibold text-blue-800">
                            @if($soldeOuv > 0){{ number_format($soldeOuv, 0, ',', ' ') }}@else —@endif
                        </td>
                        <td class="px-3 py-1.5 text-right text-sm font-semibold text-blue-800">
                            @if($soldeOuv < 0){{ number_format(abs($soldeOuv), 0, ',', ' ') }}@else —@endif
                        </td>
                        <td class="px-3 py-1.5 text-right font-bold text-blue-900">{{ number_format($soldeOuv, 0, ',', ' ') }}</td>
                    </tr>

                    @forelse($lines as $line)
                    <tr class="hover:bg-gray-50 transition-colors
                        {{ $line['type'] === 'facture' ? '' : ($line['type'] === 'avoir' ? 'bg-orange-50/40' : 'bg-green-50/40') }}">
                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</td>
                        <td class="px-3 py-2.5"><span class="font-mono font-semibold text-blue-600">{{ $line['reference'] }}</span></td>
                        <td class="px-3 py-2.5">
                            @if($line['type'] === 'facture')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-blue-100 text-blue-700">Facture</span>
                            @elseif($line['type'] === 'avoir')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-100 text-orange-700">Avoir</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Règlement</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-gray-500 hidden md:table-cell">{{ $line['echeance'] ? \Carbon\Carbon::parse($line['echeance'])->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-900">{{ $line['debit'] > 0 ? number_format($line['debit'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-900">{{ $line['credit'] > 0 ? number_format($line['credit'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-bold {{ $line['solde'] >= 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($line['solde'], 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">Aucune transaction sur cette période.</td>
                    </tr>
                    @endforelse

                    @if($lines->count())
                    <tr class="bg-gray-800 text-white">
                        <td colspan="4" class="px-3 py-1.5 text-xs font-bold uppercase">TOTAUX PÉRIODE</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($lines->sum('debit'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($lines->sum('credit'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-lg">{{ number_format($lines->last()['solde'], 0, ',', ' ') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @elseif(request()->hasAny(['client_id','date_from','date_to']))
    <div class="bg-white rounded-[4px] border border-gray-200 p-12 text-center text-gray-400 text-sm">
        Veuillez sélectionner un client (ou Tous les clients) et une période complète.
    </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-200 p-12 text-center">
        <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="text-gray-500 font-medium">Sélectionnez un client (ou <strong>Tous les clients</strong>) et une période pour générer le relevé</p>
    </div>
    @endif

</div>
@endsection
