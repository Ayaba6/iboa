@extends('layouts.erp')
@section('title', 'Rapport de rentabilité analytique')

@section('breadcrumb')
    <a href="{{ route('analytique.centres-couts.index') }}" class="hover:text-gray-700">Centres de coûts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapport de rentabilité</span>
@endsection

@section('content')
<div class="space-y-3">

<div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Rapport de rentabilité analytique</h1>
        <p class="text-sm text-gray-500 mt-0.5">§12 CDC — Analyse des coûts par produit / ligne / usine</p>
    </div>
    <form method="GET" class="flex items-end gap-2">
        <select name="year" class="h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
            @foreach(range(date('Y'), date('Y') - 3) as $y)
            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
        <select name="month" class="h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
            <option value="0" {{ $month == 0 ? 'selected' : '' }}>Toute l'année</option>
            @foreach(range(1, 12) as $m)
            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
            @endforeach
        </select>
        <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Filtrer</button>
    </form>
</div>

{{-- KPIs synthèse --}}
@php
    $totCharges  = $query->sum(fn ($c) => (float) ($c->total_charges ?? 0));
    $totProduits = $query->sum(fn ($c) => abs((float) ($c->total_produits ?? 0)));
    $totSolde    = $totCharges - $totProduits;
    $kpis = [
        ['label' => 'Centres analysés', 'value' => $query->count(),                                          'text' => 'text-gray-900',    'bd' => 'border-gray-300'],
        ['label' => 'Total charges',    'value' => number_format($totCharges, 0, ',', ' ') . ' F',           'text' => 'text-red-700',     'bd' => 'border-red-200'],
        ['label' => 'Total produits',   'value' => number_format($totProduits, 0, ',', ' ') . ' F',          'text' => 'text-emerald-700', 'bd' => 'border-emerald-200'],
        ['label' => $totSolde >= 0 ? 'Solde net (coût)' : 'Solde net (profit)', 'value' => number_format(abs($totSolde), 0, ',', ' ') . ' F', 'text' => $totSolde >= 0 ? 'text-red-800' : 'text-emerald-800', 'bd' => $totSolde >= 0 ? 'border-red-300' : 'border-emerald-300'],
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

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="min-w-full text-[14px] border-collapse">
        <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
            <tr>
                <th class="px-3 py-1.5 text-left">Centre</th>
                <th class="px-3 py-1.5 text-left">Type</th>
                @foreach(\App\Models\AnalyticLine::$categoryLabels as $key => $label)
                <th class="px-3 py-1.5 text-right">{{ Str::limit($label, 10) }}</th>
                @endforeach
                <th class="px-3 py-1.5 text-right">Solde</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $grandTotal = 0; @endphp
            @forelse($query as $center)
            @php
                $charges  = (float) ($center->total_charges ?? 0);
                $produits = abs((float) ($center->total_produits ?? 0));
                $solde    = $charges - $produits;
                $grandTotal += $solde;
            @endphp
            <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                <td class="px-3 py-1">
                    <a href="{{ route('analytique.centres-couts.show', $center) }}" class="font-medium text-blue-600 hover:text-blue-800 font-mono text-[13px]">
                        {{ $center->code }}
                    </a>
                    <span class="block text-[11px] text-gray-500">{{ $center->name }}</span>
                </td>
                <td class="px-3 py-1">
                    <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-gray-100 text-gray-600">{{ $center->typeLabel() }}</span>
                </td>
                @foreach(\App\Models\AnalyticLine::$categoryLabels as $key => $label)
                @php $cat = ($byCategory[$center->id] ?? null)?->firstWhere('category', $key); @endphp
                <td class="px-3 py-1 text-right tabular-nums text-gray-600 text-[12px]">
                    {{ $cat ? number_format(abs($cat->total), 0, ',', ' ') : '—' }}
                </td>
                @endforeach
                <td class="px-3 py-1 text-right font-mono font-bold {{ $solde >= 0 ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ number_format(abs($solde), 0, ',', ' ') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="10" class="px-4 py-12 text-center text-gray-400 text-[13px]">Aucune donnée analytique pour cette période.</td></tr>
            @endforelse
        </tbody>
        @if($query->isNotEmpty())
        <tfoot>
            <tr class="text-white font-bold" style="background:#065f46">
                <td colspan="{{ 2 + count(\App\Models\AnalyticLine::$categoryLabels) }}" class="px-3 py-1.5 text-right text-[11px] uppercase">Total</td>
                <td class="px-3 py-1.5 text-right font-mono tabular-nums">
                    {{ number_format(abs($grandTotal), 0, ',', ' ') }} F
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
    </div>
</div>

</div>
@endsection
