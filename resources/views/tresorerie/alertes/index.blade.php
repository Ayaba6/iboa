@extends('layouts.erp')
@section('title', 'Alertes trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Alertes</span>
@endsection

@section('content')
<div class="space-y-3">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Alertes trésorerie</h1>
        <p class="text-sm text-gray-500 mt-0.5">Soldes faibles, échéances proches, impayés</p>
    </div>

    {{-- Soldes faibles --}}
    <div class="bg-white rounded-[4px] border {{ $lowBalance->isNotEmpty() ? 'border-red-200' : 'border-gray-200' }} overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full {{ $lowBalance->isNotEmpty() ? 'bg-red-500 animate-pulse' : 'bg-gray-300' }}"></span>
            <h2 class="text-sm font-semibold text-gray-700">Soldes faibles ({{ $lowBalance->count() }})</h2>
        </div>
        @forelse($lowBalance as $a)
        <div class="px-5 py-3 border-b border-gray-50 flex items-center justify-between">
            <div><span class="font-medium text-gray-900">{{ $a->name }}</span> <span class="text-xs text-gray-400">{{ ucfirst($a->type) }}</span></div>
            <div class="text-right">
                <span class="font-mono font-semibold text-red-600">{{ number_format($a->current_balance, 0, ',', ' ') }}</span>
                <span class="text-xs text-gray-400"> / seuil {{ number_format($a->min_balance, 0, ',', ' ') }}</span>
            </div>
        </div>
        @empty
        <div class="px-5 py-6 text-center text-gray-400 text-sm">Aucun compte sous le seuil.</div>
        @endforelse
    </div>

    {{-- Impayés clients --}}
    <div class="bg-white rounded-[4px] border {{ $impayes->isNotEmpty() ? 'border-orange-200' : 'border-gray-200' }} overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full {{ $impayes->isNotEmpty() ? 'bg-orange-500' : 'bg-gray-300' }}"></span>
            <h2 class="text-sm font-semibold text-gray-700">Impayés clients ({{ $impayes->count() }})</h2>
        </div>
        @forelse($impayes as $i)
        <div class="px-5 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
            <span><span class="font-mono text-emerald-700">{{ $i->number }}</span> · {{ $i->tiers }}</span>
            <span class="text-right"><span class="text-red-600 font-mono font-semibold">{{ number_format($i->remaining_amount, 0, ',', ' ') }}</span> <span class="text-xs text-gray-400">éch. {{ \Carbon\Carbon::parse($i->due_at)->format('d/m/Y') }}</span></span>
        </div>
        @empty
        <div class="px-5 py-6 text-center text-gray-400 text-sm">Aucun impayé.</div>
        @endforelse
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Échéances clients --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Échéances clients (7j) — {{ $clientsDue->count() }}</h2></div>
            @forelse($clientsDue as $c)
            <div class="px-5 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
                <span><span class="font-mono text-emerald-700">{{ $c->number }}</span> · {{ $c->tiers }}</span>
                <span class="text-right"><span class="font-mono text-emerald-700">{{ number_format($c->remaining_amount, 0, ',', ' ') }}</span> <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($c->due_at)->format('d/m') }}</span></span>
            </div>
            @empty
            <div class="px-5 py-6 text-center text-gray-400 text-sm">Aucune échéance proche.</div>
            @endforelse
        </div>
        {{-- Échéances fournisseurs --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Échéances fournisseurs (7j) — {{ $suppliersDue->count() }}</h2></div>
            @forelse($suppliersDue as $s)
            <div class="px-5 py-2.5 border-b border-gray-50 flex items-center justify-between text-sm">
                <span><span class="font-mono text-emerald-700">{{ $s->number }}</span> · {{ $s->tiers }}</span>
                <span class="text-right"><span class="font-mono text-red-600">{{ number_format($s->remaining, 0, ',', ' ') }}</span> <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($s->due_at)->format('d/m') }}</span></span>
            </div>
            @empty
            <div class="px-5 py-6 text-center text-gray-400 text-sm">Aucune échéance proche.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
