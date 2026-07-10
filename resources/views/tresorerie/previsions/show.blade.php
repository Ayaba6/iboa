@extends('layouts.erp')
@section('title', 'Prévision ' . $prevision->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.previsions.index') }}" class="hover:text-gray-700">Prévisions</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $prevision->number }}</span>
@endsection

@section('content')
<div class="space-y-3 max-w-5xl">

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $prevision->number }} — {{ $prevision->label }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ ucfirst($prevision->period_type) }} ·
                {{ $prevision->period_start?->format('d/m/Y') }} → {{ $prevision->period_end?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $prevision->status === 'valide' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                {{ $prevision->statusLabel() }}
            </span>
            @if($prevision->isEditable())
            @can('treasury.write')
            <form method="POST" action="{{ route('tresorerie.previsions.validate', $prevision) }}">
                @csrf
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">✓ Valider</button>
            </form>
            <form method="POST" action="{{ route('tresorerie.previsions.sync-actuals', $prevision) }}">
                @csrf
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">⟳ Sync réalisations</button>
            </form>
            @endcan
            @endif
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500">Solde ouverture</p>
            <p class="text-[16px] font-bold tabular-nums text-gray-800">{{ number_format($prevision->opening_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-green-50 rounded-[4px] border border-green-200 p-4 text-center">
            <p class="text-xs text-green-600">Encaissements prévus</p>
            <p class="text-[16px] font-bold tabular-nums text-green-800">+{{ number_format($prevision->total_inflows, 0, ',', ' ') }}</p>
            @if($prevision->actual_inflows > 0)
            <p class="text-xs text-green-500 mt-0.5">Réalisé : {{ number_format($prevision->actual_inflows, 0, ',', ' ') }}</p>
            @endif
        </div>
        <div class="bg-red-50 rounded-[4px] border border-red-200 p-4 text-center">
            <p class="text-xs text-red-600">Décaissements prévus</p>
            <p class="text-[16px] font-bold tabular-nums text-red-800">-{{ number_format($prevision->total_outflows, 0, ',', ' ') }}</p>
            @if($prevision->actual_outflows > 0)
            <p class="text-xs text-red-500 mt-0.5">Réalisé : {{ number_format($prevision->actual_outflows, 0, ',', ' ') }}</p>
            @endif
        </div>
        <div class="rounded-[4px] border-2 p-4 text-center {{ $prevision->net_flow >= 0 ? 'bg-green-50 border-green-300' : 'bg-red-50 border-red-300' }}">
            <p class="text-xs {{ $prevision->net_flow >= 0 ? 'text-green-600' : 'text-red-600' }}">Flux net prévu</p>
            <p class="text-[16px] font-bold tabular-nums {{ $prevision->net_flow >= 0 ? 'text-green-800' : 'text-red-800' }}">
                {{ $prevision->net_flow >= 0 ? '+' : '' }}{{ number_format($prevision->net_flow, 0, ',', ' ') }}
            </p>
        </div>
        <div class="bg-[#eef5f0] rounded-[4px] border border-emerald-200 p-4 text-center">
            <p class="text-xs text-emerald-700">Solde clôture prévu</p>
            <p class="text-[16px] font-bold tabular-nums text-emerald-900">{{ number_format($prevision->closing_balance_forecast, 0, ',', ' ') }}</p>
        </div>
    </div>

    {{-- Lines --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Inflows --}}
        <div class="bg-white rounded-[4px] border border-green-200 overflow-hidden">
            <div class="px-5 py-4 bg-green-50 border-b border-green-100">
                <h3 class="font-semibold text-green-800">Encaissements</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300"><tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prévu</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réalisé</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Écart</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($prevision->inflows as $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5">
                            <p class="text-gray-800 font-medium">{{ $line->label }}</p>
                            <p class="text-xs text-gray-400">\App\Models\CashFlowForecastLine::categoryLabel($line->category)</p>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-green-700 font-medium">{{ number_format($line->forecast_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ $line->actual_amount > 0 ? number_format($line->actual_amount, 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-xs font-semibold {{ $line->variance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $line->actual_amount > 0 ? ($line->variance >= 0 ? '+' : '') . number_format($line->variance, 0, ',', ' ') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-green-200 bg-green-50 font-bold">
                    <tr>
                        <td class="px-3 py-1.5 text-xs text-gray-500 uppercase">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-green-800">{{ number_format($prevision->total_inflows, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ $prevision->actual_inflows > 0 ? number_format($prevision->actual_inflows, 0, ',', ' ') : '—' }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Outflows --}}
        <div class="bg-white rounded-[4px] border border-red-200 overflow-hidden">
            <div class="px-5 py-4 bg-red-50 border-b border-red-100">
                <h3 class="font-semibold text-red-800">Décaissements</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300"><tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prévu</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réalisé</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Écart</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($prevision->outflows as $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5">
                            <p class="text-gray-800 font-medium">{{ $line->label }}</p>
                            <p class="text-xs text-gray-400">{{ \App\Models\CashFlowForecastLine::categoryLabel($line->category) }}</p>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-red-700 font-medium">{{ number_format($line->forecast_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ $line->actual_amount > 0 ? number_format($line->actual_amount, 0, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-xs font-semibold {{ $line->variance <= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $line->actual_amount > 0 ? ($line->variance >= 0 ? '+' : '') . number_format($line->variance, 0, ',', ' ') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-red-200 bg-red-50 font-bold">
                    <tr>
                        <td class="px-3 py-1.5 text-xs text-gray-500 uppercase">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-800">{{ number_format($prevision->total_outflows, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ $prevision->actual_outflows > 0 ? number_format($prevision->actual_outflows, 0, ',', ' ') : '—' }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>
@endsection
