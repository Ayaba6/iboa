@extends('layouts.erp')
@section('title', 'Budget ' . $budget->name)

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.budgets.index') }}" class="hover:text-gray-700">Budgets</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $budget->name }}</span>
@endsection

@section('content')
@php
    $mois = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];
    $t = $comparison['totals'];
@endphp
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">{{ $budget->name }}</h1>
            <p class="text-sm text-gray-500">Exercice {{ $budget->year }}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $budget->status === 'valide' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ ucfirst($budget->status) }}</span>
    </div>

    {{-- KPIs annuels --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-emerald-600 uppercase">Entrées prévues</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-emerald-700">{{ number_format($t['plan_in'], 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400">réalisé {{ number_format($t['real_in'], 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-red-600 uppercase">Sorties prévues</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-red-700">{{ number_format($t['plan_out'], 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400">réalisé {{ number_format($t['real_out'], 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border-2 border-emerald-200 p-4">
            <p class="text-xs text-emerald-700 uppercase">Solde net prévu</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-emerald-800">{{ number_format($t['plan_in'] - $t['plan_out'], 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border-2 border-gray-200 p-4">
            <p class="text-xs text-gray-600 uppercase">Solde net réalisé</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums {{ ($t['real_in']-$t['real_out']) < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($t['real_in'] - $t['real_out'], 0, ',', ' ') }}</p>
        </div>
    </div>

    {{-- Budget vs Réalisé mensuel --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Budget vs Réalisé — {{ $budget->year }}</h2></div>
        <div class="tbl-scroll">
            <table class="tbl w-full">
                <thead>
                    <tr>
                        <th class="text-left">Mois</th>
                        <th class="text-right">Entrées prév.</th>
                        <th class="text-right">Entrées réel.</th>
                        <th class="text-right">Écart</th>
                        <th class="text-right">Sorties prév.</th>
                        <th class="text-right">Sorties réel.</th>
                        <th class="text-right">Écart</th>
                        <th class="text-right">Net réel.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($comparison['rows'] as $r)
                    @if($r['plan_in'] || $r['plan_out'] || $r['real_in'] || $r['real_out'])
                    <tr>
                        <td class="font-medium">{{ $mois[$r['month']] }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-500">{{ $r['plan_in'] ? number_format($r['plan_in'],0,',',' ') : '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-emerald-700">{{ $r['real_in'] ? number_format($r['real_in'],0,',',' ') : '—' }}</td>
                        <td class="text-right font-mono tabular-nums {{ $r['ecart_in'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $r['ecart_in'] ? ($r['ecart_in']>0?'+':'').number_format($r['ecart_in'],0,',',' ') : '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-500">{{ $r['plan_out'] ? number_format($r['plan_out'],0,',',' ') : '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-red-600">{{ $r['real_out'] ? number_format($r['real_out'],0,',',' ') : '—' }}</td>
                        <td class="text-right font-mono tabular-nums {{ $r['ecart_out'] <= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $r['ecart_out'] ? ($r['ecart_out']>0?'+':'').number_format($r['ecart_out'],0,',',' ') : '—' }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums {{ $r['real_net'] < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($r['real_net'],0,',',' ') }}</td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
                <tfoot class="bg-[#eef5f0] font-bold">
                    <tr>
                        <td class="text-emerald-900 uppercase text-xs">Total</td>
                        <td class="text-right font-mono text-gray-600">{{ number_format($t['plan_in'],0,',',' ') }}</td>
                        <td class="text-right font-mono text-emerald-800">{{ number_format($t['real_in'],0,',',' ') }}</td>
                        <td class="text-right font-mono {{ ($t['real_in']-$t['plan_in'])>=0?'text-emerald-700':'text-red-700' }}">{{ ($t['real_in']-$t['plan_in']>0?'+':'').number_format($t['real_in']-$t['plan_in'],0,',',' ') }}</td>
                        <td class="text-right font-mono text-gray-600">{{ number_format($t['plan_out'],0,',',' ') }}</td>
                        <td class="text-right font-mono text-red-700">{{ number_format($t['real_out'],0,',',' ') }}</td>
                        <td class="text-right font-mono {{ ($t['real_out']-$t['plan_out'])<=0?'text-emerald-700':'text-red-700' }}">{{ ($t['real_out']-$t['plan_out']>0?'+':'').number_format($t['real_out']-$t['plan_out'],0,',',' ') }}</td>
                        <td class="text-right font-mono text-emerald-900">{{ number_format($t['real_in']-$t['real_out'],0,',',' ') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Détail catégories budget --}}
    @if($byCategory->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Détail prévisionnel par catégorie</h2>
        <div class="space-y-1.5">
            @foreach($byCategory as $cat => $lines)
            @php $catTotal = $lines->sum('planned_amount'); $dir = $lines->first()->direction; @endphp
            <div class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {{ $dir === 'entree' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                    {{ $cat }} <span class="text-xs text-gray-400">({{ $dir === 'entree' ? 'entrée' : 'sortie' }})</span>
                </span>
                <span class="font-mono font-medium {{ $dir === 'entree' ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($catTotal, 0, ',', ' ') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <a href="{{ route('tresorerie.budgets.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour
    </a>
</div>
@endsection
