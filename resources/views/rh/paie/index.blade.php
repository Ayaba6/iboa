@extends('layouts.erp')
@section('title', 'Bulletins de paie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">RH – Paie</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
{{-- KPI summary bar --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">Masse salariale brute</p>
        <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_brut']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">Total net à payer</p>
        <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $fmt($summary['total_net']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">CNSS salarié</p>
        <p class="text-[16px] font-bold text-red-600 tabular-nums">{{ $fmt($summary['total_cnss_employee']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">IUTS total</p>
        <p class="text-[16px] font-bold text-orange-600 tabular-nums">{{ $fmt($summary['total_iuts']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Bulletins de Paie</h1>
        <p class="text-sm text-gray-500 mt-1">Gestion mensuelle de la paie CNSS + IUTS</p>
    </div>
    <div class="flex gap-2">
        {{-- Livre de paie : annuel ou mensuel --}}
        <div x-data="{ open: false, mode: 'annual' }" class="relative">
            <button @click="open = !open"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Livre de paie
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="open" @click.away="open = false" x-cloak
                 class="absolute right-0 mt-1 w-72 bg-white border border-gray-300 rounded-[4px] shadow-lg z-20 overflow-hidden">

                {{-- Onglets Annuel / Mensuel --}}
                <div class="flex border-b border-gray-100">
                    <button @click="mode = 'annual'"
                            :class="mode === 'annual' ? 'border-b-2 border-blue-600 text-blue-700 font-semibold' : 'text-gray-500'"
                            class="flex-1 px-3 py-2.5 text-xs hover:bg-gray-50">
                        📅 Annuel
                    </button>
                    <button @click="mode = 'monthly'"
                            :class="mode === 'monthly' ? 'border-b-2 border-blue-600 text-blue-700 font-semibold' : 'text-gray-500'"
                            class="flex-1 px-3 py-2.5 text-xs hover:bg-gray-50">
                        📆 Mensuel
                    </button>
                </div>

                {{-- Annuel --}}
                <div x-show="mode === 'annual'" class="py-1">
                    <p class="px-4 py-1.5 text-xs text-gray-400 uppercase tracking-wide">Choisir l'année</p>
                    @foreach($years->isEmpty() ? [now()->year] : $years as $y)
                    <a href="{{ route('rh.paie.livre-paie') }}?year={{ $y }}"
                       class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Exercice {{ $y }} — PDF complet
                    </a>
                    @endforeach
                </div>

                {{-- Mensuel --}}
                <div x-show="mode === 'monthly'" class="p-3">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Choisir le mois</p>
                    <form method="GET" data-autosubmit action="{{ route('rh.paie.livre-paie') }}" class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Année</label>
                                <select name="year" class="w-full text-sm border border-gray-200 rounded-[4px] px-2 py-1.5 focus:ring-1 focus:ring-emerald-500 focus:border-transparent">
                                    @foreach($years->isEmpty() ? [now()->year] : $years as $y)
                                        <option value="{{ $y }}" @selected($y == now()->year)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Mois</label>
                                <select name="month" class="w-full text-sm border border-gray-200 rounded-[4px] px-2 py-1.5 focus:ring-1 focus:ring-emerald-500 focus:border-transparent">
                                    @foreach([1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'] as $m => $label)
                                        <option value="{{ $m }}" @selected($m == now()->month)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-emerald-700 text-white text-sm rounded-[4px] hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Télécharger PDF mensuel
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <a href="{{ route('rh.paie.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau bulletin
        </a>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4 mb-5 flex gap-3">
    <select name="year" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Toutes les années</option>
        @foreach($years as $y)
            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
        @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les statuts</option>
        @foreach(['brouillon'=>'Brouillon','calcule'=>'Calculé','valide'=>'Validé','paye'=>'Payé'] as $v=>$l)
            <option value="{{ $v }}" @selected(($filters['status'] ?? '')===$v)>{{ $l }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm">Filtrer</button>
    <a href="{{ route('rh.paie.index') }}" class="px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm">✕</a>
</form>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="tbl-scroll">
        <table class="tbl tbl-sticky">
            <thead>
                <tr>
                    <th class="text-left">Période</th>
                    <th class="text-center">Effectif</th>
                    <th class="text-right">Total brut</th>
                    <th class="text-right">CNSS (emp.)</th>
                    <th class="text-right">CNSS (pat.)</th>
                    <th class="text-right">IUTS</th>
                    <th class="text-right">Total net</th>
                    <th class="text-center">Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($runs as $run)
            <tr>
                <td class="font-medium">{{ $run->period_label }}</td>
                <td class="text-center text-gray-600">{{ $run->employee_count ?: '—' }}</td>
                <td class="text-right font-mono">{{ $run->total_brut ? number_format($run->total_brut, 0, ',', ' ') : '—' }}</td>
                <td class="text-right font-mono text-red-600">{{ $run->total_cnss_employee ? number_format($run->total_cnss_employee, 0, ',', ' ') : '—' }}</td>
                <td class="text-right font-mono text-orange-600">{{ $run->total_cnss_employer ? number_format($run->total_cnss_employer, 0, ',', ' ') : '—' }}</td>
                <td class="text-right font-mono text-red-600">{{ $run->total_iuts ? number_format($run->total_iuts, 0, ',', ' ') : '—' }}</td>
                <td class="text-right font-mono font-semibold text-emerald-700">{{ $run->total_net ? number_format($run->total_net, 0, ',', ' ') : '—' }}</td>
                <td class="text-center">
                    @php $c = $run->status_color @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                        {{ $run->status_label }}
                    </span>
                </td>
                <td class="text-right">
                    <a href="{{ route('rh.paie.show', $run) }}" class="text-blue-600 hover:underline text-xs">Voir</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="px-4 py-12 text-center text-gray-400">Aucun bulletin de paie. Créez-en un pour commencer.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($runs->hasPages())
    <div class="px-3 py-1.5 border-t border-gray-100">{{ $runs->links() }}</div>
    @endif
</div>
@endsection
