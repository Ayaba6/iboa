@extends('layouts.erp')
@section('title', 'Centres de coûts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comptabilité analytique</span>
@endsection

@section('content')
<div class="space-y-3">

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Centres de coûts / profit</h1>
        <p class="text-sm text-gray-500 mt-0.5">§12 CDC — Ventilation analytique par axe métier</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('analytique.rapport') }}" class="btn-secondary text-sm">
            Rapport de rentabilité
        </a>
        @can('analytic.manage')
        <a href="{{ route('analytique.centres-couts.create') }}" class="btn-primary text-sm">
            + Nouveau centre
        </a>
        @endcan
    </div>
</div>

{{-- KPIs synthèse --}}
@php
    $all = $centers->getCollection();
    $kpis = [
        ['label' => 'Centres',          'value' => $centers->total(),                                                            'text' => 'text-gray-900',    'bd' => 'border-gray-300'],
        ['label' => 'Centres de coût',  'value' => $all->where('type', 'cost')->count(),                                         'text' => 'text-amber-700',   'bd' => 'border-amber-200'],
        ['label' => 'Centres de profit','value' => $all->where('type', 'profit')->count(),                                       'text' => 'text-emerald-700', 'bd' => 'border-emerald-200'],
        ['label' => 'Total ventilé (page)', 'value' => number_format($all->sum(fn ($c) => abs($c->analytic_lines_sum_amount ?? 0)), 0, ',', ' ') . ' F', 'text' => 'text-blue-700', 'bd' => 'border-blue-200'],
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
    <table class="min-w-full text-[14px] border-collapse">
        <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
            <tr>
                <th class="px-3 py-1.5 text-left">Code</th>
                <th class="px-3 py-1.5 text-left">Nom</th>
                <th class="px-3 py-1.5 text-left">Type</th>
                <th class="px-3 py-1.5 text-right">Solde (FCFA)</th>
                <th class="px-3 py-1.5 text-right">Lignes</th>
                <th class="px-3 py-1.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($centers as $center)
            <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                <td class="px-3 py-1.5 font-mono font-semibold text-gray-700">{{ $center->code }}</td>
                <td class="px-3 py-1.5 font-medium text-gray-900">{{ $center->name }}</td>
                <td class="px-3 py-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium
                        {{ $center->type === 'profit' ? 'bg-emerald-100 text-emerald-700' :
                           ($center->type === 'investment' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ $center->typeLabel() }}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-right font-mono font-semibold
                    {{ ($center->analytic_lines_sum_amount ?? 0) >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ number_format(abs($center->analytic_lines_sum_amount ?? 0), 0, ',', ' ') }}
                </td>
                <td class="px-3 py-1.5 text-right text-gray-500">{{ $center->analytic_lines_count ?? 0 }}</td>
                <td class="px-3 py-1.5 text-right">
                    <a href="{{ route('analytique.centres-couts.show', $center) }}"
                       class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Détail →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                    Aucun centre de coûts configuré.
                    @can('analytic.manage')
                    <a href="{{ route('analytique.centres-couts.create') }}" class="text-emerald-700 hover:underline ml-1">Créer le premier</a>
                    @endcan
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-3 py-1.5 border-t border-gray-100">
        {{ $centers->links() }}
    </div>
</div>

</div>
@endsection
