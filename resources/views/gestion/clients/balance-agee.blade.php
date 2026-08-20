@extends('layouts.erp')
@section('title', 'Balance âgée clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Balance âgée</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Balance âgée clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">Créances en cours ventilées par ancienneté — au {{ $today->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('clients.balance-agee.export-excel', array_filter(['client_id' => $clientId])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('clients.balance-agee.export-pdf', array_filter(['client_id' => $clientId])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('clients.releve') }}"     class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-200 hover:bg-emerald-50 px-2.5 py-1.5 rounded-[4px] transition-colors">Relevé client</a>
            <a href="{{ route('clients.grand-livre') }}" class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-200 hover:bg-emerald-50 px-2.5 py-1.5 rounded-[4px] transition-colors">Grand livre</a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex gap-3">
            {{-- `py-0` obligatoire avec `h-8` : @tailwindcss/forms impose sinon
                 un padding vertical qui écrase la hauteur du <select>. --}}
            <select name="client_id" class="h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 w-72">
                <option value="">Tous les clients</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if($clientId)
            <a href="{{ route('clients.balance-agee') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-1.5">
        @php
            $kpis = [
                ['label' => 'Total dû',    'value' => $totals['total'],    'text' => 'text-gray-900',   'bd' => 'border-gray-300'],
                ['label' => 'Non échu',    'value' => $totals['non_echu'], 'text' => 'text-blue-700',   'bd' => 'border-blue-200'],
                ['label' => '1 – 30 j',   'value' => $totals['j1_30'],    'text' => 'text-yellow-700', 'bd' => 'border-yellow-200'],
                ['label' => '31 – 60 j',  'value' => $totals['j31_60'],   'text' => 'text-orange-700', 'bd' => 'border-orange-200'],
                ['label' => '61 – 90 j',  'value' => $totals['j61_90'],   'text' => 'text-red-700',    'bd' => 'border-red-200'],
                ['label' => '+ 90 j',     'value' => $totals['j90p'],     'text' => 'text-red-900',    'bd' => 'border-red-300'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-[4px] border {{ $kpi['bd'] }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
            <p class="mt-0.5 text-[17px] font-bold {{ $kpi['text'] }} tabular-nums leading-none">
                {{ number_format($kpi['value'], 0, ',', ' ') }}
                <span class="text-[10px] font-normal text-gray-400">F</span>
            </p>
        </div>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Client</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total dû</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-blue-600 uppercase tracking-wider bg-blue-50/50">Non échu</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-yellow-600 uppercase tracking-wider bg-yellow-50/50">1 – 30 j</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-orange-600 uppercase tracking-wider bg-orange-50/50">31 – 60 j</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-red-600 uppercase tracking-wider bg-red-50/50">61 – 90 j</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-red-800 uppercase tracking-wider bg-red-100/50">+ 90 j</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1">
                            <a href="{{ route('clients.show', $row['client_id']) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $row['name'] }}</a>
                            @if($row['code'])<span class="text-[10.5px] text-gray-400 font-mono">· {{ $row['code'] }}</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-gray-900">
                            {{ number_format($row['total'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-blue-700 bg-blue-50/30">
                            {{ $row['non_echu'] > 0 ? number_format($row['non_echu'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-yellow-700 bg-yellow-50/30">
                            {{ $row['j1_30'] > 0 ? number_format($row['j1_30'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-orange-700 bg-orange-50/30">
                            {{ $row['j31_60'] > 0 ? number_format($row['j31_60'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-700 bg-red-50/30">
                            {{ $row['j61_90'] > 0 ? number_format($row['j61_90'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-red-900 bg-red-100/30">
                            {{ $row['j90p'] > 0 ? number_format($row['j90p'], 0, ',', ' ') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucune créance en cours.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                <tfoot>
                    <tr class="text-white" style="background:#065f46">
                        <td class="px-3 py-1.5 font-bold text-xs uppercase">TOTAL</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['total'],    0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['non_echu'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['j1_30'],    0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['j31_60'],   0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['j61_90'],   0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['j90p'],     0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
