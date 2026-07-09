@extends('layouts.erp')
@section('title', $account->name . ' — Transactions')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.index') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $account->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
            @php
                [$iconBg, $iconColor, $badgeBg, $badgeText] = match($account->type) {
                    'banque'       => ['bg-blue-100',   'text-blue-600',   'bg-blue-100',   'text-blue-700'],
                    'mobile_money' => ['bg-purple-100', 'text-purple-600', 'bg-purple-100', 'text-purple-700'],
                    default        => ['bg-slate-100',  'text-slate-600',  'bg-slate-100',  'text-slate-700'],
                };
            @endphp
            <div class="w-12 h-12 {{ $iconBg }} rounded-[4px] flex items-center justify-center flex-shrink-0">
                @if($account->type === 'banque')
                    <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                @elseif($account->type === 'mobile_money')
                    <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                @else
                    <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-[16px] font-bold text-gray-900">{{ $account->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium {{ $badgeBg }} {{ $badgeText }}">
                        {{ $account->typeBadge() }}
                    </span>
                    @if($account->is_default)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-yellow-100 text-yellow-700">Défaut</span>
                    @endif
                </div>
                <p class="text-sm text-gray-400 font-mono mt-0.5">{{ $account->code }}</p>
            </div>
        </div>

        {{-- Balance --}}
        <div class="bg-white border border-gray-300 rounded-[4px] px-3 py-1.5 text-center min-w-[200px]">
            <p class="text-xs text-gray-400 uppercase tracking-wider font-medium">Solde actuel</p>
            <p class="text-[19px] font-bold tabular-nums mt-1
                {{ $account->current_balance >= 0 ? 'text-emerald-800' : 'text-red-700' }}">
                {{ number_format($account->current_balance, 0, ',', ' ') }}
            </p>
            <p class="text-sm text-gray-400">FCFA</p>
        </div>
    </div>

    {{-- Stats bar --}}
    @php
        $totalCredits = $transactions->getCollection()->where('type', 'credit')->sum('amount');
        $totalDebits  = $transactions->getCollection()->where('type', 'debit')->sum('amount');
    @endphp
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-green-50 border border-green-200 rounded-[4px] p-4 text-center">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Crédits (page)</p>
            <p class="text-[16px] font-bold text-green-800 tabular-nums mt-1">
                +{{ number_format($totalCredits, 0, ',', ' ') }} FCFA
            </p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-[4px] p-4 text-center">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Débits (page)</p>
            <p class="text-[16px] font-bold text-red-800 tabular-nums mt-1">
                -{{ number_format($totalDebits, 0, ',', ' ') }} FCFA
            </p>
        </div>
        <div class="bg-[#eef5f0] border border-emerald-200 rounded-[4px] p-4 text-center">
            <p class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Solde ouverture</p>
            <p class="text-[16px] font-bold text-emerald-900 tabular-nums mt-1">
                {{ number_format($account->opening_balance, 0, ',', ' ') }} FCFA
            </p>
        </div>
    </div>

    {{-- Coordonnées bancaires --}}
    @if($account->type === 'banque' && ($account->bank_name || $account->account_number || $account->iban || $account->bank_branch || $account->swift_bic))
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Coordonnées bancaires</h2>
        <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            @if($account->bank_name)
            <div><dt class="text-xs text-gray-400 uppercase tracking-wider">Banque</dt><dd class="text-gray-900 font-medium mt-0.5">{{ $account->bank_name }}</dd></div>
            @endif
            @if($account->bank_branch)
            <div><dt class="text-xs text-gray-400 uppercase tracking-wider">Agence</dt><dd class="text-gray-900 font-medium mt-0.5">{{ $account->bank_branch }}</dd></div>
            @endif
            @if($account->account_number)
            <div><dt class="text-xs text-gray-400 uppercase tracking-wider">N° de compte (RIB)</dt><dd class="text-gray-900 font-mono mt-0.5">{{ $account->account_number }}</dd></div>
            @endif
            @if($account->iban)
            <div><dt class="text-xs text-gray-400 uppercase tracking-wider">IBAN</dt><dd class="text-gray-900 font-mono mt-0.5">{{ $account->iban }}</dd></div>
            @endif
            @if($account->swift_bic)
            <div><dt class="text-xs text-gray-400 uppercase tracking-wider">SWIFT / BIC</dt><dd class="text-gray-900 font-mono mt-0.5">{{ $account->swift_bic }}</dd></div>
            @endif
        </dl>
    </div>
    @endif

    {{-- Transactions table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Historique des transactions</h2>
            <span class="text-xs text-gray-400">{{ $transactions->total() }} transaction(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Solde après</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $tx)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">
                            {{ $tx->transaction_date?->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @if($tx->type === 'credit')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                                    </svg>
                                    Crédit
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                                    </svg>
                                    Débit
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold">
                            @if($tx->type === 'credit')
                                <span class="text-green-700">+{{ number_format($tx->amount, 0, ',', ' ') }} FCFA</span>
                            @else
                                <span class="text-red-700">-{{ number_format($tx->amount, 0, ',', ' ') }} FCFA</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-700 max-w-xs truncate">
                            {{ $tx->label ?? '—' }}
                            @if($tx->reference_type)
                                <span class="text-xs text-gray-400 ml-1">({{ $tx->reference_type }} #{{ $tx->reference_id }})</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums hidden lg:table-cell">
                            <span class="{{ $tx->balance_after >= 0 ? 'text-emerald-800' : 'text-red-700' }} font-medium">
                                {{ number_format($tx->balance_after, 0, ',', ' ') }} FCFA
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucune transaction enregistrée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>

    {{-- Back --}}
    <div>
        <a href="{{ route('tresorerie.caisses.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour aux comptes
        </a>
    </div>

</div>
@endsection
