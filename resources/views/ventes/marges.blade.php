@extends('layouts.erp')
@section('title', 'Marges par commercial / site')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.dashboard') }}" class="hover:text-gray-700">Ventes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Marges</span>
@endsection

@section('content')
@php
    $fmt  = fn ($n) => number_format((float) $n, 0, ',', ' ').' F';
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $tauxColor = fn ($t) => $t >= 25 ? 'text-emerald-700' : ($t >= 10 ? 'text-amber-600' : 'text-red-600');
@endphp
<div class="max-w-6xl space-y-4">

    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Marges — commercial &amp; site</h1>
            <p class="text-sm text-gray-500">Marge brute (CA HT − coût de revient des lignes facturées) sur {{ $months }} mois glissants.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-1">Période</label>
                <select name="mois" onchange="this.form.submit()"
                        class="h-8 px-2 py-0 border border-gray-400 rounded-[3px] text-[13px]">
                    @foreach([3 => '3 mois', 6 => '6 mois', 12 => '12 mois', 24 => '24 mois', 36 => '36 mois'] as $v => $lbl)
                        <option value="{{ $v }}" @selected($months === $v)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- Marge globale --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase">CA HT (année)</p>
            <p class="text-xl font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($global['ca']) }}</p>
        </div>
        <div class="bg-white border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs text-emerald-600 uppercase">Marge brute (année)</p>
            <p class="text-xl font-bold text-emerald-700 tabular-nums mt-1">{{ $fmt($global['marge']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase">Taux de marge</p>
            <p class="text-xl font-bold tabular-nums mt-1 {{ $tauxColor($global['taux']) }}">{{ number_format($global['taux'], 1, ',', ' ') }} %</p>
        </div>
    </div>

    {{-- Par commercial --}}
    <div>
        <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Marge par commercial</div>
        <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Commercial</th>
                        <th class="{{ $th }} text-right">Factures</th>
                        <th class="{{ $th }} text-right">CA HT</th>
                        <th class="{{ $th }} text-right">Coût de revient</th>
                        <th class="{{ $th }} text-right">Marge</th>
                        <th class="{{ $th }} text-right">Taux</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($bySalesRep as $r)
                    <tr>
                        <td class="px-3 py-1.5 font-medium">{{ $r['rep_name'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $r['invoices'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($r['ca']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($r['cost']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($r['marge']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $tauxColor($r['taux']) }}">{{ number_format($r['taux'], 1, ',', ' ') }} %</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune facture sur la période.</td></tr>
                    @endforelse
                </tbody>
                @if($bySalesRep->isNotEmpty())
                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                    <tr>
                        <td class="px-3 py-1.5">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $bySalesRep->sum('invoices') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($bySalesRep->sum('ca')) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($bySalesRep->sum('cost')) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ $fmt($bySalesRep->sum('marge')) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">@php $tc = $bySalesRep->sum('ca'); @endphp{{ $tc > 0 ? number_format($bySalesRep->sum('marge') / $tc * 100, 1, ',', ' ') : '0,0' }} %</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Par site / dépôt --}}
    <div>
        <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Marge par site / dépôt</div>
        <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Site / dépôt</th>
                        <th class="{{ $th }} text-right">Factures</th>
                        <th class="{{ $th }} text-right">CA HT</th>
                        <th class="{{ $th }} text-right">Coût de revient</th>
                        <th class="{{ $th }} text-right">Marge</th>
                        <th class="{{ $th }} text-right">Taux</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($bySite as $r)
                    <tr>
                        <td class="px-3 py-1.5 font-medium">{{ $r['site_name'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $r['invoices'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($r['ca']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($r['cost']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($r['marge']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $tauxColor($r['taux']) }}">{{ number_format($r['taux'], 1, ',', ' ') }} %</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune facture sur la période.</td></tr>
                    @endforelse
                </tbody>
                @if($bySite->isNotEmpty())
                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                    <tr>
                        <td class="px-3 py-1.5">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $bySite->sum('invoices') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($bySite->sum('ca')) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($bySite->sum('cost')) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ $fmt($bySite->sum('marge')) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">@php $ts = $bySite->sum('ca'); @endphp{{ $ts > 0 ? number_format($bySite->sum('marge') / $ts * 100, 1, ',', ' ') : '0,0' }} %</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Période : <span class="text-white font-semibold">{{ $months }} mois</span></span>
        <span class="ml-auto tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
