@extends('layouts.erp')
@section('title', 'Comptes de trésorerie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Trésorerie</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Comptes de trésorerie</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $accounts->count() }} compte(s) actif(s)</p>
        </div>
        <div class="flex gap-3">
            @can('cash_accounts.manage')
            <a href="{{ route('tresorerie.caisses.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau compte
            </a>
            @endcan
            <a href="{{ route('tresorerie.encaissements.index') }}"
               class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                </svg>
                Encaissements
            </a>
            <a href="{{ route('tresorerie.decaissements.index') }}"
               class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                </svg>
                Décaissements
            </a>
        </div>
    </div>

    {{-- Summary cards (totals by type) --}}
    @php
        $totalBalance = $accounts->sum('current_balance');
        $byType = $accounts->groupBy('type');
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-[#eef5f0] border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Total trésorerie</p>
            <p class="text-[16px] font-bold text-emerald-900 tabular-nums mt-1">
                {{ number_format($totalBalance, 0, ',', ' ') }} FCFA
            </p>
            <p class="text-xs text-emerald-600 mt-1">{{ $accounts->count() }} compte(s)</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">Banque</p>
            <p class="text-[17px] font-bold text-blue-900 tabular-nums mt-1">
                {{ number_format($byType->get('banque', collect())->sum('current_balance'), 0, ',', ' ') }} FCFA
            </p>
            <p class="text-xs text-blue-500 mt-1">{{ $byType->get('banque', collect())->count() }} compte(s)</p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Caisse + Mobile Money</p>
            <p class="text-[17px] font-bold text-emerald-900 tabular-nums mt-1">
                @php
                    $caisseTotal = $byType->get('caisse', collect())->sum('current_balance')
                                 + $byType->get('mobile_money', collect())->sum('current_balance');
                @endphp
                {{ number_format($caisseTotal, 0, ',', ' ') }} FCFA
            </p>
            <p class="text-xs text-emerald-500 mt-1">
                {{ $byType->get('caisse', collect())->count() + $byType->get('mobile_money', collect())->count() }} compte(s)
            </p>
        </div>
    </div>

    {{-- Accounts grid --}}
    @if($accounts->isEmpty())
        <div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-gray-500 text-sm">Aucun compte de trésorerie configuré.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($accounts as $account)
                @php
                    // Determine card styling by type
                    [$borderClass, $badgeBg, $badgeText, $balanceClass, $iconBg] = match($account->type) {
                        'banque'       => ['border-blue-200',   'bg-blue-100',   'text-blue-700',   'text-blue-900',   'bg-blue-100'],
                        'mobile_money' => ['border-purple-200', 'bg-purple-100', 'text-purple-700', 'text-purple-900', 'bg-purple-100'],
                        default        => ['border-slate-200',  'bg-slate-100',  'text-slate-700',  'text-slate-900',  'bg-slate-100'],
                    };

                    // Last transaction date
                    $lastTx = $account->transactions()->latest('transaction_date')->first();
                @endphp
                <div class="bg-white rounded-[4px] border {{ $borderClass }} p-5 hover:shadow-md transition-shadow flex flex-col gap-3">

                    {{-- Top row --}}
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 {{ $iconBg }} rounded-[4px] flex items-center justify-center flex-shrink-0">
                                @if($account->type === 'banque')
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                @elseif($account->type === 'mobile_money')
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm leading-tight">{{ $account->name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ $account->code }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $badgeBg }} {{ $badgeText }} flex-shrink-0">
                            {{ $account->typeBadge() }}
                        </span>
                    </div>

                    {{-- Balance --}}
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Solde actuel</p>
                        <p class="text-[16px] font-bold {{ $balanceClass }} tabular-nums">
                            {{ number_format($account->current_balance, 0, ',', ' ') }}
                            <span class="text-base font-normal text-gray-400">FCFA</span>
                        </p>
                    </div>

                    {{-- Last transaction --}}
                    <p class="text-xs text-gray-400">
                        @if($lastTx)
                            Dernière transaction : {{ $lastTx->transaction_date?->format('d/m/Y') }}
                        @else
                            Aucune transaction
                        @endif
                    </p>

                    {{-- Action --}}
                    <a href="{{ route('tresorerie.caisses.show', $account) }}"
                       class="mt-auto w-full text-center border border-gray-200 hover:border-emerald-400 hover:bg-emerald-50 text-gray-600 hover:text-emerald-800 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        Voir les transactions
                    </a>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
