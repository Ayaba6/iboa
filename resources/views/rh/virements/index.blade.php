@extends('layouts.erp')
@section('title', 'Virements de paie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">Paie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.paie.show', $run) }}" class="hover:text-gray-700">{{ $run->period_label ?? ('Run #'.$run->id) }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Virements</span>
@endsection

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ') . ' F'; @endphp

<div class="w-full space-y-3">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Virements de paie — {{ $run->period_label ?? ('Run #'.$run->id) }}</h1>
            <p class="text-sm text-gray-500">Net à payer par salarié · mode de règlement · rapprochement.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @can('rh.payroll.manage')
            @if($run->payments->isEmpty())
            <form method="POST" action="{{ route('rh.paie.virements.generate', $run) }}">@csrf
                <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px]">Générer les virements</button>
            </form>
            @endif
            @endcan
            @if($run->payments->isNotEmpty())
            <a href="{{ route('rh.paie.virements.bank-file', $run) }}" class="border border-orange-600 text-orange-700 text-[13px] font-semibold px-4 py-1.5 rounded-[4px] hover:bg-orange-50">Fichier bancaire</a>
            @endif
        </div>
    </div>

    @if($run->payments->isNotEmpty())
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Total net</p><p class="text-2xl font-bold leading-none tabular-nums text-gray-900 mt-1">{{ $fmt($summary['total']) }}</p></div>
        <div class="bg-white border border-emerald-200 rounded-[4px] p-4"><p class="text-xs text-emerald-600 uppercase">Payé</p><p class="text-2xl font-bold leading-none tabular-nums text-emerald-700 mt-1">{{ $fmt($summary['paid']) }}</p><p class="text-[11px] text-gray-400 mt-1">{{ $summary['count_paid'] }} / {{ $summary['count'] }} salariés</p></div>
        <div class="bg-white border border-amber-200 rounded-[4px] p-4"><p class="text-xs text-amber-600 uppercase">En attente</p><p class="text-2xl font-bold leading-none tabular-nums text-amber-700 mt-1">{{ $fmt($summary['pending']) }}</p></div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4 flex items-center">
            @can('rh.payroll.manage')
            @if($summary['count_paid'] < $summary['count'])
            <form method="POST" action="{{ route('rh.paie.virements.pay-all', $run) }}" class="w-full" onsubmit="return confirm('Marquer tous les virements en attente comme payés ?');">@csrf
                <input type="text" name="reference" placeholder="Réf. virement groupé" class="w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[12px] mb-1">
                <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[3px]">Marquer tout payé</button>
            </form>
            @else<p class="text-emerald-700 text-sm font-semibold">✓ Tous payés</p>@endif
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Matricule</th>
                    <th class="px-3 py-2 text-left">Salarié</th>
                    <th class="px-3 py-2 text-left">Mode</th>
                    <th class="px-3 py-2 text-left">Banque</th>
                    <th class="px-3 py-2 text-left">Compte</th>
                    <th class="px-3 py-2 text-right">Net à payer</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                    <th class="px-3 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($run->payments->sortBy('employee_name') as $p)
                <tr class="{{ $p->status === 'paye' ? 'bg-emerald-50/30' : '' }}">
                    <td class="px-3 py-1.5 font-mono text-xs">{{ $p->employee_matricule }}</td>
                    <td class="px-3 py-1.5">{{ $p->employee_name }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ \App\Models\PayrollPayment::METHODS[$p->method] ?? $p->method }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $p->bank_name ?? '—' }}</td>
                    <td class="px-3 py-1.5 font-mono text-xs text-gray-600">{{ $p->bank_account ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($p->net_amount) }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($p->status === 'paye')<span class="text-emerald-700 text-xs font-semibold">● Payé{{ $p->paid_at ? ' '.$p->paid_at->format('d/m') : '' }}</span>
                        @elseif($p->status === 'rejete')<span class="text-red-600 text-xs font-semibold">✕ Rejeté</span>
                        @else<span class="text-amber-600 text-xs">○ En attente</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @can('rh.payroll.manage')
                        @if($p->status !== 'paye')
                        <form method="POST" action="{{ route('rh.paie.virements.pay', [$run, $p]) }}" class="inline">@csrf
                            <button class="text-emerald-700 hover:underline text-xs font-semibold">Marquer payé</button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-200 p-8 text-center text-gray-400">
        Aucun virement généré. @can('rh.payroll.manage')Cliquez sur « Générer les virements » (run validé requis).@endcan
    </div>
    @endif

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Run : <span class="text-white font-semibold">{{ $run->period_label ?? ('#'.$run->id) }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
