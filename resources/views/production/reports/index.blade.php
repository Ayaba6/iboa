@extends('layouts.erp')
@section('title', 'Rapports production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapports</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Rapports de production</h1>
            <p class="text-[12px] text-gray-500">{{ $report['title'] }} — du {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('production.reports', array_merge(request()->only('type','from','to'), ['export'=>'excel'])) }}"
               class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('production.reports', array_merge(request()->only('type','from','to'), ['export'=>'pdf'])) }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center transition-colors">PDF</a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 items-end">
            <div class="col-span-2">
                <label class="{{ $lbl }}">Type de rapport</label>
                <div class="relative"><select name="type" class="{{ $lk }}" onchange="this.form.submit()">
                    @foreach($types as $k => $lbl2)<option value="{{ $k }}" @selected($type===$k)>{{ $lbl2 }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Période du</label>
                <input type="date" name="from" value="{{ $from }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">au</label>
                <input type="date" name="to" value="{{ $to }}" class="{{ $inp }}">
            </div>
            <div class="flex justify-end">
                <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Générer
                </button>
            </div>
        </div>
    </form>

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">{{ $report['title'] }} ({{ count($report['rows']) }} lignes)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        @foreach($report['headers'] as $i => $h)
                        <th class="{{ $th }} {{ in_array($i, $report['numeric']) ? 'text-right' : 'text-left' }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        @foreach($row as $i => $cell)
                        <td class="px-3 py-1.5 {{ in_array($i, $report['numeric']) ? 'text-right font-mono tabular-nums text-gray-900' : 'text-gray-700' }}">
                            {{ in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell }}
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="{{ count($report['headers']) }}" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune donnée sur la période.</td></tr>
                    @endforelse
                </tbody>
                @if($report['totals'])
                <tfoot>
                    <tr class="font-bold text-white" style="background:#065f46;">
                        @foreach($report['totals'] as $i => $cell)
                        <td class="px-3 py-1.5 {{ in_array($i, $report['numeric']) ? 'text-right font-mono tabular-nums' : 'text-[12px] uppercase tracking-wide' }}">
                            {{ in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell }}
                        </td>
                        @endforeach
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            {{ count($report['rows']) }} ligne(s) — période du {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Rapports production</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
