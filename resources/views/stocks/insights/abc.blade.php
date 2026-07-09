@extends('layouts.erp')
@section('title', 'Analyse ABC')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Analyse ABC</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
    $criteriaLabel = [
        'valuation' => 'Valorisation (stock × CMP)',
        'rotation'  => 'Rotation (sorties physiques)',
        'ca'        => "Chiffre d'affaires (facturé)",
    ][$criterion] ?? $criterion;

    $unit = $criterion === 'rotation' ? 'unités' : 'FCFA';

    $classColors = [
        'A' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'border' => 'border-emerald-300'],
        'B' => ['bg' => 'bg-amber-100',   'text' => 'text-amber-800',   'border' => 'border-amber-300'],
        'C' => ['bg' => 'bg-gray-100',    'text' => 'text-gray-700',    'border' => 'border-gray-300'],
    ];
@endphp

<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Analyse ABC — Pareto 80/15/5</h1>
            <p class="text-sm text-gray-500">Critère : <strong>{{ $criteriaLabel }}</strong> · {{ $analysis['count'] }} article(s) · total {{ $fmt($analysis['total']) }} {{ $unit }}</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Critère</label>
                <select name="criterion" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    <option value="valuation" {{ $criterion==='valuation'?'selected':'' }}>Valorisation</option>
                    <option value="rotation"  {{ $criterion==='rotation'?'selected':'' }}>Rotation</option>
                    <option value="ca"        {{ $criterion==='ca'?'selected':'' }}>Chiffre d'affaires</option>
                </select>
            </div>
            @if(in_array($criterion, ['rotation', 'ca']))
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Fenêtre (mois)</label>
                <select name="months" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    @foreach([3, 6, 12, 24] as $m)
                    <option value="{{ $m }}" {{ $months==$m?'selected':'' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </form>
    </div>

    {{-- Vue synthétique des classes --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['A', 'B', 'C'] as $cls)
            @php $c = $classColors[$cls]; $data = $analysis['by_class'][$cls]; @endphp
            <div class="bg-white rounded-[4px] border-2 {{ $c['border'] }} p-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full {{ $c['bg'] }} {{ $c['text'] }} flex items-center justify-center text-[17px] font-bold">{{ $cls }}</div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">
                            Classe {{ $cls }}
                            <span class="ml-1 normal-case font-normal">
                                @if($cls==='A') 0 → 80 %
                                @elseif($cls==='B') 80 → 95 %
                                @else 95 → 100 %
                                @endif
                            </span>
                        </p>
                        <p class="text-[16px] font-bold tabular-nums {{ $c['text'] }}">{{ $data['count'] }}</p>
                        <p class="text-xs text-gray-500">articles · {{ $analysis['count'] > 0 ? round($data['count'] / $analysis['count'] * 100, 1) : 0 }} % du catalogue</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 mt-3">
                    Valeur cumulée : <strong>{{ $fmt($data['value']) }} {{ $unit }}</strong>
                    ({{ $analysis['total'] > 0 ? round($data['value'] / $analysis['total'] * 100, 1) : 0 }} %)
                </p>
            </div>
        @endforeach
    </div>

    {{-- Interprétation --}}
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Lecture Pareto</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700 text-xs">
            <li><strong>Classe A</strong> (≈ 20 % des articles, 80 % de la valeur) → <strong>focus stratégique</strong> : suivi quotidien, négo fournisseurs, stock optimal, jamais en rupture.</li>
            <li><strong>Classe B</strong> (≈ 30 % des articles, 15 % de la valeur) → suivi hebdo, niveau de stock standard.</li>
            <li><strong>Classe C</strong> (≈ 50 % des articles, 5 % de la valeur) → suivi minimal, regroupements de commandes, peuvent être déstockés s'ils sont aussi dormants.</li>
        </ul>
    </div>

    {{-- Tableau détaillé --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Classement détaillé</h2>
        </div>
        @if(empty($analysis['rows']))
            <div class="p-8 text-center text-gray-400 text-sm">Aucune donnée pour ce critère / cette fenêtre.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left w-12">#</th>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-right">Quantité</th>
                    <th class="px-3 py-1.5 text-right">Valeur</th>
                    <th class="px-3 py-1.5 text-right">%</th>
                    <th class="px-3 py-1.5 text-right">Cumulé %</th>
                    <th class="px-3 py-1.5 text-center">Classe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($analysis['rows'] as $row)
                    @php $c = $classColors[$row['class']]; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 tabular-nums">{{ $row['rank'] }}</td>
                        <td class="px-3 py-1.5">
                            <a href="{{ route('stocks.show', $row['id']) }}" class="font-mono text-xs text-blue-700 hover:underline">{{ $row['reference'] }}</a>
                            <p class="text-sm text-gray-900">{{ $row['name'] }}</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($row['quantity'] ?? 0, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($row['value']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($row['percent'], 2, ',', ' ') }} %</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 hidden sm:block">
                                    <div class="h-1.5 rounded-full {{ $row['class'] === 'A' ? 'bg-emerald-500' : ($row['class'] === 'B' ? 'bg-amber-500' : 'bg-gray-400') }}" style="width: {{ min(100, $row['cumul_percent']) }}%"></div>
                                </div>
                                <span>{{ number_format($row['cumul_percent'], 2, ',', ' ') }} %</span>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $c['bg'] }} {{ $c['text'] }} text-xs font-bold">{{ $row['class'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endsection
