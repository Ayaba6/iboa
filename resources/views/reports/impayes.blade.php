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
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1">À la date du</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div class="w-72">
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                    <option value="">— Tous les clients —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Appliquer</button>
            <a href="{{ route('reports.impayes') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">Réinitialiser</a>
            <a href="{{ route('clients.balance-agee') }}" class="h-8 flex items-center text-emerald-700 hover:text-emerald-900 border border-emerald-200 hover:bg-emerald-50 text-[12px] px-2.5 rounded-[4px] ml-auto">Balance âgée →</a>
        </div>
    </form>

    {{-- KPIs --}}
    @php
        $kpis = [
            ['label' => 'Nb factures', 'value' => number_format($totals['count'], 0, ',', ' '),             'text' => 'text-gray-900',    'bd' => 'border-gray-300'],
            ['label' => 'Total TTC',   'value' => number_format($totals['total_ttc'], 0, ',', ' ') . ' F',  'text' => 'text-blue-700',    'bd' => 'border-blue-200'],
            ['label' => 'Déjà réglé',  'value' => number_format($totals['paid'], 0, ',', ' ') . ' F',       'text' => 'text-emerald-700', 'bd' => 'border-emerald-200'],
            ['label' => 'Restant dû',  'value' => number_format($totals['remaining'], 0, ',', ' ') . ' F',  'text' => 'text-red-800',     'bd' => 'border-red-300'],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-1.5">
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-[4px] border {{ $kpi['bd'] }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
            <p class="mt-0.5 text-[17px] font-bold {{ $kpi['text'] }} tabular-nums leading-none">{{ $kpi['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-[14px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">N° Facture</th>
                    <th class="px-3 py-1.5 text-center">Émission</th>
                    <th class="px-3 py-1.5 text-center">Échéance</th>
                    <th class="px-3 py-1.5 text-left">Client</th>
                    <th class="px-3 py-1.5 text-left">Téléphone</th>
                    <th class="px-3 py-1.5 text-right">Total TTC</th>
                    <th class="px-3 py-1.5 text-right">Réglé</th>
                    <th class="px-3 py-1.5 text-right">Restant dû</th>
                    <th class="px-3 py-1.5 text-center">Retard</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $r->jours_retard > 30 ? '!bg-red-50/60' : '' }}">
                    <td class="px-3 py-1 font-medium">
                        <a href="{{ route('ventes.factures.show', $r->id) }}" class="text-blue-600 hover:text-blue-800">{{ $r->number }}</a>
                    </td>
                    <td class="px-3 py-1 text-center text-gray-600 tabular-nums">{{ $r->issued_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-1 text-center tabular-nums {{ $r->jours_retard > 0 ? 'text-red-700 font-medium' : 'text-gray-600' }}">
                        {{ $r->due_at?->format('d/m/Y') }}
                    </td>
                    <td class="px-3 py-1 font-medium text-gray-800">{{ $r->client?->name ?? '—' }}</td>
                    <td class="px-3 py-1 text-gray-500 tabular-nums">{{ $r->client?->phone ?? '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-700">{{ number_format($r->total_ttc, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1 text-right tabular-nums text-emerald-700">{{ number_format($r->paid_amount, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-bold text-red-800">{{ number_format($r->remaining_amount, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1 text-center">
                        @if($r->jours_retard > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-bold
                                {{ $r->jours_retard > 60 ? 'bg-red-200 text-red-900' : ($r->jours_retard > 30 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ $r->jours_retard }} j
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-blue-100 text-blue-700">À échoir</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-gray-400 text-[13px]">Aucune facture impayée</td>
                </tr>
                @endforelse
            </tbody>
            @if($rows->count())
            <tfoot>
                <tr class="text-white font-bold" style="background:#065f46">
                    <td class="px-3 py-1.5 text-[11px] uppercase" colspan="5">TOTAL ({{ $totals['count'] }} facture{{ $totals['count'] > 1 ? 's' : '' }})</td>
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

</div>
@endsection
