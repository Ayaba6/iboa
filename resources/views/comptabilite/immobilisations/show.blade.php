@extends('layouts.erp')
@section('title', $asset->code . ' — ' . $asset->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.immobilisations.index') }}" class="hover:text-gray-700">Immobilisations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $asset->code }}</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int)$n, 0, ',', ' ');
    $cumulPosted = $asset->depreciations->where('is_posted', true)->sum('depreciation_amount');
    $vnc = max(0, $asset->gross_value - $cumulPosted);
    $pct = $asset->gross_value > 0 ? min(100, round($cumulPosted / $asset->gross_value * 100)) : 0;
    $statusColors = [
        'en_service'   => 'bg-emerald-100 text-emerald-700',
        'cede'         => 'bg-orange-100 text-orange-700',
        'mis_au_rebut' => 'bg-gray-100 text-gray-500',
    ];
@endphp

<div class="max-w-5xl mx-auto space-y-3">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-[16px] font-bold text-gray-900">{{ $asset->name }}</h1>
                <span class="text-sm font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ $asset->code }}</span>
                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusColors[$asset->status] ?? 'bg-gray-100 text-gray-500' }}">
                    {{ $statusLabels[$asset->status] ?? $asset->status }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $categoryLabels[$asset->category] ?? $asset->category }}
                · {{ $methodLabels[$asset->depreciation_method] ?? $asset->depreciation_method }}
                @if($asset->useful_life_years > 0)
                    · {{ $asset->useful_life_years }} ans ({{ number_format($asset->annual_rate, 2, ',', ' ') }} %/an)
                @endif
            </p>
        </div>
        @can('accounting.write')
        <form method="POST" action="{{ route('comptabilite.immobilisations.regenerate', $asset) }}">
            @csrf
            <button type="submit"
                    class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-xs font-medium px-3 py-1.5 rounded-[4px]"
                    onclick="return confirm('Recalculer le plan d\'amortissement ? Les dotations déjà comptabilisées resteront intactes.')">
                ↺ Recalculer plan
            </button>
        </form>
        @endcan
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Valeur brute</p>
            <p class="text-[17px] font-bold text-gray-900 mt-1">{{ $fmt($asset->gross_value) }} <span class="text-xs font-normal text-gray-500">FCFA</span></p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Amort. cumulé</p>
            <p class="text-[17px] font-bold text-orange-600 mt-1">{{ $fmt($cumulPosted) }} <span class="text-xs font-normal text-gray-500">FCFA</span></p>
            <div class="mt-1.5 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-1.5 {{ $pct >= 100 ? 'bg-red-400' : 'bg-orange-400' }} rounded-full" style="width:{{ $pct }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">{{ $pct }} % amorti</p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">VNC actuelle</p>
            <p class="text-[17px] font-bold text-blue-600 mt-1">{{ $fmt($vnc) }} <span class="text-xs font-normal text-gray-500">FCFA</span></p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Valeur résiduelle</p>
            <p class="text-[17px] font-bold text-gray-600 mt-1">{{ $fmt($asset->residual_value) }} <span class="text-xs font-normal text-gray-500">FCFA</span></p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">

        {{-- Fiche détaillée --}}
        <div class="col-span-1 bg-white border border-gray-300 rounded-[4px] p-5 space-y-3 text-sm">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Fiche</h2>

            <div class="space-y-2 text-gray-600">
                <div class="flex justify-between">
                    <span class="text-gray-500">Date d'acquisition</span>
                    <span class="font-medium">{{ $asset->acquisition_date->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Mise en service</span>
                    <span class="font-medium">{{ $asset->commissioning_date->format('d/m/Y') }}</span>
                </div>
                @if($asset->vendor)
                <div class="flex justify-between">
                    <span class="text-gray-500">Fournisseur</span>
                    <span class="font-medium">{{ $asset->vendor }}</span>
                </div>
                @endif
                @if($asset->invoice_ref)
                <div class="flex justify-between">
                    <span class="text-gray-500">Réf. facture</span>
                    <span class="font-medium font-mono text-xs">{{ $asset->invoice_ref }}</span>
                </div>
                @endif
                <hr class="border-gray-100">
                <div class="flex justify-between">
                    <span class="text-gray-500">Compte immob.</span>
                    <span class="font-mono font-medium text-xs">{{ $asset->asset_account }}</span>
                </div>
                @if($asset->depr_account)
                <div class="flex justify-between">
                    <span class="text-gray-500">Compte amort.</span>
                    <span class="font-mono font-medium text-xs">{{ $asset->depr_account }}</span>
                </div>
                @endif
                @if($asset->charge_account)
                <div class="flex justify-between">
                    <span class="text-gray-500">Compte charge</span>
                    <span class="font-mono font-medium text-xs">{{ $asset->charge_account }}</span>
                </div>
                @endif
                @if($asset->createdBy)
                <hr class="border-gray-100">
                <div class="flex justify-between">
                    <span class="text-gray-500">Créé par</span>
                    <span class="font-medium text-xs">{{ $asset->createdBy->name }}</span>
                </div>
                @endif
            </div>

            @if($asset->notes)
            <div class="pt-2">
                <p class="text-xs text-gray-500 font-medium mb-1">Notes</p>
                <p class="text-xs text-gray-600 leading-relaxed">{{ $asset->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Plan d'amortissement --}}
        <div class="col-span-2 bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Plan d'amortissement</h2>
                @if(!$asset->isDepreciable())
                    <span class="text-xs text-gray-400">Actif non amortissable</span>
                @endif
            </div>

            @if($asset->isDepreciable())
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Exercice</th>
                        <th class="px-3 py-1.5 text-right">Dotation</th>
                        <th class="px-3 py-1.5 text-right">Cumul</th>
                        <th class="px-3 py-1.5 text-right">VNC fin</th>
                        <th class="px-3 py-1.5 text-center">État</th>
                        <th class="px-3 py-1.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($asset->depreciations as $line)
                    <tr class="{{ $line->is_posted ? 'bg-emerald-50/40' : 'hover:bg-gray-50' }}">
                        <td class="px-3 py-2.5 font-semibold text-gray-900">{{ $line->fiscal_year }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-800">{{ $fmt($line->depreciation_amount) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-orange-600">{{ $fmt($line->cumulated_depreciation) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-600 font-medium">{{ $fmt($line->net_book_value) }}</td>
                        <td class="px-3 py-2.5 text-center">
                            @if($line->is_posted)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Comptabilisé
                                </span>
                                @if($line->journalEntry)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $line->journalEntry->number }}</p>
                                @endif
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Planifié</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            @if(!$line->is_posted && $asset->status === 'en_service')
                                @can('accounting.validate')
                                <form method="POST" action="{{ route('comptabilite.immobilisations.post-depreciation', $line) }}"
                                      onsubmit="return confirm('Comptabiliser la dotation {{ $line->fiscal_year }} de {{ $fmt($line->depreciation_amount) }} FCFA ?\nThis will generate a GL entry: DR {{ $asset->charge_account }} / CR {{ $asset->depr_account }}.')">
                                    @csrf
                                    <button type="submit" class="text-xs text-violet-600 hover:text-violet-800 font-medium whitespace-nowrap">
                                        Passer ↗
                                    </button>
                                </form>
                                @endcan
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                            Aucun plan d'amortissement généré.
                            @can('accounting.write')
                            <form method="POST" action="{{ route('comptabilite.immobilisations.regenerate', $asset) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:underline ml-1">Générer →</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($asset->depreciations->isNotEmpty())
                <tfoot class="bg-gray-50 text-xs font-semibold text-gray-700">
                    <tr>
                        <td class="px-3 py-1.5">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($asset->depreciations->sum('depreciation_amount')) }}</td>
                        <td class="px-3 py-1.5"></td>
                        <td class="px-3 py-1.5"></td>
                        <td class="px-3 py-1.5 text-center text-xs text-gray-400">
                            {{ $asset->depreciations->where('is_posted', true)->count() }}/{{ $asset->depreciations->count() }} passées
                        </td>
                        <td class="px-3 py-1.5"></td>
                    </tr>
                </tfoot>
                @endif
            </table>

            {{-- Alerte si plan non équilibré --}}
            @php
                $totalPlan = $asset->depreciations->sum('depreciation_amount');
                $baseAmort = $asset->gross_value - $asset->residual_value;
                $ecart = abs($totalPlan - $baseAmort);
            @endphp
            @if($ecart > 1 && $asset->depreciations->isNotEmpty())
            <div class="px-3 py-1.5 border-t border-amber-100 bg-amber-50 text-xs text-amber-700">
                ⚠ Écart plan vs base amortissable : {{ number_format($ecart, 0, ',', ' ') }} FCFA (arrondi prorata).
            </div>
            @endif

            @else
            <div class="px-4 py-8 text-center text-gray-400 text-sm">
                Cet actif n'est pas amortissable (terrain ou durée = 0).
            </div>
            @endif
        </div>

    </div>

</div>
@endsection
