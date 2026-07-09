@extends('layouts.erp')
@section('title', "Prêt {$pret->loan_number}")
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.prets.index') }}" class="hover:text-gray-700">Prêts</a>
    <span class="mx-1">/</span><span>{{ $pret->loan_number }}</span>
@endsection

@section('content')
<div x-data="{ showPaymentModal: false }">

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Prêt {{ $pret->loan_number }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            <a href="{{ route('rh.employes.show', $pret->employee) }}" class="hover:text-emerald-700 font-medium">
                {{ $pret->employee->full_name }}
            </a>
            · {{ $pret->employee->matricule }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if($pret->status === 'actif')
            @if(!$pret->approved_at)
                <form method="POST" action="{{ route('rh.prets.approve', $pret) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700">
                        Approuver
                    </button>
                </form>
            @endif
            <button @click="showPaymentModal = true"
                    class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                + Remboursement
            </button>
            <form method="POST" action="{{ route('rh.prets.cancel', $pret) }}"
                  onsubmit="return confirm('Annuler ce prêt ?')">
                @csrf
                <button type="submit" class="px-3 py-1.5 border border-red-300 text-red-600 rounded-[4px] text-sm hover:bg-red-50">
                    Annuler le prêt
                </button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-[4px] text-emerald-700 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-[4px] text-red-700 text-sm">{{ session('error') }}</div>
@endif

@php
    $pct = $pret->amount > 0 ? round(($pret->amount - $pret->remaining_balance) / $pret->amount * 100) : 0;
    $statusColors = ['actif'=>'blue','rembourse'=>'emerald','annule'=>'red'];
    $statusLabels = ['actif'=>'Actif','rembourse'=>'Remboursé','annule'=>'Annulé'];
    $color = $statusColors[$pret->status] ?? 'gray';
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

    {{-- KPI cards --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Montant accordé</p>
        <p class="text-[16px] font-bold text-gray-900 font-mono">{{ number_format($pret->amount, 0, ',', ' ') }} F</p>
        <p class="text-xs text-gray-400 mt-1">{{ $pret->nb_months }} mensualité(s) de {{ number_format($pret->monthly_deduction, 0, ',', ' ') }} F</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Solde restant dû</p>
        <p class="text-[16px] font-bold {{ $pret->remaining_balance > 0 ? 'text-orange-600' : 'text-emerald-600' }} font-mono">
            {{ number_format($pret->remaining_balance, 0, ',', ' ') }} F
        </p>
        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
            <div class="bg-emerald-600 h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1">{{ $pct }} % remboursé</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Statut</p>
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-{{ $color }}-100 text-{{ $color }}-700">
            {{ $statusLabels[$pret->status] ?? $pret->status }}
        </span>
        <div class="mt-3 text-xs text-gray-500 space-y-0.5">
            <div>Début : {{ \Carbon\Carbon::parse($pret->start_date)->format('d/m/Y') }}</div>
            @if($pret->end_date)<div>Fin prévue : {{ \Carbon\Carbon::parse($pret->end_date)->format('d/m/Y') }}</div>@endif
            @if($pret->approved_at)<div>Approuvé le : {{ $pret->approved_at->format('d/m/Y') }}</div>@endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Historique remboursements --}}
    <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Historique des remboursements</h2>
            <span class="text-xs text-gray-400">{{ $pret->payments->count() }} paiement(s)</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500">Période</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500">Montant remboursé</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500">Solde après</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500">Saisi par</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($pret->payments->sortByDesc('period_year')->sortByDesc('period_month') as $payment)
                <tr>
                    <td class="px-3 py-1.5 font-medium">{{ sprintf('%02d/%04d', $payment->period_month, $payment->period_year) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono text-emerald-800">{{ number_format($payment->amount, 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-right font-mono text-gray-600">{{ number_format($payment->balance_after, 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-xs text-gray-400">{{ $payment->createdBy?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucun remboursement enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Infos complémentaires --}}
    <div class="space-y-3">
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Détails du prêt</h2>
            <dl class="space-y-2 text-sm">
                @if($pret->reason)
                    <div>
                        <dt class="text-xs text-gray-400">Motif</dt>
                        <dd>{{ $pret->reason }}</dd>
                    </div>
                @endif
                @if($pret->notes)
                    <div>
                        <dt class="text-xs text-gray-400">Notes</dt>
                        <dd class="text-xs text-gray-600">{{ $pret->notes }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-400">Créé par</dt>
                    <dd>{{ $pret->createdBy?->name ?? '—' }} le {{ $pret->created_at->format('d/m/Y') }}</dd>
                </div>
                @if($pret->approvedBy)
                    <div>
                        <dt class="text-xs text-gray-400">Approuvé par</dt>
                        <dd>{{ $pret->approvedBy->name }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>

{{-- Modal remboursement --}}
<div x-show="showPaymentModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6" @click.outside="showPaymentModal = false">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Enregistrer un remboursement</h3>
        <form method="POST" action="{{ route('rh.prets.payment', $pret) }}">
            @csrf
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mois</label>
                        <select name="period_month" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                            @for($m=1; $m<=12; $m++)
                                <option value="{{ $m }}" @selected($m == now()->month)>{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Année</label>
                        <input type="number" name="period_year" value="{{ now()->year }}" min="2000" max="2100"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Montant remboursé (FCFA)
                        <span class="text-xs text-gray-400">— solde : {{ number_format($pret->remaining_balance, 0, ',', ' ') }} F</span>
                    </label>
                    <input type="number" name="amount"
                           value="{{ min($pret->monthly_deduction, $pret->remaining_balance) }}"
                           min="1" max="{{ $pret->remaining_balance }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
                    <input type="text" name="notes" maxlength="500"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showPaymentModal = false"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                        Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
