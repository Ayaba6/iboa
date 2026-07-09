@extends('layouts.erp')
@section('title', 'Relevé fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Relevé fournisseur</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Relevé fournisseur</h1>
            <p class="text-sm text-gray-500 mt-0.5">Mouvements et solde d'un fournisseur sur une période</p>
        </div>
        @if($supplier && $dateFrom && $dateTo)
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('suppliers.releve.export-excel', array_filter(['supplier_id' => $supplierId, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('suppliers.releve.export-pdf', array_filter(['supplier_id' => $supplierId, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('suppliers.balance') }}"          class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Balance</a>
            <a href="{{ route('suppliers.grand-livre') }}"      class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Grand livre</a>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex flex-wrap gap-3">
            <select name="supplier_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 w-72">
                <option value="">— Sélectionnez un fournisseur —</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" placeholder="Du">
            <input type="date" name="date_to"   value="{{ $dateTo }}"   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" placeholder="Au">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Afficher
            </button>
            @if($supplierId)
            <a href="{{ route('suppliers.releve') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    @if(!$supplier || !$dateFrom || !$dateTo)
    <div class="bg-amber-50 border border-emerald-200 rounded-[4px] p-6 text-center">
        <p class="text-amber-700 text-sm">Sélectionnez un fournisseur et une période pour afficher le relevé.</p>
    </div>
    @else

    {{-- Supplier info + opening balance --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 col-span-2">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Fournisseur</p>
            <p class="text-[16px] font-bold text-gray-900">{{ $supplier->name }}</p>
            @if($supplier->email)<p class="text-sm text-gray-500">{{ $supplier->email }}</p>@endif
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Solde d'ouverture</p>
            <p class="text-[17px] font-bold {{ $soldeOuv >= 0 ? 'text-red-700' : 'text-emerald-600' }} tabular-nums">
                {{ number_format(abs($soldeOuv), 0, ',', ' ') }}
                <span class="text-xs font-normal text-gray-400">FCFA</span>
            </p>
            <p class="text-xs text-gray-400 mt-0.5">Avant le {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</p>
        </div>
    </div>

    {{-- Lines table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Échéance</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit (FCFA)</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit (FCFA)</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde (FCFA)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Opening balance row --}}
                    @if($soldeOuv != 0)
                    <tr class="bg-amber-50/50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs italic" colspan="3">Report antérieur</td>
                        <td></td><td></td><td></td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums {{ $soldeOuv >= 0 ? 'text-red-700' : 'text-emerald-600' }}">
                            {{ number_format(abs($soldeOuv), 0, ',', ' ') }}
                        </td>
                    </tr>
                    @endif
                    @forelse($lines as $line)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-700 whitespace-nowrap">
                            {{ $line['date'] instanceof \Carbon\Carbon ? $line['date']->format('d/m/Y') : \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $line['reference'] }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @if($line['type'] === 'facture')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Facture</span>
                            @elseif($line['type'] === 'retour')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">Retour</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700">Paiement</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap text-xs">
                            {{ $line['echeance'] ? \Carbon\Carbon::parse($line['echeance'])->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-800">
                            {{ $line['debit'] > 0 ? number_format($line['debit'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-800">
                            {{ $line['credit'] > 0 ? number_format($line['credit'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums {{ $line['solde'] >= 0 ? 'text-red-700' : 'text-emerald-600' }}">
                            {{ number_format(abs($line['solde']), 0, ',', ' ') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucun mouvement sur cette période.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($lines->count())
                <tfoot>
                    <tr class="text-white" style="background:#065f46;">
                        <td colspan="4" class="px-3 py-1.5 font-bold text-xs uppercase">Solde de clôture</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($lines->sum('debit'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($lines->sum('credit'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">
                            {{ number_format(abs($lines->last()['solde']), 0, ',', ' ') }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
