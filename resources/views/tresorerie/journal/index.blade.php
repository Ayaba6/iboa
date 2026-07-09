@extends('layouts.erp')
@section('title', 'Journal de trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Journal de trésorerie</span>
@endsection

@section('content')
<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Journal de trésorerie</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tous les mouvements de caisse et banque</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <select name="cash_account_id" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
                <option value="">Tous les comptes</option>
                @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($accountId == $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
            <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
            <button type="submit" class="px-4 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Filtrer</button>
        </form>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-emerald-600 uppercase">Total entrées</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-emerald-700">+{{ number_format($totals->entrees ?? 0, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-red-600 uppercase">Total sorties</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-red-700">−{{ number_format($totals->sorties ?? 0, 0, ',', ' ') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Date</th>
                        <th class="text-left">Compte</th>
                        <th class="text-left">Libellé</th>
                        <th class="text-right">Entrée</th>
                        <th class="text-right">Sortie</th>
                        <th class="text-right">Solde après</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                    <tr>
                        <td class="tabular-nums text-gray-600">{{ \Carbon\Carbon::parse($m->transaction_date)->format('d/m/Y') }}</td>
                        <td class="text-gray-800">{{ $m->account_name }}</td>
                        <td class="text-gray-700">{{ $m->label ?? '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-emerald-700">{{ $m->type === 'credit' ? '+'.number_format($m->amount, 0, ',', ' ') : '' }}</td>
                        <td class="text-right font-mono tabular-nums text-red-600">{{ $m->type === 'debit' ? '−'.number_format($m->amount, 0, ',', ' ') : '' }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($m->balance_after, 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucun mouvement sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())<div class="px-3 py-1.5 border-t border-gray-100">{{ $movements->links() }}</div>@endif
    </div>
</div>
@endsection
