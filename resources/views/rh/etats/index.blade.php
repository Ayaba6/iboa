@extends('layouts.erp')
@section('title', 'États de paie')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>États de paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">États de paie</h1>
        <p class="text-sm text-gray-500 mt-1">Téléchargez tous les documents de paie par bulletin</p>
    </div>
    <a href="{{ route('rh.paie.index') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-200">
        Bulletins de paie
    </a>
</div>

{{-- KPI --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Bulletins ({{ $stats['year'] }})</p>
        <p class="text-[16px] font-bold text-emerald-700 mt-1">{{ $stats['runs_year'] }}</p>
        <p class="text-xs text-gray-400">{{ $stats['runs_total'] }} au total</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Effectif</p>
        <p class="text-[16px] font-bold text-gray-900 mt-1">{{ $stats['employes'] }}</p>
        <p class="text-xs text-gray-400">dernier run</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 col-span-2">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Net versé {{ $stats['year'] }}</p>
        <p class="text-[16px] font-bold text-emerald-600 tabular-nums mt-1">{{ number_format($stats['net_year'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        <p class="text-xs text-gray-400">bulletins validés/payés</p>
    </div>
</div>

@if($runs->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-200 shadow-sm p-16 text-center">
    <div class="text-gray-400 text-lg">Aucun bulletin de paie disponible.</div>
    <a href="{{ route('rh.paie.create') }}" class="mt-4 inline-block text-emerald-700 hover:text-emerald-900 text-sm font-medium">
        Créer le premier bulletin
    </a>
</div>
@else

{{-- Légende documents --}}
<div class="flex flex-wrap gap-3 mb-5 text-xs text-gray-600">
    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-100 border border-red-200"></span> PDF</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-green-100 border border-green-200"></span> Excel</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-blue-100 border border-blue-200"></span> CSV</span>
</div>

<div class="space-y-3">
    @foreach($runs as $run)
    @php
        $periodDate  = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
        $periodLabel = ucfirst($periodDate->translatedFormat('F Y'));
        $isValide    = in_array($run->status, ['valide', 'paye']);
        $statusClass = match($run->status) {
            'brouillon' => 'bg-gray-100 text-gray-600',
            'calcule'   => 'bg-blue-100 text-blue-700',
            'valide'    => 'bg-green-100 text-green-700',
            'paye'      => 'bg-emerald-100 text-emerald-800',
            default     => 'bg-gray-100 text-gray-600',
        };
        $statusLabel = match($run->status) {
            'brouillon' => 'Brouillon',
            'calcule'   => 'Calculé',
            'valide'    => 'Validé',
            'paye'      => 'Payé',
            default     => ucfirst($run->status),
        };
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 hover:shadow-md transition-shadow overflow-hidden">
        <div class="flex flex-wrap items-center gap-4 px-5 py-4">
            {{-- Période + statut --}}
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-[4px] bg-[#eef5f0] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900">{{ $periodLabel }}</div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                        <span class="text-xs text-gray-400">{{ $run->employee_count ?? 0 }} employé(s)</span>
                        @if($run->total_net)
                        <span class="text-xs text-gray-400">· Net total : {{ number_format($run->total_net, 0, ',', ' ') }} F</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Boutons de téléchargement --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- SALAIRES --}}
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-400 mr-1 hidden sm:inline">Salaires :</span>
                    <a href="{{ route('rh.paie.recap-pdf', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-50 text-red-700 border border-red-200 rounded-[4px] text-xs font-medium hover:bg-red-100 transition-colors"
                       title="Livre de paie PDF">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"/></svg>
                        Récap PDF
                    </a>
                    <a href="{{ route('rh.paie.livre-paie-xlsx', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-green-50 text-green-700 border border-green-200 rounded-[4px] text-xs font-medium hover:bg-green-100 transition-colors"
                       title="Livre de paie Excel">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/></svg>
                        XLSX
                    </a>
                </div>

                {{-- CNSS --}}
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-400 mr-1 hidden sm:inline">CNSS :</span>
                    <a href="{{ route('rh.paie.cnss-pdf', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-50 text-red-700 border border-red-200 rounded-[4px] text-xs font-medium hover:bg-red-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
                        PDF
                    </a>
                    <a href="{{ route('rh.paie.cnss-xlsx', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-green-50 text-green-700 border border-green-200 rounded-[4px] text-xs font-medium hover:bg-green-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/></svg>
                        XLSX
                    </a>
                </div>

                {{-- IUTS --}}
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-400 mr-1 hidden sm:inline">IUTS :</span>
                    <a href="{{ route('rh.paie.iuts-pdf', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-50 text-red-700 border border-red-200 rounded-[4px] text-xs font-medium hover:bg-red-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
                        PDF
                    </a>
                    <a href="{{ route('rh.paie.iuts-xlsx', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-green-50 text-green-700 border border-green-200 rounded-[4px] text-xs font-medium hover:bg-green-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/></svg>
                        XLSX
                    </a>
                </div>

                {{-- Avances & Prêts --}}
                <div class="flex items-center gap-1">
                    <a href="{{ route('rh.paie.avances-pdf', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-[4px] text-xs font-medium hover:bg-amber-100 transition-colors"
                       title="État des avances">
                        Avances
                    </a>
                    <a href="{{ route('rh.paie.prets-pdf', $run) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-[4px] text-xs font-medium hover:bg-amber-100 transition-colors"
                       title="État des prêts">
                        Prêts
                    </a>
                </div>

                {{-- Virement --}}
                <a href="{{ route('rh.paie.virement-csv', $run) }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-[4px] text-xs font-medium hover:bg-blue-100 transition-colors"
                   title="Ordre de virement CSV">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Virement CSV
                </a>

                {{-- Lien bulletin --}}
                <a href="{{ route('rh.paie.show', $run) }}"
                   class="ml-1 inline-flex items-center gap-1 px-2.5 py-1.5 text-gray-500 hover:text-emerald-700 text-xs font-medium"
                   title="Ouvrir le bulletin">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Détail
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Pagination --}}
@if($runs->hasPages())
<div class="mt-4">{{ $runs->links() }}</div>
@endif

{{-- Livre de paie annuel --}}
<div class="mt-6 bg-gradient-to-br from-emerald-50 to-white border border-emerald-200 rounded-[4px] shadow-sm px-5 py-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="font-semibold text-emerald-900">Livre de paie annuel</div>
            <div class="text-sm text-emerald-800 mt-0.5">Récapitulatif de tous les bulletins validés de l'année</div>
        </div>
        <div class="flex items-center gap-3">
            <form action="{{ route('rh.paie.livre-paie') }}" method="GET" class="flex items-center gap-2">
                <select name="year" class="border border-emerald-300 bg-white rounded-[4px] px-3 py-1.5 text-sm text-emerald-800">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                    </svg>
                    Télécharger PDF
                </button>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
