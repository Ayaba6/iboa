@extends('layouts.erp')
@section('title', 'Demandes de paiement')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Demandes de paiement</span>
@endsection

@section('content')
@php
    $statusBadge = [
        'brouillon' => 'bg-gray-100 text-gray-600',
        'soumis'    => 'bg-amber-100 text-amber-700',
        'valide'    => 'bg-blue-100 text-blue-700',
        'rejete'    => 'bg-red-100 text-red-700',
        'paye'      => 'bg-emerald-100 text-emerald-700',
    ];
    $statusLabel = ['brouillon'=>'Brouillon','soumis'=>'Soumise','valide'=>'Validée','rejete'=>'Rejetée','paye'=>'Payée'];
    $prioBadge = ['basse'=>'text-gray-400','normale'=>'text-gray-600','haute'=>'text-orange-600','urgente'=>'text-red-600 font-bold'];
@endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Demandes de paiement</h1>
            <p class="text-sm text-gray-500 mt-0.5">Circuit demande → validation → paiement</p>
        </div>
        @can('treasury.write')
        <a href="{{ route('tresorerie.demandes.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle demande
        </a>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">À valider</p>
            <p class="text-[16px] font-bold text-amber-600 mt-1">{{ $stats['soumises'] }}</p>
            <p class="text-xs text-gray-400">demandes soumises</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">À payer</p>
            <p class="text-[16px] font-bold text-blue-600 mt-1">{{ $stats['a_payer_count'] }}</p>
            <p class="text-xs text-gray-400">validées en attente</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Montant à payer</p>
            <p class="text-[16px] font-bold text-emerald-700 tabular-nums mt-1">{{ number_format($stats['a_payer_montant'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Payées</p>
            <p class="text-[16px] font-bold text-emerald-600 mt-1">{{ $stats['payees'] }}</p>
        </div>
    </div>

    {{-- Filtre statut --}}
    <form method="GET" class="flex gap-2">
        <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Tous les statuts</option>
            @foreach($statusLabel as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N°</th>
                        <th class="text-left">Objet</th>
                        <th class="text-left">Bénéficiaire</th>
                        <th class="text-right">Montant</th>
                        <th class="text-left">Échéance</th>
                        <th class="text-center">Priorité</th>
                        <th class="text-center">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $r)
                    <tr>
                        <td class="font-mono font-semibold text-emerald-700">{{ $r->number }}</td>
                        <td class="text-gray-800">{{ \Illuminate\Support\Str::limit($r->object, 40) }}</td>
                        <td class="text-gray-600">{{ $r->beneficiaryName() }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums text-gray-900">{{ number_format($r->amount, 0, ',', ' ') }}</td>
                        <td class="tabular-nums text-gray-500">{{ $r->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-center text-xs uppercase {{ $prioBadge[$r->priority] ?? '' }}">{{ $r->priority }}</td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusBadge[$r->status] ?? 'bg-gray-100' }}">{{ $statusLabel[$r->status] ?? $r->status }}</span>
                        </td>
                        <td class="text-right"><a href="{{ route('tresorerie.demandes.show', $r) }}" class="text-emerald-700 hover:underline text-xs font-medium">Voir →</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune demande de paiement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $requests->links() }}</div>
        @endif
    </div>

</div>
@endsection
