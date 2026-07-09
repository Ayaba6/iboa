@extends('layouts.erp')
@section('title', 'État des impayés clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Impayés clients</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">État des impayés clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">Factures avec solde restant dû — FCFA</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export Excel
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
               class="inline-flex items-center gap-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Export PDF
            </a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">À la date du</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-emerald-400">
                    <option value="">— Tous les clients —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Appliquer</button>
            <a href="{{ route('reports.impayes') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px]">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nb factures</p>
            <p class="mt-1 text-[17px] font-bold text-emerald-800">{{ $totals['count'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total TTC</p>
            <p class="mt-1 text-[17px] font-bold text-blue-700">{{ number_format($totals['total_ttc'], 0, ',', ' ') }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Déjà réglé</p>
            <p class="mt-1 text-[17px] font-bold text-emerald-700">{{ number_format($totals['paid'], 0, ',', ' ') }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Restant dû</p>
            <p class="mt-1 text-[17px] font-bold text-rose-700">{{ number_format($totals['remaining'], 0, ',', ' ') }} F</p>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-rose-700 text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left font-semibold">N° Facture</th>
                    <th class="px-3 py-1.5 text-center font-semibold">Date émission</th>
                    <th class="px-3 py-1.5 text-center font-semibold">Échéance</th>
                    <th class="px-3 py-1.5 text-left font-semibold">Client</th>
                    <th class="px-3 py-1.5 text-left font-semibold">Téléphone</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Total TTC</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Réglé</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Restant dû</th>
                    <th class="px-3 py-1.5 text-center font-semibold">Retard</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                <tr class="hover:bg-gray-50 {{ $r->jours_retard > 30 ? 'bg-rose-50' : '' }}">
                    <td class="px-3 py-2.5 font-medium text-emerald-800">
                        <a href="{{ route('ventes.factures.show', $r->id) }}" class="hover:underline">{{ $r->number }}</a>
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-600">{{ $r->issued_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 text-center {{ $r->jours_retard > 0 ? 'text-rose-700 font-medium' : 'text-gray-600' }}">
                        {{ $r->due_at?->format('d/m/Y') }}
                    </td>
                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ $r->client?->name ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-gray-500">{{ $r->client?->phone ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($r->total_ttc, 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700">{{ number_format($r->paid_amount, 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-bold text-rose-700">{{ number_format($r->remaining_amount, 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-center">
                        @if($r->jours_retard > 0)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                {{ $r->jours_retard > 60 ? 'bg-rose-200 text-rose-900' : ($r->jours_retard > 30 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ $r->jours_retard }} j
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-blue-100 text-blue-700">
                                À échoir
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-gray-400">Aucune facture impayée</td>
                </tr>
                @endforelse
            </tbody>
            @if($rows->count())
            <tfoot class="bg-rose-900 text-white font-bold">
                <tr>
                    <td class="px-3 py-1.5" colspan="5">TOTAL ({{ $totals['count'] }} facture{{ $totals['count'] > 1 ? 's' : '' }})</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['total_ttc'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['paid'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['remaining'], 0, ',', ' ') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

</div>
@endsection
