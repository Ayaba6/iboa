@extends('layouts.erp')
@section('title', 'Libération qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Libération qualité</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $tabs = ['a_liberer'=>'À libérer','libere'=>'Libérés','derogation'=>'Dérogations','refuse'=>'Refusés'];
    $num = fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<div class="space-y-4">

    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Libération qualité des lots</h1>
        <p class="text-[12px] text-gray-500">Décision qualité avant mise à disposition ou expédition — libéré, refusé ou sous dérogation.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="grid grid-cols-4 gap-3">
        <div class="bg-white border border-amber-200 rounded-[4px] px-3 py-2"><p class="text-[11px] text-amber-600 uppercase">À libérer</p><p class="text-[18px] font-bold text-amber-700 tabular-nums">{{ $stats['a_liberer'] }}</p></div>
        <div class="bg-white border border-emerald-200 rounded-[4px] px-3 py-2"><p class="text-[11px] text-emerald-600 uppercase">Libérés</p><p class="text-[18px] font-bold text-emerald-700 tabular-nums">{{ $stats['liberes'] }}</p></div>
        <div class="bg-white border border-indigo-200 rounded-[4px] px-3 py-2"><p class="text-[11px] text-indigo-600 uppercase">Dérogations</p><p class="text-[18px] font-bold text-indigo-700 tabular-nums">{{ $stats['derogation'] }}</p></div>
        <div class="bg-white border border-red-200 rounded-[4px] px-3 py-2"><p class="text-[11px] text-red-600 uppercase">Refusés</p><p class="text-[18px] font-bold text-red-700 tabular-nums">{{ $stats['refuses'] }}</p></div>
    </div>

    {{-- Onglets --}}
    <div class="flex gap-1 border-b border-gray-200">
        @foreach($tabs as $k => $label)
        <a href="{{ route('qualite.releases.index', ['state' => $k]) }}"
           class="px-4 py-2 text-[13px] font-semibold rounded-t-[4px] {{ $state === $k ? 'bg-white border border-b-0 border-gray-300 text-emerald-800' : 'text-gray-500 hover:text-gray-800' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Lot</th>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-left">OF</th>
                        <th class="{{ $th }} text-right">Quantité</th>
                        <th class="{{ $th }} text-center">Statut lot</th>
                        <th class="{{ $th }} text-center">Libération</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $b)
                    @php $rel = $b->qualityRelease; @endphp
                    <tr class="border-b border-gray-100 align-top odd:bg-white even:bg-gray-50/40">
                        <td class="px-3 py-2 font-mono text-emerald-800 whitespace-nowrap">{{ $b->batch_number ?? '#'.$b->id }}</td>
                        <td class="px-3 py-2">{{ $b->product?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $b->productionOrder?->reference ?? $b->productionOrder?->code ?? ('#'.$b->production_order_id) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $num($b->quantity) }}</td>
                        <td class="px-3 py-2 text-center">
                            @php $bc = match($b->status){ 'conforme'=>'bg-emerald-100 text-emerald-700','cloture'=>'bg-gray-200 text-gray-600',default=>'bg-blue-100 text-blue-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $bc }}">{{ $b->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($rel)
                                @php $rc = match($rel->status){ 'libere'=>'text-emerald-700','derogation'=>'text-indigo-700','refuse'=>'text-red-600',default=>'text-amber-600' }; @endphp
                                <span class="{{ $rc }} text-[11px] font-semibold">{{ $rel->statusLabel() }}</span>
                                @if($rel->derogation_reference)<span class="block text-[10px] text-gray-400">Dérog. {{ $rel->derogation_reference }}</span>@endif
                                @if($rel->decided_at)<span class="block text-[10px] text-gray-400">{{ $rel->decided_at->format('d/m/Y') }}</span>@endif
                            @else
                                <span class="text-gray-400 text-[11px]">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @can('quality.manage')
                            @if(! $rel || ! $rel->isReleased())
                            <div x-data="{ open:false, decision:'libere' }" class="inline-block text-left">
                                <button type="button" @click="open=!open" class="text-emerald-700 hover:underline text-[12px] font-semibold">Décider ▾</button>
                                <div x-show="open" x-cloak @click.outside="open=false" class="absolute z-10 mt-1 w-64 bg-white border border-gray-300 rounded-[4px] shadow-lg p-3 text-left">
                                    <form method="POST" action="{{ route('qualite.releases.decide', $b) }}" class="space-y-2">@csrf
                                        <select name="decision" x-model="decision" class="w-full h-8 py-0 px-2 border border-gray-300 rounded-[3px] text-[12px]">
                                            <option value="libere">Libérer</option>
                                            <option value="derogation">Libérer sous dérogation</option>
                                            <option value="refuse">Refuser (quarantaine)</option>
                                        </select>
                                        <input type="text" name="derogation_reference" x-show="decision==='derogation'" placeholder="Réf. dérogation *" class="w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[12px]">
                                        <textarea name="decision_comment" rows="2" placeholder="Commentaire" class="w-full px-2 py-1 border border-gray-300 rounded-[3px] text-[12px]"></textarea>
                                        <button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold py-1.5 rounded-[3px]">Valider la décision</button>
                                    </form>
                                </div>
                            </div>
                            @else
                            <span class="text-emerald-700 text-[11px]">✓ {{ $rel->decidedBy?->name }}</span>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun lot dans cet état.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $items->total() }} lot(s)</span>
            @if($items->hasPages())<div>{{ $items->links() }}</div>@endif
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Libération qualité</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
