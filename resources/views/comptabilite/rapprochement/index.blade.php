@extends('layouts.erp')
@section('title', 'Rapprochement bancaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapprochement bancaire</span>
@endsection

@section('content')
<div class="space-y-3">

    @php
        $lblX = 'block text-[11px] font-bold text-gray-700 mb-1';
        $inpX = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
        $lkX  = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
        $carX = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    @endphp

    {{-- ── En-tête [X3] ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Rapprochements bancaires</h1>
            <p class="text-[12.5px] text-gray-500">Pointage du relevé bancaire contre les écritures comptables</p>
        </div>
        <div class="flex items-center gap-1.5">
            @can('accounting.write')
            <a href="{{ route('comptabilite.rapprochement.create') }}"
               class="h-8 inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-semibold px-3 rounded-[4px] transition-colors">
                + Nouveau rapprochement
            </a>
            @endcan
            <button type="button" onclick="window.print()"
                    class="h-8 inline-flex items-center border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-50 text-[12px] font-semibold px-3 rounded-[4px] transition-colors">Imprimer</button>
            <a href="{{ route('comptabilite.dashboard') }}"
               class="h-8 inline-flex items-center border border-gray-300 text-gray-500 hover:text-gray-700 hover:bg-gray-50 text-[12px] font-semibold px-3 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

    {{-- ── Fiche critères [X3] ──────────────────────────────────────────────── --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3 items-end">
            <div class="sm:col-span-3">
                <label class="{{ $lblX }}">Compte bancaire</label>
                <div class="relative"><select name="cash_account_id" class="{{ $lkX }}">
                    <option value="">Tous les comptes</option>
                    @foreach($cashAccounts as $ca)
                    <option value="{{ $ca->id }}" {{ ($filters['cash_account_id'] ?? '') == $ca->id ? 'selected' : '' }}>{{ $ca->name }} ({{ $ca->code }})</option>
                    @endforeach
                </select>{!! $carX !!}</div>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lblX }}">Statut</label>
                <div class="relative"><select name="status" class="{{ $lkX }}">
                    <option value="">Tous les statuts</option>
                    <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                    <option value="valide"    {{ ($filters['status'] ?? '') === 'valide'    ? 'selected' : '' }}>Validé</option>
                </select>{!! $carX !!}</div>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lblX }}">Période du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="{{ $inpX }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lblX }}">au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="{{ $inpX }}">
            </div>
            <div class="sm:col-span-3 flex gap-1.5">
                <button type="submit" class="flex-1 h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-semibold rounded-[4px] transition-colors">Rechercher</button>
                @if(array_filter($filters))
                <a href="{{ route('comptabilite.rapprochement.index') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-500 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-[13px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left">N°</th>
                        <th class="px-3 py-1.5 text-left">Compte bancaire</th>
                        <th class="px-3 py-1.5 text-left">Période</th>
                        <th class="px-3 py-1.5 text-left">Date relevé</th>
                        <th class="px-3 py-1.5 text-right">Solde relevé (XOF)</th>
                        <th class="px-3 py-1.5 text-right">Solde compta (XOF)</th>
                        <th class="px-3 py-1.5 text-right">Écart (XOF)</th>
                        <th class="px-3 py-1.5 text-center">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reconciliations as $rec)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 whitespace-nowrap">
                            <a href="{{ route('comptabilite.rapprochement.show', $rec) }}" class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[12px]">{{ $rec->number }}</a>
                        </td>
                        <td class="px-3 py-1 text-gray-700 whitespace-nowrap">{{ $rec->cashAccount?->name }}</td>
                        <td class="px-3 py-1 text-gray-500 text-[12px] tabular-nums whitespace-nowrap">
                            {{ $rec->period_start?->format('d/m/Y') }} → {{ $rec->period_end?->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-1 text-gray-700 tabular-nums whitespace-nowrap">{{ $rec->statement_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700 whitespace-nowrap">{{ number_format($rec->closing_balance, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700 whitespace-nowrap">{{ number_format($rec->book_balance, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold whitespace-nowrap {{ $rec->difference == 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $rec->difference == 0 ? '✓ 0' : number_format($rec->difference, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1 text-center">
                            @php $colors = ['brouillon' => 'bg-gray-100 text-gray-700', 'valide' => 'bg-emerald-100 text-emerald-700']; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $colors[$rec->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $rec->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1 text-right">
                            <a href="{{ route('comptabilite.rapprochement.show', $rec) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium whitespace-nowrap">Détail →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                            Aucun rapprochement trouvé.
                            @can('accounting.write')
                                <a href="{{ route('comptabilite.rapprochement.create') }}" class="text-emerald-700 hover:underline ml-1">Créer le premier →</a>
                            @endcan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reconciliations->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $reconciliations->links() }}</div>
        @endif
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Filtre actif : <span class="text-white font-semibold">{{ array_filter($filters) ? implode(', ', array_keys(array_filter($filters))) : 'Aucun' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
