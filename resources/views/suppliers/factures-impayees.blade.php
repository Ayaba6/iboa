@extends('layouts.erp')
@section('title', 'Factures fournisseurs impayées')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Factures impayées</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Factures fournisseurs impayées</h1>
            <p class="text-sm text-gray-500 mt-0.5">Factures avec solde restant dû — au {{ $today->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('suppliers.factures-impayees.export-excel', array_filter(['supplier_id' => $supplierId])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('suppliers.factures-impayees.export-pdf', array_filter(['supplier_id' => $supplierId])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('suppliers.balance-agee') }}" class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Balance âgée</a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-200 p-4">
        <div class="flex gap-3">
            <select name="supplier_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 w-72">
                <option value="">Tous les fournisseurs</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if($supplierId)
            <a href="{{ route('suppliers.factures-impayees') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Factures impayées</p>
            <p class="text-[16px] font-bold text-gray-900">{{ $invoices->count() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">En retard</p>
            <p class="text-[16px] font-bold text-red-700">{{ $invoices->filter(fn($i) => $i->due_at && $i->due_at < $today)->count() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-amber-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Restant dû total</p>
            <p class="text-[17px] font-bold text-red-700 tabular-nums">{{ number_format($totalDue, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N° Facture</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Fournisseur</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réf. Fourn.</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Échéance</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                        <th class="px-3 py-1.5 text-right  text-xs font-semibold text-red-700   uppercase tracking-wider">Restant dû</th>
                        <th class="px-3 py-1.5 text-center text-xs font-semibold text-red-700   uppercase tracking-wider">Retard</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $inv)
                    @php
                        $overdue = $inv->due_at && $inv->due_at < $today;
                        $days    = $inv->due_at ? $today->diffInDays($inv->due_at, false) * -1 : 0;
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors {{ $overdue ? 'bg-red-50/30' : '' }}">
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-700">{{ $inv->number }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $inv->supplier?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $inv->supplier_invoice_number ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center text-gray-600 whitespace-nowrap">{{ $inv->received_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center whitespace-nowrap {{ $overdue ? 'text-red-700 font-semibold' : 'text-gray-600' }}">
                            {{ $inv->due_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($inv->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-red-700">{{ number_format($inv->remaining_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @if($overdue)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                    {{ (int)$days }} j
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucune facture impayée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($invoices->count())
                <tfoot>
                    <tr class="text-white" style="background:#065f46;">
                        <td colspan="5" class="px-3 py-1.5 font-bold text-xs uppercase">TOTAL</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($invoices->sum('total_ttc'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totalDue, 0, ',', ' ') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
