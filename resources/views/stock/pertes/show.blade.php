@extends('layouts.erp')
@section('title', 'Perte — '.($loss->reference ?? '#'.$loss->id))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.pertes.index') }}" class="hover:text-gray-700">Pertes & casses</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $loss->reference ?? '#'.$loss->id }}</span>
@endsection

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F';
    $qte = rtrim(rtrim(number_format((float) $loss->quantity, 3, ',', ' '), '0'), ',');
    $sc = ['declaree'=>'text-amber-600','validee'=>'text-emerald-700','rejetee'=>'text-red-600'][$loss->status] ?? 'text-gray-500';
@endphp
<div class="max-w-2xl space-y-4">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $loss->typeLabel() }} — {{ $loss->product?->name }}</h1>
            <p class="text-sm text-gray-500">{{ $loss->reference ?? '#'.$loss->id }} · Dépôt {{ $loss->warehouse?->code ?? $loss->warehouse?->name }} · <span class="{{ $sc }} font-semibold">{{ $loss->statusLabel() }}</span></p>
        </div>
        <div class="text-right">
            <p class="text-[24px] font-bold text-red-700 tabular-nums leading-none">{{ $fmt($loss->estimated_value) }}</p>
            <p class="text-xs text-gray-500">{{ $qte }} × {{ $fmt($loss->unit_cost) }} (PMP)</p>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($loss->status === 'rejetee' && $loss->reject_reason)<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2">Rejetée : {{ $loss->reject_reason }}</div>@endif

    <div class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-2 gap-3 text-[13px]">
        <div><span class="text-gray-400">Article :</span> <span class="font-medium">{{ $loss->product?->name }}</span></div>
        <div><span class="text-gray-400">Quantité :</span> <span class="font-medium tabular-nums">{{ $qte }}</span></div>
        <div><span class="text-gray-400">N° lot :</span> {{ $loss->lot_number ?? '—' }}</div>
        <div><span class="text-gray-400">Responsable :</span> {{ $loss->responsible?->full_name ?? '—' }}</div>
        <div class="col-span-2"><span class="text-gray-400">Cause :</span> {{ $loss->cause ?? '—' }}</div>
        @if($loss->photo_path)<div class="col-span-2"><a href="{{ route('stocks.pertes.photo', $loss) }}" target="_blank" class="text-blue-700 hover:underline">📎 Voir la photo / justificatif</a></div>@endif
        @if($loss->notes)<div class="col-span-2"><span class="text-gray-400">Notes :</span> {{ $loss->notes }}</div>@endif
    </div>

    @if($loss->status === 'declaree')
    <div class="flex items-center gap-2 flex-wrap">
        @can('stocks.adjust')
        <form method="POST" action="{{ route('stocks.pertes.validate', $loss) }}" onsubmit="return confirm('Valider la perte ? Le stock sera sorti au PMP.');">@csrf
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">Valider (sortie stock)</button>
        </form>
        <form method="POST" action="{{ route('stocks.pertes.reject', $loss) }}" class="flex items-center gap-1">@csrf
            <input name="reject_reason" placeholder="Motif du rejet" class="h-9 px-2 border border-gray-300 rounded-[4px] text-[13px]">
            <button class="border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold px-4 py-2 rounded-[4px]">Rejeter</button>
        </form>
        @endcan
    </div>
    @elseif($loss->status === 'validee')
    <p class="text-emerald-700 text-sm font-semibold">✓ Validée le {{ optional($loss->validated_at)->format('d/m/Y') }} — stock sorti au PMP.</p>
    @endif

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Valeur : <span class="text-white font-semibold">{{ $fmt($loss->estimated_value) }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
