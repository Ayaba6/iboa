@extends('layouts.erp')
@section('title', 'Note de frais — '.$report->title)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.frais.index') }}" class="hover:text-gray-700">Notes de frais</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $report->title }}</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F';
    $sc = ['brouillon'=>'text-gray-500','soumise'=>'text-amber-600','approuvee'=>'text-blue-700','rejetee'=>'text-red-600','remboursee'=>'text-emerald-700'][$report->status] ?? 'text-gray-500';
@endphp
<div class="space-y-4">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $report->title }}</h1>
            <p class="text-sm text-gray-500">{{ $report->employee?->full_name ?? '—' }} · {{ optional($report->report_date)->format('d/m/Y') }} · <span class="{{ $sc }} font-semibold">{{ $report->statusLabel() }}</span></p>
        </div>
        <div class="flex items-center gap-2">
            <p class="text-[24px] font-bold text-gray-900 tabular-nums">{{ $fmt($report->total_amount) }}</p>
            @can('rh.employees.manage')@if($report->isEditable())<a href="{{ route('rh.frais.edit', $report) }}" class="border border-gray-300 text-gray-700 text-sm font-medium px-4 py-1.5 rounded-[4px] hover:bg-gray-50">Modifier</a>@endif @endcan
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($report->status === 'rejetee' && $report->reject_reason)<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2">Rejetée : {{ $report->reject_reason }}</div>@endif

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#3b4248] text-white">
                <tr>
                    <th class="{{ $th }} text-left">Date</th>
                    <th class="{{ $th }} text-left">Catégorie</th>
                    <th class="{{ $th }} text-left">Description</th>
                    <th class="{{ $th }} text-right">Montant</th>
                    <th class="{{ $th }} text-right">Dont TVA</th>
                    <th class="{{ $th }} text-center">Justif.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($report->lines as $l)
                <tr>
                    <td class="px-3 py-1.5 tabular-nums">{{ optional($l->expense_date)->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ \App\Models\ExpenseReport::CATEGORIES[$l->category] ?? $l->category }}</td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $l->description ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($l->amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $l->tax_amount > 0 ? $fmt($l->tax_amount) : '—' }}</td>
                    <td class="px-3 py-1.5 text-center">{!! $l->has_receipt ? '<span class="text-emerald-700">✓</span>' : '<span class="text-red-400">✕</span>' !!}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Aucune ligne.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="bg-gray-50 font-semibold"><td colspan="3" class="px-3 py-1.5 text-right text-gray-500">Total</td><td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($report->total_amount) }}</td><td colspan="2"></td></tr></tfoot>
        </table>
    </div>

    {{-- Workflow --}}
    @can('rh.employees.manage')
    <div class="flex items-center gap-2 flex-wrap">
        @if($report->isEditable())
        <form method="POST" action="{{ route('rh.frais.submit', $report) }}">@csrf<button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">Soumettre</button></form>
        @elseif($report->status === 'soumise')
        <form method="POST" action="{{ route('rh.frais.approve', $report) }}">@csrf<button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">Approuver</button></form>
        <form method="POST" action="{{ route('rh.frais.reject', $report) }}" class="flex items-center gap-1">@csrf
            <input name="reject_reason" placeholder="Motif du rejet" class="h-9 px-2 border border-gray-300 rounded-[4px] text-[13px]">
            <button class="border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold px-4 py-2 rounded-[4px]">Rejeter</button>
        </form>
        @elseif($report->status === 'approuvee')
        <form method="POST" action="{{ route('rh.frais.reimburse', $report) }}" class="flex items-center gap-1">@csrf
            <input name="payment_method" placeholder="Moyen (virement, caisse…)" class="h-9 px-2 border border-gray-300 rounded-[4px] text-[13px]">
            <button class="bg-[#3b4248] hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-[4px]">Marquer remboursée</button>
        </form>
        @elseif($report->status === 'remboursee')
        <span class="text-emerald-700 text-sm font-semibold">✓ Remboursée le {{ optional($report->reimbursed_at)->format('d/m/Y') }}{{ $report->payment_method ? ' ('.$report->payment_method.')' : '' }}</span>
        @endif
    </div>
    @endcan

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Lignes : <span class="text-white font-semibold">{{ $report->lines->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
