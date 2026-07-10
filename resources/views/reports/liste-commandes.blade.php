@extends('layouts.erp')
@section('title', 'Liste des commandes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Liste des commandes</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Liste des commandes</h1>
            <p class="text-sm text-gray-500 mt-0.5">Toutes les commandes clients avec filtres — FCFA</p>
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
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div class="w-64">
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                    <option value="">— Tous —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Statut</label>
                <select name="status" class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                    <option value="">— Tous statuts —</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Appliquer</button>
            <a href="{{ route('reports.liste-commandes') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs --}}
    @php
        $kpis = [
            ['label' => 'Nb commandes', 'value' => number_format($totals['count'], 0, ',', ' '),           'text' => 'text-gray-900',  'bd' => 'border-gray-300'],
            ['label' => 'Total HT',     'value' => number_format($totals['ht'], 0, ',', ' ') . ' F',       'text' => 'text-blue-700',  'bd' => 'border-blue-200'],
            ['label' => 'Total TTC',    'value' => number_format($totals['ttc'], 0, ',', ' ') . ' F',      'text' => 'text-emerald-800', 'bd' => 'border-emerald-200'],
        ];
    @endphp
    <div class="grid grid-cols-3 gap-1.5">
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
                    <th class="px-3 py-1.5 text-left">N° Commande</th>
                    <th class="px-3 py-1.5 text-center">Date</th>
                    <th class="px-3 py-1.5 text-left">Client</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                    <th class="px-3 py-1.5 text-right">HT</th>
                    <th class="px-3 py-1.5 text-right">TTC</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                @php
                    // Classes statiques (une classe dynamique bg-{x}-100 échappe au build Tailwind)
                    $badge = match($r->status) {
                        'confirme' => 'bg-blue-100 text-blue-800',
                        'en_cours' => 'bg-indigo-100 text-indigo-800',
                        'livre'    => 'bg-emerald-100 text-emerald-800',
                        'facture'  => 'bg-green-100 text-green-800',
                        'annule'   => 'bg-red-100 text-red-800',
                        default    => 'bg-gray-100 text-gray-800',
                    };
                    $sl = $statuses[$r->status] ?? $r->status;
                @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1 font-medium">
                        <a href="{{ route('ventes.commandes.show', $r->id) }}" class="text-blue-600 hover:text-blue-800">{{ $r->number }}</a>
                    </td>
                    <td class="px-3 py-1 text-center text-gray-600 tabular-nums">{{ $r->issued_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-1 text-gray-800">{{ $r->client?->name ?? '—' }}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $badge }}">{{ $sl }}</span>
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-700">{{ number_format($r->subtotal_ht, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-semibold text-gray-900">{{ number_format($r->total_ttc, 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-gray-400 text-[13px]">Aucune commande sur cette période</td>
                </tr>
                @endforelse
            </tbody>
            @if($rows->count())
            <tfoot>
                <tr class="text-white font-bold" style="background:#065f46">
                    <td class="px-3 py-1.5 text-[11px] uppercase" colspan="4">TOTAL ({{ $totals['count'] }} commande{{ $totals['count'] > 1 ? 's' : '' }})</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['ht'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['ttc'], 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>

</div>
@endsection
