@extends('layouts.erp')
@section('title', 'Bilan')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bilan</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Bilan SYSCOHADA</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($selectedFy)
                    Soldes cumulés au {{ $selectedFy->ends_at->format('d/m/Y') }} — {{ $selectedFy->label }}
                @else
                    Soldes cumulés (tous exercices confondus)
                @endif
            </p>
        </div>
        <div class="flex gap-3 flex-wrap">
            <a href="{{ route('comptabilite.compte-de-resultat', request()->only('fiscal_year_id')) }}"
               class="text-sm text-violet-600 hover:text-violet-800 font-medium border border-violet-200 px-3 py-1.5 rounded-[4px]">
                Compte de résultat →
            </a>
            @if(isset($netResult) && $netResult !== 0)
            <a href="{{ route('comptabilite.affectation-resultat', request()->only('fiscal_year_id')) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium border px-3 py-1.5 rounded-[4px]
                      {{ $netResult < 0 ? 'text-red-600 border-red-200 hover:bg-red-50' : 'text-emerald-700 border-emerald-200 hover:bg-emerald-50' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Affecter le résultat
            </a>
            @endif
            <a href="{{ route('comptabilite.bilan.pdf', request()->only('fiscal_year_id')) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-[4px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                Exporter PDF
            </a>
            <button onclick="window.print()" class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-[4px]">
                Imprimer
            </button>
        </div>
    </div>

    {{-- Fiscal year filter --}}
    <form method="GET" action="{{ route('comptabilite.bilan') }}" class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Exercice comptable</label>
            <select name="fiscal_year_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm focus:ring-violet-500 focus:border-violet-500">
                <option value="">— Tous exercices (soldes cumulés) —</option>
                @foreach($fiscalYears as $fy)
                <option value="{{ $fy->id }}" {{ $selectedFy?->id == $fy->id ? 'selected' : '' }}>
                    {{ $fy->label }}
                    ({{ $fy->starts_at->format('d/m/Y') }} – {{ $fy->ends_at->format('d/m/Y') }})
                    @if($fy->is_current) ★ @endif
                </option>
                @endforeach
            </select>
        </div>
        @if($selectedFy)
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" name="compare" value="1"
                   {{ ($compare ?? false) ? 'checked' : '' }}
                   onchange="this.form.submit()"
                   class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            Comparer N vs N-1
        </label>
        <a href="{{ route('comptabilite.bilan') }}" class="text-xs text-gray-400 hover:text-gray-600 underline">
            Réinitialiser
        </a>
        @endif
    </form>

    {{-- Contrôle d'équilibre du bilan (Actif = Passif) --}}
    @php $bilanEcart = $totalActif - $totalPassif; @endphp
    @if($bilanEcart == 0)
    <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-[4px] px-3 py-2.5 text-sm text-emerald-700">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Bilan équilibré — Actif = Passif = {{ number_format($totalActif, 0, ',', ' ') }} FCFA
    </div>
    @else
    <div class="flex items-start gap-2 bg-red-50 border border-red-200 rounded-[4px] px-3 py-1.5 text-sm text-red-700">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="font-semibold">Bilan déséquilibré — écart de {{ number_format(abs($bilanEcart), 0, ',', ' ') }} FCFA</p>
            <p class="text-xs text-red-600 mt-0.5">
                Actif {{ number_format($totalActif, 0, ',', ' ') }} ≠ Passif {{ number_format($totalPassif, 0, ',', ' ') }}.
                Causes fréquentes : à-nouveaux (journal AN) non saisis, capital social manquant en classe 1, ou écritures déséquilibrées.
            </p>
        </div>
    </div>
    @endif

    {{-- [COMPTA-PRO-04] Comparatif N vs N-1 --}}
    @if(($compare ?? false) && $selectedFy)
        @if(!$prevFy)
            <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4 text-sm text-amber-800">
                Aucun exercice antérieur trouvé pour la comparaison.
            </div>
        @else
            @php
                $variation = fn($n, $n1) => $n - $n1;
                $varPct    = fn($n, $n1) => $n1 != 0 ? round(($n - $n1) / abs($n1) * 100, 1) : null;
                $varClass  = fn($v) => $v > 0 ? 'text-emerald-600' : ($v < 0 ? 'text-red-600' : 'text-gray-500');
                $varIcon   = fn($v) => $v > 0 ? '↑' : ($v < 0 ? '↓' : '→');
            @endphp
            <div class="bg-white rounded-[4px] border border-violet-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-violet-100 bg-violet-50">
                    <h2 class="text-sm font-semibold text-violet-800">
                        Comparatif {{ $selectedFy->label }} vs {{ $prevFy->label }}
                    </h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                        <tr>
                            <th class="px-3 py-1.5 text-left">Poste</th>
                            <th class="px-3 py-1.5 text-right">{{ $selectedFy->label }}</th>
                            <th class="px-3 py-1.5 text-right">{{ $prevFy->label }}</th>
                            <th class="px-3 py-1.5 text-right">Variation</th>
                            <th class="px-3 py-1.5 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($actif as $label => $accs)
                            @php
                                $n  = (int) $accs->sum('net');
                                $n1 = (int) ($prevTotals['ACTIF::' . $label] ?? 0);
                                $v  = $variation($n, $n1);
                                $p  = $varPct($n, $n1);
                            @endphp
                            @if($n !== 0 || $n1 !== 0)
                            <tr>
                                <td class="px-3 py-1.5 text-emerald-700">ACTIF · {{ $label }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($n, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($n1, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($v) }}">{{ $varIcon($v) }} {{ number_format(abs($v), 0, ',', ' ') }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($v) }}">{{ $p !== null ? $p.' %' : '—' }}</td>
                            </tr>
                            @endif
                        @endforeach
                        <tr class="bg-emerald-50 font-semibold">
                            <td class="px-3 py-1.5">TOTAL ACTIF</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totalActif, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($prevTotals['__totalActif'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($totalActif - ($prevTotals['__totalActif'] ?? 0)) }}">
                                {{ $varIcon($totalActif - ($prevTotals['__totalActif'] ?? 0)) }} {{ number_format(abs($totalActif - ($prevTotals['__totalActif'] ?? 0)), 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($totalActif - ($prevTotals['__totalActif'] ?? 0)) }}">
                                {{ ($prevTotals['__totalActif'] ?? 0) > 0 ? round(($totalActif - $prevTotals['__totalActif']) / $prevTotals['__totalActif'] * 100, 1).' %' : '—' }}
                            </td>
                        </tr>
                        @foreach($passif as $label => $accs)
                            @php
                                $n  = (int) $accs->sum(fn($a) => abs($a->net));
                                $n1 = (int) ($prevTotals['PASSIF::' . $label] ?? 0);
                                $v  = $variation($n, $n1);
                                $p  = $varPct($n, $n1);
                            @endphp
                            @if($n !== 0 || $n1 !== 0)
                            <tr>
                                <td class="px-3 py-1.5 text-orange-700">PASSIF · {{ $label }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($n, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($n1, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($v) }}">{{ $varIcon($v) }} {{ number_format(abs($v), 0, ',', ' ') }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($v) }}">{{ $p !== null ? $p.' %' : '—' }}</td>
                            </tr>
                            @endif
                        @endforeach
                        <tr class="bg-orange-50 font-semibold">
                            <td class="px-3 py-1.5">TOTAL PASSIF</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totalPassif, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($prevTotals['__totalPassif'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($totalPassif - ($prevTotals['__totalPassif'] ?? 0)) }}">
                                {{ $varIcon($totalPassif - ($prevTotals['__totalPassif'] ?? 0)) }} {{ number_format(abs($totalPassif - ($prevTotals['__totalPassif'] ?? 0)), 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($totalPassif - ($prevTotals['__totalPassif'] ?? 0)) }}">
                                {{ ($prevTotals['__totalPassif'] ?? 0) > 0 ? round(($totalPassif - $prevTotals['__totalPassif']) / $prevTotals['__totalPassif'] * 100, 1).' %' : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- Balance check --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-blue-50 rounded-[4px] border border-blue-100 p-4 text-center">
            <p class="text-xs text-blue-600 font-medium uppercase">Total Actif</p>
            <p class="text-[16px] font-bold tabular-nums text-blue-800 mt-1">{{ number_format($totalActif, 0, ',', ' ') }}</p>
            <p class="text-xs text-blue-500 mt-0.5">FCFA</p>
        </div>
        <div class="flex items-center justify-center">
            @if(abs($totalActif - $totalPassif) < 1)
            <div class="text-center">
                <p class="text-2xl">⚖️</p>
                <p class="text-sm font-bold text-green-600">Bilan équilibré</p>
            </div>
            @else
            <div class="text-center">
                <p class="text-2xl">⚠️</p>
                <p class="text-sm font-bold text-orange-600">Écart : {{ number_format(abs($totalActif - $totalPassif), 0, ',', ' ') }}</p>
            </div>
            @endif
        </div>
        <div class="bg-green-50 rounded-[4px] border border-green-100 p-4 text-center">
            <p class="text-xs text-green-600 font-medium uppercase">Total Passif</p>
            <p class="text-[16px] font-bold tabular-nums text-green-800 mt-1">{{ number_format($totalPassif, 0, ',', ' ') }}</p>
            <p class="text-xs text-green-500 mt-0.5">FCFA</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- ACTIF --}}
        <div class="space-y-4">
            <h2 class="text-[16px] font-bold text-blue-800 border-b-2 border-blue-200 pb-2">ACTIF</h2>
            @foreach($actif as $sectionName => $sectionAccounts)
            @if($sectionAccounts->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-3 py-1.5 bg-blue-50 border-b border-blue-100">
                    <h3 class="text-sm font-semibold text-blue-800">{{ $sectionName }}</h3>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sectionAccounts as $account)
                        @if($account->net != 0)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5">
                                <span class="font-mono text-violet-600 font-semibold text-xs">{{ $account->code }}</span>
                                <span class="text-gray-700 ml-1 text-xs">{{ $account->name }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-blue-700 whitespace-nowrap">
                                {{ number_format(abs($account->net), 0, ',', ' ') }}
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr>
                            <td class="px-3 py-1.5 text-xs font-bold text-gray-500 uppercase">Sous-total</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-bold text-blue-800">
                                {{ number_format($sectionAccounts->sum(fn($a) => abs($a->net)), 0, ',', ' ') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
            @endforeach
            <div class="bg-blue-700 text-white rounded-[4px] px-3 py-1.5 flex justify-between font-bold">
                <span>TOTAL ACTIF</span>
                <span class="tabular-nums">{{ number_format($totalActif, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        {{-- PASSIF --}}
        <div class="space-y-4">
            <h2 class="text-[16px] font-bold text-green-800 border-b-2 border-green-200 pb-2">PASSIF</h2>
            @foreach($passif as $sectionName => $sectionAccounts)
            @if($sectionAccounts->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-3 py-1.5 bg-green-50 border-b border-green-100">
                    <h3 class="text-sm font-semibold text-green-800">{{ $sectionName }}</h3>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sectionAccounts as $account)
                        @if($account->net != 0)
                        @php $isVirtualLoss = ($account->_virtual ?? false) && ($account->_is_loss ?? false); @endphp
                        <tr class="hover:bg-gray-50 {{ $isVirtualLoss ? 'bg-red-50' : '' }}">
                            <td class="px-3 py-1.5">
                                <span class="font-mono {{ $isVirtualLoss ? 'text-red-500' : 'text-violet-600' }} font-semibold text-xs">{{ $account->code }}</span>
                                <span class="{{ $isVirtualLoss ? 'text-red-700' : 'text-gray-700' }} ml-1 text-xs font-medium">{{ $account->name }}</span>
                                @if($account->_virtual ?? false)
                                    <span class="ml-1 text-xs px-1 py-0.5 rounded {{ $isVirtualLoss ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600' }}">calculé</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold whitespace-nowrap {{ $isVirtualLoss ? 'text-red-600' : 'text-green-700' }}">
                                {{ $isVirtualLoss ? '(' : '' }}{{ number_format(abs($account->net), 0, ',', ' ') }}{{ $isVirtualLoss ? ')' : '' }}
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                    @php
                        $sectionNet = $sectionAccounts->sum(fn($a) =>
                            ($a->_virtual ?? false) ? $a->net : abs($a->net)
                        );
                    @endphp
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr>
                            <td class="px-3 py-1.5 text-xs font-bold text-gray-500 uppercase">Sous-total</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-bold {{ $sectionNet < 0 ? 'text-red-700' : 'text-green-800' }}">
                                {{ $sectionNet < 0 ? '(' : '' }}{{ number_format(abs($sectionNet), 0, ',', ' ') }}{{ $sectionNet < 0 ? ')' : '' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
            @endforeach
            <div class="bg-green-700 text-white rounded-[4px] px-3 py-1.5 flex justify-between font-bold">
                <span>TOTAL PASSIF</span>
                <span class="tabular-nums">{{ number_format($totalPassif, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>
    </div>

</div>
@endsection
