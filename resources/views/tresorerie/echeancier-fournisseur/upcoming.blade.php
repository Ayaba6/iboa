@extends('layouts.erp')
@section('title', 'Échéancier fournisseurs')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Échéancier fournisseurs</span>
@endsection

@section('content')
@php
    $rem = fn($inv) => (int) ($inv->total_ttc - $inv->paid_amount);
@endphp
<div class="space-y-3">

    {{-- Header + KPIs --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Échéancier fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">Dettes à payer par date d'échéance</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-xs text-gray-500">Fenêtre</label>
            <select name="window" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
                @foreach([7,14,30,60,90] as $w)
                    <option value="{{ $w }}" @selected($window == $w)>{{ $w }} jours</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border-2 border-red-200 p-4">
            <p class="text-xs font-medium text-red-600 uppercase">En retard</p>
            <p class="mt-1 text-[16px] font-bold text-red-700 tabular-nums">{{ number_format($totalOverdue, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
            <p class="text-xs text-red-500 mt-0.5">{{ $overdue->count() }} facture(s)</p>
        </div>
        <div class="bg-white rounded-[4px] border-2 border-amber-200 p-4">
            <p class="text-xs font-medium text-amber-600 uppercase">À venir ({{ $window }} j)</p>
            <p class="mt-1 text-[16px] font-bold text-amber-700 tabular-nums">{{ number_format($totalUpcoming, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
            <p class="text-xs text-amber-500 mt-0.5">{{ $upcoming->count() }} facture(s)</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Sans échéance</p>
            <p class="mt-1 text-[16px] font-bold text-gray-700 tabular-nums">{{ number_format($totalSans, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $sansEcheance->count() }} facture(s)</p>
        </div>
    </div>

    @php
        $sections = [
            ['En retard', $overdue, 'text-red-600', true],
            ['À venir — ' . $window . ' jours', $upcoming, 'text-amber-600', false],
            ['Sans échéance', $sansEcheance, 'text-gray-500', false],
        ];
    @endphp

    @foreach($sections as [$title, $rows, $cls, $isOverdue])
    @if($rows->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold {{ $cls }}">{{ $title }} <span class="text-gray-400">({{ $rows->count() }})</span></h2>
        </div>
        <div class="tbl-scroll">
            <table class="tbl w-full">
                <thead>
                    <tr>
                        <th class="text-left">Facture</th>
                        <th class="text-left">Fournisseur</th>
                        <th class="text-left">Échéance</th>
                        <th class="text-right">Total TTC</th>
                        <th class="text-right">Payé</th>
                        <th class="text-right">Reste dû</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $inv)
                    <tr>
                        <td class="font-mono text-emerald-700">{{ $inv->number }}</td>
                        <td class="text-gray-800">{{ $inv->supplier?->name ?? '—' }}</td>
                        <td class="tabular-nums {{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $inv->due_at?->format('d/m/Y') ?? '—' }}
                            @if($isOverdue && $inv->due_at)
                                <span class="text-xs">({{ (int) $inv->due_at->diffInDays(now()) }} j)</span>
                            @endif
                        </td>
                        <td class="text-right font-mono tabular-nums text-gray-600">{{ number_format($inv->total_ttc, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-400">{{ number_format($inv->paid_amount, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums text-gray-900">{{ number_format($rem($inv), 0, ',', ' ') }}</td>
                        <td class="text-right">
                            <a href="{{ route('achats.factures-fournisseurs.show', $inv) }}" class="text-emerald-700 hover:underline text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endforeach

    @if($overdue->isEmpty() && $upcoming->isEmpty() && $sansEcheance->isEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm font-medium">Aucune dette fournisseur en cours.</p>
    </div>
    @endif

</div>
@endsection
