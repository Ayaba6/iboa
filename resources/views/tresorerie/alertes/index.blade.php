@extends('layouts.erp')
@section('title', 'Alertes trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Alertes</span>
@endsection

@section('content')
@php
    $nbAlertes = $lowBalance->count() + $impayes->count() + $clientsDue->count() + $suppliersDue->count();
@endphp
<div class="space-y-3">

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Alertes trésorerie</h1>
            <p class="text-xs text-gray-400 mt-0.5">Soldes faibles, échéances proches, impayés — {{ $nbAlertes }} alerte(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('tresorerie.dashboard') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                ✕ Fermer
            </a>
        </div>
    </div>

    {{-- ══ 1. Soldes faibles ═════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border {{ $lowBalance->isNotEmpty() ? 'border-red-300' : 'border-gray-300' }} overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 {{ $lowBalance->isNotEmpty() ? 'bg-red-50 border-b border-red-100' : 'bg-[#eef5f0] border-b border-emerald-100' }}">
            <p class="text-[11px] font-bold {{ $lowBalance->isNotEmpty() ? 'text-red-800' : 'text-emerald-900' }} uppercase tracking-wide">1. Soldes sous le seuil</p>
            <p class="text-[11px] {{ $lowBalance->isNotEmpty() ? 'text-red-600 font-bold' : 'text-emerald-600' }}">{{ $lowBalance->count() }} compte(s)</p>
        </div>
        @forelse($lowBalance as $a)
        <div class="px-4 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
            <div><span class="font-medium text-gray-900">{{ $a->name }}</span> <span class="text-xs text-gray-400">{{ ucfirst($a->type) }}</span></div>
            <div class="text-right">
                <span class="font-mono tabular-nums font-semibold text-red-600">{{ number_format($a->current_balance, 0, ',', ' ') }}</span>
                <span class="text-xs text-gray-400"> / seuil {{ number_format($a->min_balance, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>
        @empty
        <div class="px-4 py-6 text-center text-gray-400 text-sm">Aucun compte sous le seuil.</div>
        @endforelse
    </div>

    {{-- ══ 2. Impayés clients ════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border {{ $impayes->isNotEmpty() ? 'border-orange-300' : 'border-gray-300' }} overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 {{ $impayes->isNotEmpty() ? 'bg-amber-50 border-b border-amber-100' : 'bg-[#eef5f0] border-b border-emerald-100' }}">
            <p class="text-[11px] font-bold {{ $impayes->isNotEmpty() ? 'text-amber-800' : 'text-emerald-900' }} uppercase tracking-wide">2. Impayés clients (échéance dépassée)</p>
            <p class="text-[11px] {{ $impayes->isNotEmpty() ? 'text-amber-700 font-bold' : 'text-emerald-600' }}">{{ $impayes->count() }} facture(s)</p>
        </div>
        @forelse($impayes as $i)
        <div class="px-4 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
            <span><span class="font-mono text-emerald-700 font-semibold">{{ $i->number }}</span> · {{ $i->tiers }}</span>
            <span class="text-right"><span class="text-red-600 font-mono tabular-nums font-semibold">{{ number_format($i->remaining_amount, 0, ',', ' ') }}</span> <span class="text-xs text-gray-400">éch. {{ \Carbon\Carbon::parse($i->due_at)->format('d/m/Y') }}</span></span>
        </div>
        @empty
        <div class="px-4 py-6 text-center text-gray-400 text-sm">Aucun impayé.</div>
        @endforelse
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- ══ 3. Échéances clients ══════════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">3. Échéances clients — 7 jours</p>
                <p class="text-[11px] text-emerald-600">{{ $clientsDue->count() }} facture(s)</p>
            </div>
            @forelse($clientsDue as $c)
            <div class="px-4 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
                <span><span class="font-mono text-emerald-700 font-semibold">{{ $c->number }}</span> · {{ $c->tiers }}</span>
                <span class="text-right"><span class="font-mono tabular-nums text-blue-700 font-semibold">{{ number_format($c->remaining_amount, 0, ',', ' ') }}</span> <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($c->due_at)->format('d/m') }}</span></span>
            </div>
            @empty
            <div class="px-4 py-6 text-center text-gray-400 text-sm">Aucune échéance proche.</div>
            @endforelse
        </div>
        {{-- ══ 4. Échéances fournisseurs ═════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">4. Échéances fournisseurs — 7 jours</p>
                <p class="text-[11px] text-emerald-600">{{ $suppliersDue->count() }} facture(s)</p>
            </div>
            @forelse($suppliersDue as $s)
            <div class="px-4 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
                <span><span class="font-mono text-emerald-700 font-semibold">{{ $s->number }}</span> · {{ $s->tiers }}</span>
                <span class="text-right"><span class="font-mono tabular-nums text-red-600 font-semibold">{{ number_format($s->remaining, 0, ',', ' ') }}</span> <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($s->due_at)->format('d/m') }}</span></span>
            </div>
            @empty
            <div class="px-4 py-6 text-center text-gray-400 text-sm">Aucune échéance proche.</div>
            @endforelse
        </div>
    </div>

    {{-- ══ Synthèse (pattern X3 : barre basse) ═══════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-200">
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Soldes faibles</p>
            <p class="text-[15px] font-bold {{ $lowBalance->count() > 0 ? 'text-red-600' : 'text-gray-800' }} mt-0.5">{{ $lowBalance->count() }}</p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Impayés</p>
            <p class="text-[15px] font-bold {{ $impayes->count() > 0 ? 'text-amber-600' : 'text-gray-800' }} mt-0.5">{{ $impayes->count() }}</p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Éch. clients 7 j</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ number_format($clientsDue->sum('remaining_amount'), 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Éch. fournisseurs 7 j</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-red-600 mt-0.5">{{ number_format($suppliersDue->sum('remaining'), 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">Trésorerie — Alertes</strong></span>
            <span>Fenêtre échéances : <strong class="text-white">7 jours</strong></span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
