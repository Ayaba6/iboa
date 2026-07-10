@extends('layouts.erp')
@section('title', 'Bobine '.$coil->reference)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.coils.index') }}" class="hover:text-gray-700">Bobines</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $coil->reference }}</span>
@endsection

@section('content')
@php
    $rate = $coil->initial_weight > 0 ? ($coil->remaining_weight / $coil->initial_weight) * 100 : 0;
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $secH = 'px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white';
    [$statutLabel, $statutClass] = match($coil->status) {
        'disponible'    => ['Disponible',    'bg-emerald-100 text-emerald-700'],
        'en_production' => ['En production', 'bg-blue-100 text-blue-700'],
        'epuisee'       => ['Épuisée',       'bg-gray-100 text-gray-500'],
        default         => [str_replace('_',' ',ucfirst($coil->status)), 'bg-gray-100 text-gray-500'],
    };
@endphp
<div class="max-w-4xl mx-auto space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-white rounded-[4px] border border-gray-300 flex items-center justify-between px-3 py-1.5 bg-gradient-to-b from-gray-50 to-white">
        <div class="flex items-center gap-3">
            <h1 class="text-[15px] font-bold text-gray-900 font-mono">{{ $coil->reference }}</h1>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statutClass }}">{{ $statutLabel }}</span>
            <span class="text-[12px] text-gray-500">{{ $coil->color ?? 'Sans couleur' }} · Lot <span class="font-mono">{{ $coil->lot_number ?? '—' }}</span></span>
        </div>
        <div class="flex items-center gap-2">
            @can('production.update')
            <a href="{{ route('production.coils.edit', $coil) }}" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Modifier</a>
            @endcan
            <a href="{{ route('production.coils.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Retour</a>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Poids restant</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums mt-0.5">{{ number_format($coil->remaining_weight,0,',',' ') }} kg</p>
            <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full {{ $rate < 20 ? 'bg-red-400' : 'bg-emerald-500' }}" style="width: {{ min(100,$rate) }}%"></div>
            </div>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Poids initial</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums mt-0.5">{{ number_format($coil->initial_weight,0,',',' ') }} kg</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Coût / kg</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums mt-0.5">{{ number_format($coil->cost_per_kg,2,',',' ') }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Consommé</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums mt-0.5">{{ number_format(max(0, $coil->initial_weight - $coil->remaining_weight),0,',',' ') }} kg</p>
        </div>
    </div>

    {{-- Caractéristiques --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">Caractéristiques</div>
        <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 text-[13px] p-4">
            <div><dt class="text-[11px] font-bold text-gray-500">Article matière</dt><dd class="text-gray-900">{{ $coil->product?->name ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-bold text-gray-500">Fournisseur</dt><dd class="text-gray-900">{{ $coil->supplier?->name ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-bold text-gray-500">Épaisseur</dt><dd class="text-gray-900 tabular-nums">{{ $coil->thickness ?? '—' }} mm</dd></div>
            <div><dt class="text-[11px] font-bold text-gray-500">Largeur</dt><dd class="text-gray-900 tabular-nums">{{ $coil->width ?? '—' }} mm</dd></div>
            <div><dt class="text-[11px] font-bold text-gray-500">Longueur estimée</dt><dd class="text-gray-900 tabular-nums">{{ number_format($coil->estimated_length,0,',',' ') }} m</dd></div>
            <div><dt class="text-[11px] font-bold text-gray-500">Réception</dt><dd class="text-gray-900">{{ optional($coil->received_at)->format('d/m/Y') ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Consommations --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}"><h2 class="text-[13px] font-bold text-gray-900">Consommations ({{ $coil->consumptions->count() }})</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Date</th>
                        <th class="{{ $th }} text-left">Ordre fab.</th>
                        <th class="{{ $th }} text-right">Poids consommé</th>
                        <th class="{{ $th }} text-right">Longueur</th>
                        <th class="{{ $th }} text-right">Coût</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coil->consumptions as $cons)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ optional($cons->consumed_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">
                            @if($cons->productionOrder)
                            <a href="{{ route('production.orders.show', $cons->productionOrder) }}" class="hover:underline">{{ $cons->productionOrder->number }}</a>
                            @else — @endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($cons->weight_consumed,2,',',' ') }} kg</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($cons->length_consumed,2,',',' ') }} m</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-900">{{ number_format($cons->cost,0,',',' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Aucune consommation enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            {{ $coil->consumptions->count() }} consommation(s) — {{ number_format((float) $coil->consumptions->sum('weight_consumed'), 2, ',', ' ') }} kg — {{ number_format((float) $coil->consumptions->sum('cost'), 0, ',', ' ') }} F
        </div>
    </div>
</div>
@endsection
