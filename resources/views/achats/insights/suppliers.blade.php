@extends('layouts.erp')
@section('title', 'Évaluation fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.dashboard') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Évaluation</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
    $gradeColors = [
        'A' => ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','border'=>'border-emerald-300'],
        'B' => ['bg'=>'bg-blue-100',   'text'=>'text-blue-800',   'border'=>'border-blue-300'],
        'C' => ['bg'=>'bg-amber-100',  'text'=>'text-amber-800',  'border'=>'border-amber-300'],
        'D' => ['bg'=>'bg-orange-100', 'text'=>'text-orange-800', 'border'=>'border-orange-300'],
        'E' => ['bg'=>'bg-red-100',    'text'=>'text-red-800',    'border'=>'border-red-300'],
    ];
@endphp

<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">📊 Évaluation fournisseurs</h1>
            <p class="text-[13px] text-gray-500">Scorecard sur les {{ $months }} derniers mois — service, délais, retours, volume.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">Période (mois)</label>
                <select name="months" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px]">
                    @foreach([3, 6, 12, 24, 36] as $m)
                    <option value="{{ $m }}" {{ $months==$m?'selected':'' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-[13px] text-blue-800">
        <p class="font-medium mb-1">💡 Méthodologie de notation</p>
        <ul class="list-disc list-inside text-blue-700 text-[12px] space-y-0.5">
            <li><strong>Taux de service (50%)</strong> = qté reçue / qté commandée. Mesure la fiabilité de livraison.</li>
            <li><strong>Respect des délais (30%)</strong> = écart entre délai réel et délai promis. ≤ 0 j = parfait.</li>
            <li><strong>Taux de retour (20%)</strong> = montant des retours / montant commandé. Mesure la qualité.</li>
            <li><strong>Score 0-100</strong> · Grades : <strong>A</strong> ≥90 · <strong>B</strong> ≥75 · <strong>C</strong> ≥60 · <strong>D</strong> ≥40 · <strong>E</strong> &lt; 40</li>
        </ul>
    </div>

    @if($scorecards->isEmpty())
        <div class="bg-gray-50 border border-gray-300 rounded-[4px] p-8 text-center text-gray-500 text-[13px]">
            Aucune donnée d'achat sur les {{ $months }} derniers mois.
        </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-right">Volume</th>
                    <th class="px-3 py-1.5 text-right">PO</th>
                    <th class="px-3 py-1.5 text-right">Service</th>
                    <th class="px-3 py-1.5 text-right">Délai (vs promis)</th>
                    <th class="px-3 py-1.5 text-right">Retour</th>
                    <th class="px-3 py-1.5 text-right">Dû actuel</th>
                    <th class="px-3 py-1.5 text-right">Score</th>
                    <th class="px-3 py-1.5 text-center">Grade</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($scorecards as $s)
                @php $g = $gradeColors[$s->grade ?? 'C']; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5">
                        <p class="text-[13px] font-medium text-gray-900">{{ $s->name }}</p>
                        <p class="text-[12px] text-gray-400 font-mono">{{ $s->code }}</p>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($s->po_volume ?? 0) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $s->po_count ?? 0 }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">
                        @if($s->service_rate !== null)
                            <span class="font-medium {{ $s->service_rate >= 95 ? 'text-emerald-700' : ($s->service_rate >= 85 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ number_format($s->service_rate, 1, ',', ' ') }}%
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right text-[12px]">
                        @if($s->avg_real_delay !== null)
                            <span class="text-gray-700">{{ round($s->avg_real_delay, 1) }} j</span>
                            @if($s->delay_gap !== null)
                                @if($s->delay_gap <= 0)
                                    <span class="text-emerald-600 ml-1">(à l'heure)</span>
                                @else
                                    <span class="text-red-600 ml-1">(+{{ $s->delay_gap }} j)</span>
                                @endif
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums">
                        @if($s->return_rate > 0)
                            <span class="{{ $s->return_rate <= 1 ? 'text-emerald-700' : ($s->return_rate <= 3 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ number_format($s->return_rate, 2, ',', ' ') }}%
                            </span>
                        @else
                            <span class="text-emerald-700">0%</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ ($s->outstanding ?? 0) > 0 ? 'text-orange-700 font-medium' : 'text-gray-400' }}">
                        {{ $fmt($s->outstanding ?? 0) }}
                        @if($s->overdue_count > 0)
                            <p class="text-[12px] text-red-600">⚠ {{ $s->overdue_count }} en retard</p>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $g['text'] }}">{{ $s->score }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ $g['bg'] }} {{ $g['text'] }} text-[13px] font-bold">{{ $s->grade }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
