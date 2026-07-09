@extends('layouts.erp')
@section('title', 'Rapport de rentabilité analytique')

@section('breadcrumb')
    <a href="{{ route('analytique.centres-couts.index') }}" class="hover:text-gray-700">Centres de coûts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapport de rentabilité</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Rapport de rentabilité analytique</h1>
        <p class="text-sm text-gray-500">§12 CDC — Analyse des coûts par produit / ligne / usine</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="year" class="input-field text-sm">
            @foreach(range(date('Y'), date('Y') - 3) as $y)
            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
        <select name="month" class="input-field text-sm">
            <option value="0" {{ $month == 0 ? 'selected' : '' }}>Toute l'année</option>
            @foreach(range(1, 12) as $m)
            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary text-sm">Filtrer</button>
    </form>
</div>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Centre</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                @foreach(\App\Models\AnalyticLine::$categoryLabels as $key => $label)
                <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">{{ Str::limit($label, 10) }}</th>
                @endforeach
                <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @php $grandTotal = 0; @endphp
            @forelse($query as $center)
            @php
                $charges  = (float) ($center->total_charges ?? 0);
                $produits = abs((float) ($center->total_produits ?? 0));
                $solde    = $charges - $produits;
                $grandTotal += $solde;
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5">
                    <a href="{{ route('analytique.centres-couts.show', $center) }}" class="font-medium text-emerald-700 hover:underline">
                        {{ $center->code }}
                    </a>
                    <span class="block text-xs text-gray-500">{{ $center->name }}</span>
                </td>
                <td class="px-3 py-1.5">
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $center->typeLabel() }}</span>
                </td>
                @foreach(\App\Models\AnalyticLine::$categoryLabels as $key => $label)
                @php $cat = ($byCategory[$center->id] ?? null)?->firstWhere('category', $key); @endphp
                <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 text-xs">
                    {{ $cat ? number_format(abs($cat->total), 0, ',', ' ') : '—' }}
                </td>
                @endforeach
                <td class="px-3 py-1.5 text-right font-mono font-bold {{ $solde >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ number_format(abs($solde), 0, ',', ' ') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="10" class="px-4 py-12 text-center text-gray-400">Aucune donnée analytique pour cette période.</td></tr>
            @endforelse
        </tbody>
        @if($query->isNotEmpty())
        <tfoot class="bg-gray-50 border-t border-gray-200">
            <tr>
                <td colspan="{{ 2 + count(\App\Models\AnalyticLine::$categoryLabels) }}" class="px-3 py-1.5 text-right text-sm font-semibold text-gray-700">Total</td>
                <td class="px-3 py-1.5 text-right font-mono font-bold text-lg {{ $grandTotal >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ number_format(abs($grandTotal), 0, ',', ' ') }} F
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
@endsection
