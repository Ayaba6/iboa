@extends('layouts.erp')
@section('title', 'Compte de résultat')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Compte de résultat</span>
@endsection

@section('content')
<div class="space-y-3 max-w-6xl">

    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Compte de résultat SYSCOHADA</h1>
            <p class="text-[11.5px] text-gray-400">
                @if($selectedFy)
                    Charges et Produits du {{ $selectedFy->starts_at->format('d/m/Y') }}
                    au {{ $selectedFy->ends_at->format('d/m/Y') }} — {{ $selectedFy->label }}
                @else
                    Charges (classe 6) et Produits (classe 7) — soldes cumulés
                @endif
            </p>
        </div>
        <div class="flex gap-1.5 flex-wrap">
            <a href="{{ route('comptabilite.bilan', request()->only('fiscal_year_id')) }}"
               class="text-[12px] text-violet-600 hover:text-violet-800 font-medium border border-violet-200 hover:bg-violet-50 px-3 py-1.5 rounded-[4px]">
                ← Bilan
            </a>
            <a href="{{ route('comptabilite.compte-de-resultat.pdf', request()->only('fiscal_year_id')) }}"
               class="inline-flex items-center gap-1.5 text-[12px] font-medium text-white bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-[4px]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                Exporter PDF
            </a>
            <button onclick="window.print()" class="text-[12px] text-gray-500 hover:text-gray-700 border border-gray-200 hover:bg-gray-50 px-3 py-1.5 rounded-[4px]">
                Imprimer
            </button>
        </div>
    </div>

    {{-- Fiscal year filter --}}
    <form method="GET" action="{{ route('comptabilite.compte-de-resultat') }}" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[10px] font-bold text-gray-600 uppercase tracking-wide mb-1">Exercice comptable</label>
            <select name="fiscal_year_id" onchange="this.form.submit()"
                    class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
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
        {{-- [COMPTA-PRO-04] Toggle comparatif N vs N-1 --}}
        <label class="inline-flex items-center gap-1.5 text-[12px] text-gray-700 cursor-pointer pb-1">
            <input type="checkbox" name="compare" value="1"
                   {{ ($compare ?? false) ? 'checked' : '' }}
                   onchange="this.form.submit()"
                   class="w-3.5 h-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            Comparer N vs N-1
        </label>
        <a href="{{ route('comptabilite.compte-de-resultat') }}" class="text-[11px] text-gray-400 hover:text-gray-600 underline pb-1">
            Réinitialiser
        </a>
        @endif
    </form>

    {{-- [COMPTA-PRO-04] Tableau comparatif N vs N-1 --}}
    @if(($compare ?? false) && $selectedFy)
        @if(!$prevFy)
            <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-2 text-[12px] text-amber-800">
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
                <div class="px-3 py-1.5 border-b border-violet-100 bg-violet-50">
                    <h2 class="text-[12px] font-bold text-violet-800 uppercase tracking-wide">
                        Comparatif {{ $selectedFy->label }} vs {{ $prevFy->label }}
                    </h2>
                </div>
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] uppercase tracking-wide font-bold text-emerald-900">
                        <tr>
                            <th class="px-3 py-1.5 text-left">Poste</th>
                            <th class="px-3 py-1.5 text-right">{{ $selectedFy->label }}</th>
                            <th class="px-3 py-1.5 text-right">{{ $prevFy->label }}</th>
                            <th class="px-3 py-1.5 text-right">Variation</th>
                            <th class="px-3 py-1.5 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($produits as $label => $accs)
                            @php
                                $n  = (int) $accs->sum('net');
                                $n1 = (int) ($prevTotals['PRODUITS::' . $label] ?? 0);
                                $v  = $variation($n, $n1);
                                $p  = $varPct($n, $n1);
                            @endphp
                            @if($n !== 0 || $n1 !== 0)
                            <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                <td class="px-3 py-1 text-blue-700">{{ $label }}</td>
                                <td class="px-3 py-1 text-right tabular-nums">{{ number_format($n, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums text-gray-500">{{ number_format($n1, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums {{ $varClass($v) }}">{{ $varIcon($v) }} {{ number_format(abs($v), 0, ',', ' ') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums {{ $varClass($v) }}">{{ $p !== null ? $p . ' %' : '—' }}</td>
                            </tr>
                            @endif
                        @endforeach
                        <tr class="bg-blue-50 font-semibold">
                            <td class="px-3 py-1">Total Produits</td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ number_format($totalProduits, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ number_format($prevTotals['__totalProduits'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1 text-right tabular-nums {{ $varClass($totalProduits - ($prevTotals['__totalProduits'] ?? 0)) }}">
                                {{ $varIcon($totalProduits - ($prevTotals['__totalProduits'] ?? 0)) }} {{ number_format(abs($totalProduits - ($prevTotals['__totalProduits'] ?? 0)), 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1 text-right tabular-nums {{ $varClass($totalProduits - ($prevTotals['__totalProduits'] ?? 0)) }}">
                                {{ ($prevTotals['__totalProduits'] ?? 0) > 0 ? round(($totalProduits - $prevTotals['__totalProduits']) / $prevTotals['__totalProduits'] * 100, 1) . ' %' : '—' }}
                            </td>
                        </tr>
                        @foreach($charges as $label => $accs)
                            @php
                                $n  = (int) $accs->sum('net');
                                $n1 = (int) ($prevTotals['CHARGES::' . $label] ?? 0);
                                $v  = $variation($n, $n1);
                                $p  = $varPct($n, $n1);
                            @endphp
                            @if($n !== 0 || $n1 !== 0)
                            <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                <td class="px-3 py-1 text-red-700">{{ $label }}</td>
                                <td class="px-3 py-1 text-right tabular-nums">{{ number_format($n, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums text-gray-500">{{ number_format($n1, 0, ',', ' ') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums {{ $varClass(-$v) }}">{{ $varIcon($v) }} {{ number_format(abs($v), 0, ',', ' ') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums {{ $varClass(-$v) }}">{{ $p !== null ? $p . ' %' : '—' }}</td>
                            </tr>
                            @endif
                        @endforeach
                        <tr class="bg-red-50 font-semibold">
                            <td class="px-3 py-1">Total Charges</td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ number_format($totalCharges, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ number_format($prevTotals['__totalCharges'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1 text-right tabular-nums {{ $varClass(-($totalCharges - ($prevTotals['__totalCharges'] ?? 0))) }}">
                                {{ $varIcon($totalCharges - ($prevTotals['__totalCharges'] ?? 0)) }} {{ number_format(abs($totalCharges - ($prevTotals['__totalCharges'] ?? 0)), 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1 text-right tabular-nums {{ $varClass(-($totalCharges - ($prevTotals['__totalCharges'] ?? 0))) }}">
                                {{ ($prevTotals['__totalCharges'] ?? 0) > 0 ? round(($totalCharges - $prevTotals['__totalCharges']) / $prevTotals['__totalCharges'] * 100, 1) . ' %' : '—' }}
                            </td>
                        </tr>
                        <tr class="bg-violet-100 font-bold">
                            <td class="px-3 py-1.5">RÉSULTAT NET</td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $resultat >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($resultat, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($prevTotals['__resultat'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($resultat - ($prevTotals['__resultat'] ?? 0)) }}">
                                {{ $varIcon($resultat - ($prevTotals['__resultat'] ?? 0)) }} {{ number_format(abs($resultat - ($prevTotals['__resultat'] ?? 0)), 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $varClass($resultat - ($prevTotals['__resultat'] ?? 0)) }}">
                                {{ ($prevTotals['__resultat'] ?? 0) != 0 ? round(($resultat - $prevTotals['__resultat']) / abs($prevTotals['__resultat']) * 100, 1) . ' %' : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- Result KPI --}}
    <div class="grid grid-cols-3 gap-1.5">
        <div class="bg-red-50 rounded-[4px] border border-red-200 px-3 py-2 text-center">
            <p class="text-[10px] text-red-600 font-bold uppercase tracking-wide">Total Charges</p>
            <p class="text-[19px] font-bold tabular-nums text-red-800 mt-0.5 leading-none">{{ number_format($totalCharges, 0, ',', ' ') }}</p>
            <p class="text-[10px] text-red-400 mt-1">FCFA</p>
        </div>
        <div class="rounded-[4px] border px-3 py-2 text-center {{ $resultat >= 0 ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
            <p class="text-[10px] font-bold uppercase tracking-wide {{ $resultat >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $resultat >= 0 ? 'Bénéfice net' : 'Perte nette' }}
            </p>
            <p class="text-[19px] font-bold tabular-nums mt-0.5 leading-none {{ $resultat >= 0 ? 'text-green-800' : 'text-red-800' }}">
                {{ number_format(abs($resultat), 0, ',', ' ') }}
            </p>
            <p class="text-[10px] mt-1 {{ $resultat >= 0 ? 'text-green-500' : 'text-red-500' }}">FCFA</p>
        </div>
        <div class="bg-blue-50 rounded-[4px] border border-blue-200 px-3 py-2 text-center">
            <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wide">Total Produits</p>
            <p class="text-[19px] font-bold tabular-nums text-blue-800 mt-0.5 leading-none">{{ number_format($totalProduits, 0, ',', ' ') }}</p>
            <p class="text-[10px] text-blue-400 mt-1">FCFA</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- CHARGES --}}
        <div class="space-y-2">
            <h2 class="text-[13px] font-bold text-red-800 border-b-2 border-red-200 pb-1">CHARGES</h2>
            @foreach($charges as $sectionName => $sectionAccounts)
            @if($sectionAccounts->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-3 py-1.5 bg-red-50 border-b border-red-100">
                    <h3 class="text-[11.5px] font-bold text-red-800 uppercase tracking-wide">{{ $sectionName }}</h3>
                </div>
                <table class="w-full text-[12.5px] border-collapse">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sectionAccounts->where('net', '>', 0) as $account)
                        <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-red-50/40">
                            <td class="px-3 py-1 w-full">
                                <span class="font-mono text-violet-600 font-semibold text-[11px]">{{ $account->code }}</span>
                                <span class="text-gray-700 ml-1 text-[11.5px]">{{ $account->name }}</span>
                            </td>
                            <td class="px-3 py-1 text-right tabular-nums font-semibold text-red-700 whitespace-nowrap">
                                {{ number_format($account->net, 0, ',', ' ') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-[#f7faf8]">
                        <tr>
                            <td class="px-3 py-1 text-[10px] font-bold text-gray-500 uppercase w-full">Sous-total</td>
                            <td class="px-3 py-1 text-right tabular-nums font-bold text-red-800 whitespace-nowrap">
                                {{ number_format($sectionAccounts->sum('net'), 0, ',', ' ') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
            @endforeach
            <div class="bg-red-700 text-white rounded-[4px] px-3 py-2 flex justify-between font-bold text-[13px]">
                <span>TOTAL CHARGES</span>
                <span class="tabular-nums">{{ number_format($totalCharges, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        {{-- PRODUITS --}}
        <div class="space-y-2">
            <h2 class="text-[13px] font-bold text-blue-800 border-b-2 border-blue-200 pb-1">PRODUITS</h2>
            @foreach($produits as $sectionName => $sectionAccounts)
            @if($sectionAccounts->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-3 py-1.5 bg-blue-50 border-b border-blue-100">
                    <h3 class="text-[11.5px] font-bold text-blue-800 uppercase tracking-wide">{{ $sectionName }}</h3>
                </div>
                <table class="w-full text-[12.5px] border-collapse">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sectionAccounts->where('net', '>', 0) as $account)
                        <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-blue-50/40">
                            <td class="px-3 py-1 w-full">
                                <span class="font-mono text-violet-600 font-semibold text-[11px]">{{ $account->code }}</span>
                                <span class="text-gray-700 ml-1 text-[11.5px]">{{ $account->name }}</span>
                            </td>
                            <td class="px-3 py-1 text-right tabular-nums font-semibold text-blue-700 whitespace-nowrap">
                                {{ number_format($account->net, 0, ',', ' ') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-[#f7faf8]">
                        <tr>
                            <td class="px-3 py-1 text-[10px] font-bold text-gray-500 uppercase w-full">Sous-total</td>
                            <td class="px-3 py-1 text-right tabular-nums font-bold text-blue-800 whitespace-nowrap">
                                {{ number_format($sectionAccounts->sum('net'), 0, ',', ' ') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
            @endforeach
            <div class="bg-blue-700 text-white rounded-[4px] px-3 py-2 flex justify-between font-bold text-[13px]">
                <span>TOTAL PRODUITS</span>
                <span class="tabular-nums">{{ number_format($totalProduits, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

    </div>

    {{-- Result line --}}
    <div class="rounded-[4px] border {{ $resultat >= 0 ? 'border-green-500 bg-green-600' : 'border-red-500 bg-red-600' }} text-white px-3 py-1.5 flex justify-between items-center">
        <div>
            <p class="text-[12px] font-medium opacity-80 uppercase tracking-wide">Résultat net de l'exercice</p>
            <p class="text-[11px] opacity-60">{{ $resultat >= 0 ? 'Produits > Charges → Bénéfice' : 'Charges > Produits → Perte' }}</p>
        </div>
        <p class="text-[24px] font-bold tabular-nums leading-none">{{ number_format(abs($resultat), 0, ',', ' ') }} FCFA</p>
    </div>

</div>
@endsection
