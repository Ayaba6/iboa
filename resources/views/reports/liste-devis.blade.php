@extends('layouts.erp')
@section('title', 'Liste des devis')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Liste des devis</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Liste des devis</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tous les devis avec filtres — FCFA</p>
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
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}" class="w-full border border-gray-300 rounded-[4px] px-2 h-8 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}" class="w-full border border-gray-300 rounded-[4px] px-2 h-8 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-[4px] px-2 h-8 py-0 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                    <option value="">— Tous —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Statut</label>
                <select name="status" class="w-full border border-gray-300 rounded-[4px] px-2 h-8 py-0 text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                    <option value="">— Tous statuts —</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Appliquer</button>
            <a href="{{ route('reports.liste-devis') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px]">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Nb devis</p>
            <p class="mt-1 text-[17px] font-bold text-emerald-800">{{ $totals['count'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total HT</p>
            <p class="mt-1 text-[17px] font-bold text-blue-700">{{ number_format($totals['ht'], 0, ',', ' ') }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total TTC</p>
            <p class="mt-1 text-[17px] font-bold text-gray-900">{{ number_format($totals['ttc'], 0, ',', ' ') }} F</p>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[14px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">N° Devis</th>
                    <th class="px-3 py-1.5 text-center">Date</th>
                    <th class="px-3 py-1.5 text-center">Validité</th>
                    <th class="px-3 py-1.5 text-left">Client</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                    <th class="px-3 py-1.5 text-right">HT</th>
                    <th class="px-3 py-1.5 text-right">TTC</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                @php
                    $sc = match($r->status) {
                        'valide' => 'bg-blue-100 text-blue-800', 'converti' => 'bg-green-100 text-green-800',
                        'expire' => 'bg-orange-100 text-orange-800', 'refuse' => 'bg-red-100 text-red-800', 'annule' => 'bg-red-100 text-red-800',
                        default  => 'bg-gray-100 text-gray-800',
                    };
                    $sl = $statuses[$r->status] ?? $r->status;
                @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1 font-medium text-emerald-800">
                        <a href="{{ route('ventes.devis.show', $r->id) }}" class="hover:underline">{{ $r->number }}</a>
                    </td>
                    <td class="px-3 py-1 text-center text-gray-600">{{ $r->issued_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-1 text-center text-gray-600">{{ $r->expires_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1 text-gray-800">{{ $r->client?->name ?? '—' }}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $sc }}">{{ $sl }}</span>
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-700">{{ number_format($r->subtotal_ht, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-semibold text-gray-900">{{ number_format($r->total_ttc, 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun devis sur cette période</td>
                </tr>
                @endforelse
            </tbody>
            @if($rows->count())
            <tfoot class="text-white font-bold" style="background:#065f46">
                <tr>
                    <td class="px-3 py-1.5" colspan="5">TOTAL ({{ $totals['count'] }} devis)</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['ht'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['ttc'], 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

</div>
@endsection
