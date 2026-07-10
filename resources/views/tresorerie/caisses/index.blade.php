@extends('layouts.erp')
@section('title', 'Comptes de trésorerie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comptes</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Comptes de trésorerie</h1>
            <p class="text-sm text-gray-500 mt-0.5">Caisses, banques et mobile money — soldes temps réel</p>
        </div>
        <div class="flex items-center gap-1.5">
            @can('cash_accounts.manage')
            <a href="{{ route('tresorerie.caisses.create') }}"
               class="h-8 inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                + Nouveau compte
            </a>
            @endcan
            <a href="{{ route('tresorerie.encaissements.index') }}"
               class="h-8 inline-flex items-center gap-1.5 border border-emerald-500 text-emerald-700 bg-white hover:bg-emerald-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                Encaissements
            </a>
            <a href="{{ route('tresorerie.decaissements.index') }}"
               class="h-8 inline-flex items-center gap-1.5 border border-red-300 text-red-700 bg-white hover:bg-red-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                Décaissements
            </a>
        </div>
    </div>

    {{-- KPIs synthèse --}}
    @php
        $byType = $accounts->groupBy('type');
        $kpis = [
            ['label' => 'Total trésorerie', 'value' => number_format($accounts->sum('current_balance'), 0, ',', ' ') . ' F', 'sub' => $accounts->count() . ' compte(s) actif(s)', 'text' => 'text-emerald-800', 'bd' => 'border-emerald-300'],
            ['label' => 'Banques',          'value' => number_format($byType->get('banque', collect())->sum('current_balance'), 0, ',', ' ') . ' F', 'sub' => $byType->get('banque', collect())->count() . ' compte(s)', 'text' => 'text-blue-700', 'bd' => 'border-blue-200'],
            ['label' => 'Caisses',          'value' => number_format($byType->get('caisse', collect())->sum('current_balance'), 0, ',', ' ') . ' F', 'sub' => $byType->get('caisse', collect())->count() . ' caisse(s)', 'text' => 'text-gray-900', 'bd' => 'border-gray-300'],
            ['label' => 'Mobile Money',     'value' => number_format($byType->get('mobile_money', collect())->sum('current_balance'), 0, ',', ' ') . ' F', 'sub' => $byType->get('mobile_money', collect())->count() . ' compte(s)', 'text' => 'text-violet-700', 'bd' => 'border-violet-200'],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-1.5">
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-[4px] border {{ $kpi['bd'] }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
            <p class="mt-0.5 text-[17px] font-bold {{ $kpi['text'] }} tabular-nums leading-none">{{ $kpi['value'] }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">{{ $kpi['sub'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Table dense --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full text-[14px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">Code</th>
                    <th class="px-3 py-1.5 text-left">Compte</th>
                    <th class="px-3 py-1.5 text-left">Type</th>
                    <th class="px-3 py-1.5 text-left">Établissement</th>
                    <th class="px-3 py-1.5 text-left">N° compte</th>
                    <th class="px-3 py-1.5 text-left">Dernière transaction</th>
                    <th class="px-3 py-1.5 text-right">Solde (FCFA)</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($accounts as $account)
                @php
                    [$badge, $balanceClass] = match($account->type) {
                        'banque'       => ['bg-blue-100 text-blue-700',     'text-blue-900'],
                        'mobile_money' => ['bg-violet-100 text-violet-700', 'text-violet-900'],
                        default        => ['bg-slate-100 text-slate-700',   'text-gray-900'],
                    };
                @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1">
                        <a href="{{ route('tresorerie.caisses.show', $account) }}" class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[13px]">{{ $account->code }}</a>
                    </td>
                    <td class="px-3 py-1 font-medium text-gray-900">
                        {{ $account->name }}
                        @if($account->is_default)<span class="ml-1 inline-flex px-1.5 py-0.5 rounded-[3px] text-[10px] font-bold bg-emerald-100 text-emerald-700">Défaut</span>@endif
                    </td>
                    <td class="px-3 py-1">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $badge }}">{{ $account->typeBadge() }}</span>
                    </td>
                    <td class="px-3 py-1 text-gray-600">{{ $account->bank_name ?: '—' }}</td>
                    <td class="px-3 py-1 font-mono text-[12px] text-gray-500">{{ $account->account_number ?: '—' }}</td>
                    <td class="px-3 py-1 text-gray-500 tabular-nums">
                        {{ $account->last_transaction_date ? \Carbon\Carbon::parse($account->last_transaction_date)->format('d/m/Y') : '—' }}
                    </td>
                    <td class="px-3 py-1 text-right font-bold tabular-nums {{ $balanceClass }}">
                        {{ number_format($account->current_balance, 0, ',', ' ') }}
                        @if($account->min_balance && $account->current_balance < $account->min_balance)
                        <span class="block text-[10px] font-semibold text-red-600">sous seuil ({{ number_format($account->min_balance, 0, ',', ' ') }})</span>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-right whitespace-nowrap">
                        <a href="{{ route('tresorerie.caisses.show', $account) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Détail →</a>
                        @can('cash_accounts.manage')
                        <a href="{{ route('tresorerie.caisses.edit', $account) }}" class="ml-2 text-gray-400 hover:text-gray-700 text-xs font-medium">Modifier</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-gray-400 text-[13px]">
                        Aucun compte de trésorerie configuré.
                        @can('cash_accounts.manage')
                        <a href="{{ route('tresorerie.caisses.create') }}" class="text-emerald-700 hover:underline ml-1">Créer le premier</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($accounts->isNotEmpty())
            <tfoot>
                <tr class="text-white font-bold" style="background:#065f46">
                    <td colspan="6" class="px-3 py-1.5 text-right text-[11px] uppercase">Total trésorerie</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ number_format($accounts->sum('current_balance'), 0, ',', ' ') }} F</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>

</div>
@endsection
