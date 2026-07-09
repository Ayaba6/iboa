@extends('layouts.erp')
@section('title', 'Optimisation de découpe')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Optimisation découpe</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $initLines = old('items', $input['items'] ?? [['length'=>'','quantity'=>'']]);
@endphp
<div class="max-w-4xl space-y-4" x-data="{ lines: {{ Js::from(array_values($initLines)) }} }">

    @if(!empty($error))
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">{{ $error }}</div>
    @endif

    {{-- [Maquette] Optimisations enregistrées — en premier --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <div>
                <h2 class="text-[15px] font-bold text-gray-900">Optimisation de découpe</h2>
                <p class="text-[11.5px] text-gray-500">Plans de coupe — minimise les chutes, valorise les réserves</p>
            </div>
            <a href="{{ route('production.cutting.create') }}" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">+ Nouvelle optimisation</a>
        </div>
        @if(isset($optimizations) && $optimizations->isNotEmpty())
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left">Code</th>
                <th class="{{ $th }} text-left">Produit</th>
                <th class="{{ $th }} text-left">Bobine</th>
                <th class="{{ $th }} text-right">Demandes</th>
                <th class="{{ $th }} text-right">Rendement</th>
                <th class="{{ $th }} text-right">Chute (m)</th>
                <th class="{{ $th }} text-left">Statut</th>
                <th class="{{ $th }} text-left">Date</th>
                <th class="{{ $th }}"></th>
            </tr></thead>
            <tbody>
                @foreach($optimizations as $op)
                <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1.5"><a href="{{ route('production.cutting.edit', $op) }}" class="font-mono text-emerald-800 hover:underline">{{ $op->code ?: 'OPT-'.$op->id }}</a></td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $op->product?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 font-mono text-gray-600">{{ $op->coil?->reference ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $op->lines_count }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $op->material_yield ? 'text-emerald-700 font-semibold' : 'text-gray-400' }}">{{ $op->material_yield ? number_format($op->material_yield, 1, ',', ' ').' %' : '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $op->estimated_waste_m ? 'text-orange-600' : 'text-gray-400' }}">{{ $op->estimated_waste_m ? number_format($op->estimated_waste_m, 2, ',', ' ') : '—' }}</td>
                    <td class="px-3 py-1.5">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ match($op->status){ 'optimisee' => 'bg-emerald-100 text-emerald-800', 'validee' => 'bg-blue-100 text-blue-800', default => 'bg-gray-100 text-gray-600' } }}">{{ ucfirst($op->status ?? 'brouillon') }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-gray-500">{{ optional($op->execution_date)->format('d/m/Y') ?? $op->created_at->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        <a href="{{ route('production.cutting.edit', $op) }}" class="text-[12px] text-emerald-700 hover:text-emerald-900 font-semibold">Ouvrir →</a>
                        <form method="POST" action="{{ route('production.cutting.destroy', $op) }}" class="inline ml-2" data-confirm="Supprimer cette optimisation ?">
                            @csrf @method('DELETE')
                            <button class="text-gray-400 hover:text-red-600 text-xs">✕</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">{{ $optimizations->links() }}</div>
        @else
        <div class="px-4 py-6 text-center text-[12.5px] text-gray-400">Aucune optimisation enregistrée — créez la première avec « + Nouvelle optimisation ».</div>
        @endif
    </div>

    <form method="POST" action="{{ route('production.cutting.optimize') }}">
        @csrf
        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <div>
                    <h2 class="text-[15px] font-bold text-gray-900">Calcul rapide</h2>
                    <p class="text-[11.5px] text-gray-500">Plan de coupe 1D (cutting stock) sans sauvegarde</p>
                </div>
                <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Optimiser</button>
            </div>

            <div class="p-4 space-y-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Paramètres</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3">
                        <div>
                            <label class="{{ $lbl }}">Longueur stock <span class="text-red-600">*</span></label>
                            <input type="number" name="stock_length" value="{{ old('stock_length', $input['stock_length'] ?? 6000) }}" step="0.01" min="0.01" required class="{{ $inp }}">
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Trait de scie (kerf)</label>
                            <input type="number" name="kerf" value="{{ old('kerf', $input['kerf'] ?? 0) }}" step="0.01" min="0" class="{{ $inp }}">
                        </div>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="flex items-center justify-between {{ $secH }}">
                        <span>Pièces à couper</span>
                        <button type="button" @click="lines.push({length:'',quantity:''})" class="text-[12px] text-emerald-700 hover:text-emerald-900 font-semibold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Ajouter une ligne
                        </button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-[#eef5f0] border-b border-gray-300">
                                <tr>
                                    <th class="{{ $th }} text-left w-5/12">Longueur</th>
                                    <th class="{{ $th }} text-right w-5/12">Quantité</th>
                                    <th class="{{ $th }} w-2/12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(l, i) in lines" :key="i">
                                    <tr class="border-b border-gray-100">
                                        <td class="py-1.5 pr-2"><input type="number" step="0.01" min="0" :name="`items[${i}][length]`" x-model="l.length" class="{{ $inp }}" placeholder="ex. 2000"></td>
                                        <td class="py-1.5 pr-2"><input type="number" min="0" :name="`items[${i}][quantity]`" x-model="l.quantity" class="{{ $inp }}" placeholder="ex. 5"></td>
                                        <td class="py-1.5 text-right">
                                            <button type="button" @click="lines.splice(i,1)" class="p-1 text-gray-400 hover:text-red-500" :disabled="lines.length===1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </form>

    @if($plan)
    {{-- KPI résultat --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach([
            ['label' => 'Barres / bobines', 'value' => number_format($plan['bars_count'], 0, ',', ' '),          'color' => 'text-gray-900',    'bg' => 'bg-[#eef5f0]'],
            ['label' => 'Utilisé',          'value' => number_format($plan['used'], 0, ',', ' '),                'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
            ['label' => 'Chutes',           'value' => number_format($plan['waste'], 0, ',', ' '),               'color' => 'text-red-600',     'bg' => 'bg-red-50'],
            ['label' => 'Rendement',        'value' => number_format($plan['yield'], 1, ',', ' ') . ' %',        'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
            <p class="text-[16px] font-bold {{ $kpi['color'] }} tabular-nums mt-0.5">{{ $kpi['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Plan de coupe --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Plan de coupe</h2>
        </div>
        <div class="p-4 space-y-3">
            @foreach($plan['bars'] as $idx => $bar)
            <div>
                <div class="flex items-center justify-between text-[11.5px] text-gray-500 mb-1">
                    <span>Barre {{ $idx+1 }} — {{ implode(' + ', array_map(fn($c)=>number_format($c,0,',',' '), $bar['cuts'])) }}</span>
                    <span>Chute : <span class="{{ $bar['waste']>0 ? 'text-red-600 font-semibold' : 'text-emerald-600' }} tabular-nums">{{ number_format($bar['waste'],0,',',' ') }}</span></span>
                </div>
                <div class="flex h-7 rounded-[3px] overflow-hidden border border-gray-300">
                    @foreach($bar['cuts'] as $c)
                    <div class="border-r border-white flex items-center justify-center text-[10px] text-white font-medium" style="width: {{ max(3, $c / $plan['stock_length'] * 100) }}%; background:#059669;">{{ number_format($c,0,',',' ') }}</div>
                    @endforeach
                    @if($bar['waste'] > 0)
                    <div class="bg-red-200 flex items-center justify-center text-[10px] text-red-700" style="width: {{ max(2, $bar['waste'] / $plan['stock_length'] * 100) }}%">chute</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            {{ $plan['bars_count'] }} barre(s) — rendement {{ number_format($plan['yield'],1,',',' ') }} % — chutes {{ number_format($plan['waste'],0,',',' ') }}
        </div>
    </div>
    @endif
</div>
@endsection
