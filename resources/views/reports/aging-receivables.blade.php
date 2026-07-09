@extends('layouts.erp')
@section('title', 'Âge des créances')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Âge des créances</span>
@endsection

@section('content')
<div class="space-y-3">

    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Âge des créances clients</h1>
        <p class="text-sm text-gray-500 mt-0.5">Analyse de l'ancienneté des factures impayées</p>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">À la date du</label>
                <input type="date" name="as_of" value="{{ $asOf }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-rose-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-rose-400 bg-white">
                    <option value="">— Tous —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors w-full">
                    Afficher
                </button>
            </div>
        </div>
    </form>

    {{-- Summary buckets --}}
    @php
        $grandTotal = $totals['total'];
        $bucketDefs = [
            ['key' => 'current',  'label' => 'À échoir',       'color' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
            ['key' => '1_30',     'label' => '1 – 30 jours',   'color' => 'bg-amber-400',   'text' => 'text-amber-700',   'bg' => 'bg-amber-50'],
            ['key' => '31_60',    'label' => '31 – 60 jours',  'color' => 'bg-orange-500',  'text' => 'text-orange-700',  'bg' => 'bg-orange-50'],
            ['key' => '61_90',    'label' => '61 – 90 jours',  'color' => 'bg-red-500',     'text' => 'text-red-700',     'bg' => 'bg-red-50'],
            ['key' => 'over_90',  'label' => '+ 90 jours',     'color' => 'bg-red-800',     'text' => 'text-red-800',     'bg' => 'bg-red-100'],
        ];
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @foreach($bucketDefs as $b)
        @php $amt = $totals[$b['key']]; $pct = $grandTotal > 0 ? round($amt / $grandTotal * 100, 1) : 0; @endphp
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">{{ $b['label'] }}</p>
            <p class="text-lg font-black {{ $b['text'] }} tabular-nums">{{ number_format($amt, 0, ',', ' ') }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">FCFA · {{ $pct }}%</p>
            <div class="mt-2 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                <div class="h-full rounded-full {{ $b['color'] }}" style="width:{{ $pct }}%"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Total bar --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-500">Total créances impayées au {{ \Carbon\Carbon::parse($asOf)->format('d/m/Y') }}</p>
            <p class="text-2xl font-black text-gray-900 tabular-nums mt-0.5">{{ number_format($grandTotal, 0, ',', ' ') }} <span class="text-base font-semibold text-gray-500">FCFA</span></p>
        </div>
        <div class="text-sm text-gray-500">
            {{ $invoices->count() }} facture(s) impayée(s) · {{ $byClient->count() }} client(s)
        </div>
    </div>

    {{-- By client table --}}
    @if($byClient->isEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 py-16 text-center text-gray-400 text-sm">
        Aucune créance impayée à cette date.
    </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Détail par client</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] font-bold text-emerald-900 uppercase tracking-wide">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Client</th>
                        <th class="px-3 py-1.5 text-right">À échoir</th>
                        <th class="px-3 py-1.5 text-right">1–30j</th>
                        <th class="px-3 py-1.5 text-right">31–60j</th>
                        <th class="px-3 py-1.5 text-right">61–90j</th>
                        <th class="px-3 py-1.5 text-right">+90j</th>
                        <th class="px-3 py-1.5 text-right font-bold text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($byClient as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $row['client']?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ $row['current'] > 0 ? number_format($row['current'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-amber-700">{{ $row['1_30'] > 0 ? number_format($row['1_30'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-orange-700">{{ $row['31_60'] > 0 ? number_format($row['31_60'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-700">{{ $row['61_90'] > 0 ? number_format($row['61_90'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-800 font-semibold">{{ $row['over_90'] > 0 ? number_format($row['over_90'], 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold text-gray-900">{{ number_format($row['total'], 0, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200 font-bold text-sm">
                    <tr>
                        <td class="px-3 py-1.5 text-gray-700">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ number_format($totals['current'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-amber-700">{{ number_format($totals['1_30'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-orange-700">{{ number_format($totals['31_60'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-700">{{ number_format($totals['61_90'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-800">{{ number_format($totals['over_90'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-900">{{ number_format($totals['total'], 0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
