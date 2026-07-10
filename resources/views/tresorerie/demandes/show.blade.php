@extends('layouts.erp')
@section('title', 'Demande ' . $demande->number)

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.demandes.index') }}" class="hover:text-gray-700">Demandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $demande->number }}</span>
@endsection

@section('content')
@php
    $statusBadge = ['brouillon'=>'bg-gray-100 text-gray-600','soumis'=>'bg-amber-100 text-amber-700','valide'=>'bg-blue-100 text-blue-700','rejete'=>'bg-red-100 text-red-700','paye'=>'bg-emerald-100 text-emerald-700'];
    $statusLabel = ['brouillon'=>'Brouillon','soumis'=>'Soumise','valide'=>'Validée','rejete'=>'Rejetée','paye'=>'Payée'];
@endphp
<div class="max-w-2xl mx-auto space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900 font-mono">{{ $demande->number }}</h1>
            <p class="text-sm text-gray-500">{{ $demande->object }}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusBadge[$demande->status] ?? 'bg-gray-100' }}">{{ $statusLabel[$demande->status] ?? $demande->status }}</span>
    </div>

    {{-- Étapes workflow --}}
    <div class="bg-white rounded-[4px] border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between text-xs">
            @foreach(['brouillon'=>'Brouillon','soumis'=>'Soumise','valide'=>'Validée','paye'=>'Payée'] as $step => $lbl)
                @php
                    $order = ['brouillon'=>0,'soumis'=>1,'valide'=>2,'paye'=>3];
                    $cur = $demande->status === 'rejete' ? -1 : ($order[$demande->status] ?? 0);
                    $done = $order[$step] <= $cur;
                @endphp
                <div class="flex-1 flex flex-col items-center">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center font-bold {{ $done ? 'bg-emerald-700 text-white' : 'bg-gray-100 text-gray-400' }}">{{ $order[$step]+1 }}</span>
                    <span class="mt-1 {{ $done ? 'text-emerald-800 font-medium' : 'text-gray-400' }}">{{ $lbl }}</span>
                </div>
                @if(!$loop->last)<div class="flex-1 h-0.5 {{ $done ? 'bg-emerald-300' : 'bg-gray-100' }} mb-4"></div>@endif
            @endforeach
        </div>
        @if($demande->status === 'rejete')
        <p class="text-center text-xs text-red-600 mt-2 font-medium">Demande rejetée</p>
        @endif
    </div>

    {{-- Détails --}}
    <div class="bg-white rounded-[4px] border border-gray-200 shadow-sm p-6 space-y-3 text-sm">
        <div class="flex justify-between"><span class="text-gray-500">Montant</span><span class="font-bold font-mono text-gray-900">{{ number_format($demande->amount, 0, ',', ' ') }} FCFA</span></div>
        <div class="flex justify-between"><span class="text-gray-500">Bénéficiaire</span><span class="text-gray-900">{{ $demande->beneficiaryName() }}</span></div>
        @if($demande->due_date)<div class="flex justify-between"><span class="text-gray-500">Échéance</span><span class="text-gray-900">{{ $demande->due_date->format('d/m/Y') }}</span></div>@endif
        <div class="flex justify-between"><span class="text-gray-500">Priorité</span><span class="text-gray-900 capitalize">{{ $demande->priority }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500">Demandé par</span><span class="text-gray-900">{{ $demande->requestedBy?->name ?? '—' }}</span></div>
        @if($demande->validated_by)<div class="flex justify-between"><span class="text-gray-500">Validé par</span><span class="text-gray-900">{{ $demande->validatedBy?->name }} le {{ $demande->validated_at?->format('d/m/Y') }}</span></div>@endif
        @if($demande->supplierPayment)
        <div class="flex justify-between"><span class="text-gray-500">Décaissement</span><a href="{{ route('tresorerie.decaissements.show', $demande->supplierPayment) }}" class="text-emerald-700 hover:underline font-mono">{{ $demande->supplierPayment->number }}</a></div>
        @endif
        @if($demande->rejection_reason)
        <div class="pt-2 border-t border-gray-100"><p class="text-red-500 mb-1">Motif rejet</p><p class="text-red-700">{{ $demande->rejection_reason }}</p></div>
        @endif
        @if($demande->notes)
        <div class="pt-2 border-t border-gray-100"><p class="text-gray-500 mb-1">Notes</p><p class="text-gray-700">{{ $demande->notes }}</p></div>
        @endif
    </div>

    {{-- Actions workflow --}}
    @if($demande->isSubmittable())
    @can('treasury.write')
    <form method="POST" action="{{ route('tresorerie.demandes.submit', $demande) }}"
          data-confirm="Soumettre la demande {{ $demande->number }} pour validation ?" data-confirm-title="Soumettre" data-confirm-label="Soumettre" data-confirm-danger="false">
        @csrf
        <button type="submit" class="w-full px-5 py-2.5 bg-amber-500 text-white rounded-[4px] text-sm font-semibold hover:bg-amber-600">Soumettre pour validation</button>
    </form>
    @endcan
    @endif

    @if($demande->isValidatable())
    @can('treasury.validate')
    <div class="bg-white rounded-[4px] border border-amber-200 shadow-sm p-4" x-data="{ rejectOpen: false }">
        <p class="text-sm text-gray-600 mb-3">Validation requise @if($demande->required_role)(rôle « {{ $demande->required_role }} »)@endif.</p>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('tresorerie.demandes.approve', $demande) }}"
                  data-confirm="Valider la demande {{ $demande->number }} ?" data-confirm-title="Valider" data-confirm-label="Valider" data-confirm-danger="false">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700">Valider</button>
            </form>
            <button type="button" @click="rejectOpen = !rejectOpen" class="px-3 py-1.5 border border-red-300 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50">Rejeter</button>
        </div>
        <form x-show="rejectOpen" x-cloak method="POST" action="{{ route('tresorerie.demandes.reject', $demande) }}" class="mt-3 flex gap-2">
            @csrf
            <input type="text" name="motif" required minlength="5" maxlength="500" placeholder="Motif du rejet…" class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-300">
            <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 whitespace-nowrap">Confirmer rejet</button>
        </form>
    </div>
    @endcan
    @endif

    @if($demande->isPayable())
    @can('payments.create')
    <div class="bg-white rounded-[4px] border border-emerald-200 shadow-sm p-4">
        <p class="text-sm font-medium text-gray-700 mb-3">Payer cette demande (génère un décaissement)</p>
        @if(!$demande->supplier_id)
        <p class="text-xs text-amber-600">Aucun fournisseur enregistré — paiement manuel via le module Décaissements.</p>
        @else
        <form method="POST" action="{{ route('tresorerie.demandes.pay', $demande) }}" class="space-y-3"
              data-confirm="Payer la demande {{ $demande->number }} ({{ number_format($demande->amount,0,',',' ') }} FCFA) ?" data-confirm-title="Payer" data-confirm-label="Payer" data-confirm-danger="false">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <select name="cash_account_id" required class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    <option value="">Compte…</option>
                    @foreach($cashAccounts as $ca)<option value="{{ $ca->id }}">{{ $ca->name }}</option>@endforeach
                </select>
                <select name="payment_method_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    <option value="">Mode…</option>
                    @foreach($paymentMethods as $pm)<option value="{{ $pm->id }}" @selected($demande->payment_method_id==$pm->id)>{{ $pm->name }}</option>@endforeach
                </select>
                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            </div>
            <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-700">Générer le décaissement</button>
        </form>
        @endif
    </div>
    @endcan
    @endif

    <a href="{{ route('tresorerie.demandes.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour
    </a>

</div>
@endsection
