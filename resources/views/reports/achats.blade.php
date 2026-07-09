@extends('layouts.erp')
@section('title', 'Rapport Achats')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Achats fournisseurs</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Rapport Achats fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">Analyse des factures fournisseurs par période — FCFA</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Fournisseur</label>
                <select name="supplier_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="">— Tous —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Grouper par</label>
                <div class="flex gap-2">
                    <select name="group_by" class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400 bg-white">
                        <option value="month" {{ $groupBy === 'month' ? 'selected' : '' }}>Mois</option>
                        <option value="week"  {{ $groupBy === 'week'  ? 'selected' : '' }}>Semaine</option>
                        <option value="day"   {{ $groupBy === 'day'   ? 'selected' : '' }}>Jour</option>
                    </select>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        Afficher
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @php
            $kpis = [
                ['label' => 'Factures',          'value' => number_format($totals->nb_factures ?? 0, 0, ',', ' '), 'unit' => 'docs'],
                ['label' => 'Total HT',           'value' => number_format($totals->total_ht ?? 0, 0, ',', ' '),    'unit' => 'FCFA'],
                ['label' => 'Total TVA',          'value' => number_format($totals->total_tva ?? 0, 0, ',', ' '),   'unit' => 'FCFA'],
                ['label' => 'Total TTC',          'value' => number_format($totals->total_ttc ?? 0, 0, ',', ' '),   'unit' => 'FCFA'],
                ['label' => 'Total payé',         'value' => number_format($totals->total_paye ?? 0, 0, ',', ' '),  'unit' => 'FCFA'],
                ['label' => 'Reste à payer',      'value' => number_format($totals->total_reste ?? 0, 0, ',', ' '), 'unit' => 'FCFA'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ $kpi['label'] }}</p>
            <p class="text-lg font-black text-gray-900 tabular-nums mt-1">{{ $kpi['value'] }}</p>
            <p class="text-[10px] text-gray-400">{{ $kpi['unit'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Time series table --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">Évolution des achats</h2>
            </div>
            @if($serie->isEmpty())
            <div class="py-12 text-center text-gray-400 text-sm">Aucune donnée pour cette période.</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] font-bold text-emerald-900 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-1.5 text-left">Période</th>
                            <th class="px-3 py-1.5 text-right">Nbre</th>
                            <th class="px-3 py-1.5 text-right">Total HT</th>
                            <th class="px-3 py-1.5 text-right">Total TTC</th>
                            <th class="px-3 py-1.5 text-right">Payé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($serie as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2.5 font-medium text-gray-800">{{ $row->label }}</td>
                            <td class="px-3 py-2.5 text-right text-gray-600 tabular-nums">{{ $row->nb }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($row->ht, 0, ',', ' ') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($row->ttc, 0, ',', ' ') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-green-700">{{ number_format($row->paye, 0, ',', ' ') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 font-bold text-sm">
                        <tr>
                            <td class="px-3 py-2.5 text-gray-700">Total</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ $serie->sum('nb') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-900">{{ number_format($serie->sum('ht'), 0, ',', ' ') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-900">{{ number_format($serie->sum('ttc'), 0, ',', ' ') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-green-700">{{ number_format($serie->sum('paye'), 0, ',', ' ') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>

        {{-- Top fournisseurs --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">Top fournisseurs</h2>
            </div>
            @if($topSuppliers->isEmpty())
            <div class="py-12 text-center text-gray-400 text-sm">Aucune donnée.</div>
            @else
            @php $maxS = $topSuppliers->max('total'); @endphp
            <div class="p-4 space-y-3">
                @foreach($topSuppliers as $i => $ts)
                @php $pct = $maxS > 0 ? round(($ts->total / $maxS) * 100) : 0; @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700 truncate max-w-[55%]">{{ $ts->supplier?->name ?? '#'.$ts->supplier_id }}</span>
                        <div class="text-right">
                            <span class="text-xs font-bold text-gray-900 tabular-nums">{{ number_format($ts->total, 0, ',', ' ') }}</span>
                            <span class="text-[10px] text-gray-400 ml-1">{{ $ts->nb }} fact.</span>
                        </div>
                    </div>
                    <div class="bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full rounded-full bg-orange-500" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
