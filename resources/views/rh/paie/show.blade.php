@extends('layouts.erp')
@section('title', 'Paie – ' . $run->period_label)

@section('breadcrumb')
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">Paie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $run->period_label }}</span>
@endsection

@section('content')
<div x-data="payrollShow()" x-init="loadVariables()">

{{-- ── Workflow progress ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 px-5 py-4 mb-5">
    <x-workflow.progress-steps
        :steps="[
            ['key' => 'brouillon', 'label' => 'Brouillon',  'icon' => '✏️'],
            ['key' => 'valide',    'label' => 'Validé',     'icon' => '✅'],
            ['key' => 'paye',      'label' => 'Payé',       'icon' => '💰'],
        ]"
        :current="$run->status"
    />
</div>

{{-- ── En-tête ──────────────────────────────────────────────────────────────── --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Bulletin — {{ $run->period_label }}</h1>
        @php $c = $run->status_color @endphp
        <span class="inline-flex mt-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
            {{ $run->status_label }}
        </span>
        @if($run->validatedBy)
        <span class="ml-2 text-xs text-gray-400">Validé par {{ $run->validatedBy->name }} le {{ $run->validated_at->format('d/m/Y à H:i') }}</span>
        @endif
        @if($run->paid_at)
        <span class="ml-2 text-xs text-emerald-600 font-medium">Payé le {{ $run->paid_at->format('d/m/Y') }}</span>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 justify-end">

        {{-- Écriture comptable --}}
        @if($run->isValidated())
        @if($run->journal_entry_id)
        <a href="{{ route('comptabilite.journaux.show', $run->journal_entry_id) }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-violet-100 text-violet-700 rounded-[4px] text-sm font-medium hover:bg-violet-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Écriture #{{ $run->journal_entry_id }}
        </a>
        @else
        <form method="POST" action="{{ route('rh.paie.journalize', $run) }}">
            @csrf
            <button class="inline-flex items-center gap-2 px-3 py-2 bg-violet-600 text-white rounded-[4px] text-sm font-medium hover:bg-violet-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Comptabiliser
            </button>
        </form>
        @endif
        @endif

        {{-- États de paie --}}
        @if($run->status !== 'brouillon')
        <div x-data="{open:false}" class="relative">
            <button @click="open=!open"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-[4px] text-sm hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                États de paie
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" @click.away="open=false"
                 class="absolute right-0 mt-1 w-64 bg-white border border-gray-300 rounded-[4px] shadow-xl z-20 py-1">

                {{-- [PAI-07] Virements — suivi ligne par ligne --}}
                <a href="{{ route('rh.paie.virements.index', $run) }}"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-emerald-50 font-medium">
                    <span class="text-emerald-600">💳</span> Virements &amp; paiements
                </a>
                {{-- [PAI-08] Déclarations — figeage CNSS/IUTS --}}
                @can('rh.payroll.manage')
                <form method="POST" action="{{ route('rh.paie.declarations.generate', $run) }}">@csrf
                    <button type="submit" class="w-full flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50 font-medium text-left">
                        <span class="text-blue-600">🗂️</span> Figer les déclarations CNSS/IUTS
                    </button>
                </form>
                @endcan
                <a href="{{ route('rh.paie.declarations.index') }}"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50">
                    <span class="text-blue-500">📚</span> Archive des déclarations
                </a>

                {{-- Titre section Salaires --}}
                <div class="px-4 py-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Salaires</div>
                <a href="{{ route('rh.paie.recap-pdf', $run) }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50">
                    <span class="text-blue-500">📄</span> Livre de paie (récap mensuel)
                </a>
                <a href="{{ route('rh.paie.livre-paie-xlsx', $run) }}"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-emerald-50">
                    <span class="text-emerald-600">📊</span> Livre de paie Excel
                </a>

                {{-- CNSS --}}
                <div class="px-4 py-1.5 mt-1 text-xs font-semibold text-gray-400 uppercase tracking-wide border-t border-gray-100">CNSS</div>
                <a href="{{ route('rh.paie.cnss-pdf', $run) }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50">
                    <span class="text-blue-500">📄</span> Bordereau CNSS PDF
                </a>
                <a href="{{ route('rh.paie.cnss-xlsx', $run) }}"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-emerald-50">
                    <span class="text-emerald-600">📊</span> CNSS Excel
                </a>

                {{-- IUTS --}}
                <div class="px-4 py-1.5 mt-1 text-xs font-semibold text-gray-400 uppercase tracking-wide border-t border-gray-100">ITS / IUTS</div>
                <a href="{{ route('rh.paie.iuts-pdf', $run) }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50">
                    <span class="text-blue-500">📄</span> État IUTS PDF
                </a>
                <a href="{{ route('rh.paie.iuts-xlsx', $run) }}"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-emerald-50">
                    <span class="text-emerald-600">📊</span> IUTS Excel
                </a>

                {{-- Avances & Prêts --}}
                <div class="px-4 py-1.5 mt-1 text-xs font-semibold text-gray-400 uppercase tracking-wide border-t border-gray-100">Avances & Prêts</div>
                <a href="{{ route('rh.paie.avances-pdf', $run) }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50">
                    <span class="text-blue-500">📄</span> État avances PDF
                </a>
                <a href="{{ route('rh.paie.prets-pdf', $run) }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50">
                    <span class="text-blue-500">📄</span> État prêts PDF
                </a>

                {{-- Virement --}}
                <div class="px-4 py-1.5 mt-1 text-xs font-semibold text-gray-400 uppercase tracking-wide border-t border-gray-100">Banque</div>
                <a href="{{ route('rh.paie.virement-csv', $run) }}"
                   class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    <span>📋</span> Ordre de virement CSV
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════════
     Workflow de validation — Préparation → Contrôle → Validation → Clôture
     Inspiré Sage Paie : stepper horizontal avec état visuel + action contextuelle
══════════════════════════════════════════════════════════════════════════════ --}}
@php
    $wfStep = match($run->status) {
        'brouillon' => 1,
        'calcule'   => 2,
        'valide'    => 3,
        'paye'      => 4,
        default     => 1,
    };
    $wfSteps = [
        1 => [
            'label'    => 'Préparation',
            'sub'      => 'Saisie des variables & vérification',
            'icon'     => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'color'    => 'blue',
            'date'     => $run->created_at?->format('d/m/Y'),
        ],
        2 => [
            'label'    => 'Contrôle',
            'sub'      => 'Calcul & vérification des montants',
            'icon'     => 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18',
            'color'    => 'indigo',
            'date'     => $run->status === 'calcule' || $wfStep > 2 ? $run->updated_at?->format('d/m/Y') : null,
        ],
        3 => [
            'label'    => 'Validation',
            'sub'      => 'Approbation finale du bulletin',
            'icon'     => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'color'    => 'green',
            'date'     => $run->validated_at?->format('d/m/Y'),
        ],
        4 => [
            'label'    => 'Clôture',
            'sub'      => 'Paiement & archivage de la période',
            'icon'     => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
            'color'    => 'emerald',
            'date'     => $run->paid_at?->format('d/m/Y'),
        ],
    ];
@endphp

<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm mb-6 overflow-hidden">
    {{-- Barre de titre --}}
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50">
        <span class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Workflow de traitement</span>
        <span class="text-xs text-gray-400">{{ $run->period_label }}</span>
    </div>

    {{-- Stepper — classes Tailwind STATIQUES par étape (pas de concat dynamique) --}}
    <div class="px-6 py-5">
        <div class="flex items-start">

            {{-- ── ÉTAPE 1 : Préparation ── --}}
            <div class="flex-1 flex flex-col items-center relative min-w-0">
                {{-- Connecteur droite --}}
                <div class="absolute right-0 top-5 -translate-y-1/2 w-1/2 h-0.5
                            {{ $wfStep > 1 ? 'bg-blue-400' : 'bg-gray-200' }}"></div>
                {{-- Cercle --}}
                <div class="relative z-10 flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-300
                            {{ $wfStep > 1 ? 'bg-blue-500 border-blue-500 text-white'
                               : ($wfStep === 1 ? 'bg-white border-blue-500 text-blue-600 ring-4 ring-blue-100'
                                              : 'bg-white border-gray-200 text-gray-300') }}">
                    @if($wfStep > 1)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    @endif
                </div>
                <div class="mt-2 text-center px-1">
                    <div class="text-xs font-semibold {{ $wfStep >= 1 ? 'text-blue-700' : 'text-gray-400' }}">Préparation</div>
                    @if($wfStep === 1)<div class="text-[10px] text-gray-500 mt-0.5 hidden sm:block">Saisie des variables</div>@endif
                    @if($wfSteps[1]['date'])<div class="text-[10px] text-gray-400 mt-0.5">{{ $wfSteps[1]['date'] }}</div>@endif
                </div>
            </div>

            {{-- ── ÉTAPE 2 : Contrôle ── --}}
            <div class="flex-1 flex flex-col items-center relative min-w-0">
                {{-- Connecteur gauche --}}
                <div class="absolute left-0 top-5 -translate-y-1/2 w-1/2 h-0.5
                            {{ $wfStep >= 2 ? 'bg-blue-400' : 'bg-gray-200' }}"></div>
                {{-- Connecteur droite --}}
                <div class="absolute right-0 top-5 -translate-y-1/2 w-1/2 h-0.5
                            {{ $wfStep > 2 ? 'bg-emerald-500' : 'bg-gray-200' }}"></div>
                {{-- Cercle --}}
                <div class="relative z-10 flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-300
                            {{ $wfStep > 2 ? 'bg-emerald-600 border-emerald-500 text-white'
                               : ($wfStep === 2 ? 'bg-white border-emerald-500 text-emerald-700 ring-4 ring-indigo-100'
                                              : 'bg-white border-gray-200 text-gray-300') }}">
                    @if($wfStep > 2)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif($wfStep === 2)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                    @else
                        <span class="text-sm font-bold text-gray-300">2</span>
                    @endif
                </div>
                <div class="mt-2 text-center px-1">
                    <div class="text-xs font-semibold {{ $wfStep >= 2 ? 'text-emerald-800' : 'text-gray-400' }}">Contrôle</div>
                    @if($wfStep === 2)<div class="text-[10px] text-gray-500 mt-0.5 hidden sm:block">Vérification des montants</div>@endif
                    @if($wfSteps[2]['date'])<div class="text-[10px] text-gray-400 mt-0.5">{{ $wfSteps[2]['date'] }}</div>@endif
                </div>
            </div>

            {{-- ── ÉTAPE 3 : Validation ── --}}
            <div class="flex-1 flex flex-col items-center relative min-w-0">
                {{-- Connecteur gauche --}}
                <div class="absolute left-0 top-5 -translate-y-1/2 w-1/2 h-0.5
                            {{ $wfStep >= 3 ? 'bg-emerald-500' : 'bg-gray-200' }}"></div>
                {{-- Connecteur droite --}}
                <div class="absolute right-0 top-5 -translate-y-1/2 w-1/2 h-0.5
                            {{ $wfStep > 3 ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                {{-- Cercle --}}
                <div class="relative z-10 flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-300
                            {{ $wfStep > 3 ? 'bg-green-500 border-green-500 text-white'
                               : ($wfStep === 3 ? 'bg-white border-green-500 text-green-600 ring-4 ring-green-100'
                                              : 'bg-white border-gray-200 text-gray-300') }}">
                    @if($wfStep > 3)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif($wfStep === 3)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                        <span class="text-sm font-bold text-gray-300">3</span>
                    @endif
                </div>
                <div class="mt-2 text-center px-1">
                    <div class="text-xs font-semibold {{ $wfStep >= 3 ? 'text-green-700' : 'text-gray-400' }}">Validation</div>
                    @if($wfStep === 3)<div class="text-[10px] text-gray-500 mt-0.5 hidden sm:block">Approbation finale</div>@endif
                    @if($wfSteps[3]['date'])<div class="text-[10px] text-gray-400 mt-0.5">{{ $wfSteps[3]['date'] }}</div>@endif
                </div>
            </div>

            {{-- ── ÉTAPE 4 : Clôture ── --}}
            <div class="flex-1 flex flex-col items-center relative min-w-0">
                {{-- Connecteur gauche --}}
                <div class="absolute left-0 top-5 -translate-y-1/2 w-1/2 h-0.5
                            {{ $wfStep > 4 ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                {{-- Cercle --}}
                <div class="relative z-10 flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-300
                            {{ $wfStep > 4 ? 'bg-emerald-500 border-emerald-500 text-white'
                               : ($wfStep === 4 ? 'bg-white border-emerald-500 text-emerald-600 ring-4 ring-emerald-100'
                                              : 'bg-white border-gray-200 text-gray-300') }}">
                    @if($wfStep > 4)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif($wfStep === 4)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    @else
                        <span class="text-sm font-bold text-gray-300">4</span>
                    @endif
                </div>
                <div class="mt-2 text-center px-1">
                    <div class="text-xs font-semibold {{ $wfStep >= 4 ? 'text-emerald-700' : 'text-gray-400' }}">Clôture</div>
                    @if($wfStep === 4)<div class="text-[10px] text-gray-500 mt-0.5 hidden sm:block">Paiement & archivage</div>@endif
                    @if($wfSteps[4]['date'])<div class="text-[10px] text-gray-400 mt-0.5">{{ $wfSteps[4]['date'] }}</div>@endif
                </div>
            </div>

        </div>

        {{-- Action contextuelle du workflow --}}
        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-600">
                @if($wfStep === 1)
                    <span class="font-medium text-blue-700">Étape 1 : Préparation</span>
                    — Saisissez les variables mensuelles, vérifiez les paramètres, puis lancez le calcul.
                @elseif($wfStep === 2)
                    <span class="font-medium text-emerald-800">Étape 2 : Contrôle</span>
                    — Vérifiez les montants calculés ligne par ligne avant validation.
                    @if($run->validatedBy)
                    Calculé le {{ $run->updated_at?->format('d/m/Y') }}.
                    @endif
                @elseif($wfStep === 3)
                    <span class="font-medium text-green-700">Étape 3 : Validation</span>
                    — Bulletin validé le {{ $run->validated_at?->format('d/m/Y') }} par {{ $run->validatedBy?->name ?? '—' }}.
                    Procédez au paiement pour clôturer la période.
                @elseif($wfStep === 4)
                    <span class="font-medium text-emerald-700">✓ Période clôturée</span>
                    — Payé le {{ $run->paid_at?->format('d/m/Y') }}. Toutes les étapes sont complètes.
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($wfStep === 1)
                {{-- Calculer → passe à Contrôle --}}
                <form method="POST" action="{{ route('rh.paie.calculate', $run) }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                        Lancer le calcul →
                    </button>
                </form>
                @elseif($wfStep === 2)
                {{-- Valider → passe à Validation --}}
                <form method="POST" action="{{ route('rh.paie.validate', $run) }}"
                      onsubmit="return confirm('Valider définitivement ce bulletin ? Cette action est irréversible.')">
                    @csrf
                    <button class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Valider le bulletin →
                    </button>
                </form>
                {{-- Recalculer si besoin --}}
                <form method="POST" action="{{ route('rh.paie.calculate', $run) }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-600 rounded-[4px] text-sm hover:bg-gray-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Recalculer
                    </button>
                </form>
                @elseif($wfStep === 3)
                {{-- Payer → passe à Clôture --}}
                <button @click="$refs.modalPaid.classList.remove('hidden')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Marquer comme payé →
                </button>
                @elseif($wfStep === 4)
                <span class="inline-flex items-center gap-1.5 text-sm text-emerald-600 font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Période clôturée
                </span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── KPIs ─────────────────────────────────────────────────────────────────── --}}
@if($run->total_brut > 0)
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 mb-6">
    @foreach([
        ['Effectif',        $run->employee_count.' emp.',                                          'indigo'],
        ['Total brut',      number_format($run->total_brut,0,',',' ').' F',                        'gray'],
        ['CNSS salarial',   number_format($run->total_cnss_employee,0,',',' ').' F',               'red'],
        ['CNSS patronal',   number_format($run->total_cnss_employer,0,',',' ').' F',               'amber'],
        ['IUTS',            number_format($run->total_iuts,0,',',' ').' F',                        'purple'],
        ['Net à payer',     number_format($run->total_net,0,',',' ').' F',                         'emerald'],
    ] as [$l,$v,$col])
    <div class="bg-white rounded-[4px] border border-gray-300 p-3 text-center">
        <div class="text-xs text-gray-500">{{ $l }}</div>
        <div class="font-mono font-bold text-{{ $col }}-700 text-sm mt-1">{{ $v }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Variables mensuelles (saisie) ──────────────────────────────────────── --}}
@if($run->isEditable())
<div class="bg-amber-50 border border-amber-200 rounded-[4px] p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-amber-800">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Variables mensuelles — HS / Absences / Primes ponctuelles / Avances
        </h2>
        <button @click="showVarForm=!showVarForm"
                class="px-3 py-1.5 bg-amber-600 text-white rounded-[4px] text-xs font-medium hover:bg-amber-700">
            + Ajouter une variable
        </button>
    </div>

    {{-- Formulaire d'ajout --}}
    <div x-show="showVarForm" x-cloak class="bg-white rounded-[4px] border border-amber-200 p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Employé *</label>
                <select x-model="newVar.employee_id" class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-sm">
                    <option value="">— Choisir —</option>
                    @foreach($run->items->sortBy('employee_name') as $item)
                    <option value="{{ $item->employee_id }}">{{ $item->employee_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                <select x-model="newVar.type" @change="onTypeChange()" class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-sm">
                    @foreach(\App\Models\PayrollVariable::TYPES as $key=>$meta)
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Libellé</label>
                <input type="text" x-model="newVar.label" class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Unité</label>
                <select x-model="newVar.unit" class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-sm">
                    <option value="heures">Heures</option>
                    <option value="jours">Jours</option>
                    <option value="forfait">Forfait</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Quantité</label>
                <input type="number" x-model="newVar.qty" min="0" step="0.5"
                       class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Montant (FCFA) *</label>
                <input type="number" x-model="newVar.amount" min="0" step="100"
                       class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div class="flex items-end gap-3">
                <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                    <input type="checkbox" x-model="newVar.is_taxable" class="rounded"> Imposable
                </label>
                <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                    <input type="checkbox" x-model="newVar.is_social_charged" class="rounded"> CNSS
                </label>
            </div>
            <div class="flex items-end justify-end gap-2">
                <button @click="saveVariable()" :disabled="saving"
                        class="px-4 py-1.5 bg-amber-600 text-white rounded-[4px] text-sm font-medium hover:bg-amber-700 disabled:opacity-50">
                    <span x-text="saving ? 'Enregistrement…' : 'Ajouter'"></span>
                </button>
                <button @click="showVarForm=false" class="px-3 py-1.5 border border-gray-300 text-gray-600 rounded-[4px] text-sm">Annuler</button>
            </div>
        </div>
    </div>

    {{-- Liste des variables --}}
    <div x-show="variables.length > 0">
        <table class="w-full text-xs bg-white rounded-[4px] overflow-hidden">
            <thead class="bg-gray-100 text-gray-500 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Employé</th>
                    <th class="px-3 py-2 text-left">Libellé</th>
                    <th class="px-3 py-2 text-center">Unité</th>
                    <th class="px-3 py-2 text-right">Qté</th>
                    <th class="px-3 py-2 text-right">Montant</th>
                    <th class="px-3 py-2 text-center">Gain/Ret.</th>
                    <th class="px-3 py-2 text-center">Imp.</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="v in variables" :key="v.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium" x-text="v.employee?.full_name || empName(v.employee_id)"></td>
                        <td class="px-3 py-2 text-gray-600" x-text="v.label"></td>
                        <td class="px-3 py-2 text-center text-gray-400" x-text="v.unit"></td>
                        <td class="px-3 py-2 text-right font-mono" x-text="v.qty > 0 ? v.qty : '—'"></td>
                        <td class="px-3 py-2 text-right font-mono font-semibold"
                            :class="v.is_gain ? 'text-green-700' : 'text-red-600'"
                            x-text="(v.is_gain ? '+' : '-') + new Intl.NumberFormat('fr-FR').format(v.amount) + ' F'"></td>
                        <td class="px-3 py-2 text-center" x-text="v.is_gain ? 'Gain' : 'Retenue'"></td>
                        <td class="px-3 py-2 text-center" x-text="v.is_taxable ? '✓' : '—'"></td>
                        <td class="px-3 py-2 text-right">
                            <button @click="deleteVariable(v.id)" class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div x-show="variables.length === 0" class="text-center text-sm text-amber-700/60 py-3">
        Aucune variable saisie. Les HS, absences et avances seront prises en compte au calcul.
    </div>
</div>
@endif

{{-- ── Tableau des bulletins individuels ──────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
    <div class="px-3 py-1.5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Bulletins individuels — {{ $run->employee_count }} employé(s)</h2>
        @if($run->status !== 'brouillon')
        <span class="text-xs text-gray-400">Coût total employeur : <strong class="text-gray-700">{{ number_format($run->total_brut + $run->total_cnss_employer, 0, ',', ' ') }} F</strong></span>
        @endif
    </div>
    <table class="w-full divide-y divide-gray-200 text-xs">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-3 text-left">Mat.</th>
                <th class="px-3 py-3 text-left">Employé / Poste</th>
                <th class="px-3 py-3 text-right">Base</th>
                <th class="px-3 py-3 text-right">HS</th>
                <th class="px-3 py-3 text-right">Primes</th>
                <th class="px-3 py-3 text-right">Absences</th>
                <th class="px-3 py-3 text-right font-semibold">Brut</th>
                <th class="px-3 py-3 text-right">CNSS (e)</th>
                <th class="px-3 py-3 text-right">CNSS (p)</th>
                <th class="px-3 py-3 text-right">IUTS</th>
                <th class="px-3 py-3 text-right">Avances</th>
                <th class="px-3 py-3 text-right font-bold text-emerald-700">Net</th>
                <th class="px-3 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($run->items as $item)
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-2 font-mono text-gray-400">{{ $item->employee_matricule }}</td>
            <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ $item->employee_name }}</div>
                <div class="text-gray-400">{{ $item->department_name }} · {{ $item->job_title }}</div>
                <div class="text-gray-300">{{ $item->worked_days }}/{{ $item->total_days }} j · {{ $item->family_charges !== null ? $item->family_charges . ' charge(s)' : $item->nb_parts . ' part(s)' }}</div>
            </td>
            <td class="px-3 py-2 text-right font-mono">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-blue-600">
                @if($item->hs_25_amount + $item->hs_50_amount + $item->hs_nuit_amount > 0)
                {{ number_format($item->hs_25_amount + $item->hs_50_amount + $item->hs_nuit_amount, 0, ',', ' ') }}
                @else —
                @endif
            </td>
            <td class="px-3 py-2 text-right font-mono text-emerald-700">
                {{ $item->total_allowances_taxable + $item->primes_exceptionnelles + ($item->autres_gains ?? 0) > 0
                    ? number_format($item->total_allowances_taxable + $item->primes_exceptionnelles + ($item->autres_gains ?? 0), 0, ',', ' ')
                    : '—' }}
            </td>
            <td class="px-3 py-2 text-right font-mono text-orange-600">
                @if($item->absence_amount > 0)
                -{{ number_format($item->absence_amount, 0, ',', ' ') }}
                <div class="text-gray-300">{{ $item->absence_days }}j</div>
                @else —
                @endif
            </td>
            <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-red-600">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-amber-600">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-purple-600">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-red-500">
                {{ $item->avances_deductions > 0 ? '-'.number_format($item->avances_deductions, 0, ',', ' ') : '—' }}
            </td>
            <td class="px-3 py-2 text-right font-mono font-bold text-emerald-700">{{ number_format($item->salaire_net, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right">
                @if($run->status !== 'brouillon')
                <a href="{{ route('rh.paie.bulletin-pdf', [$run, $item]) }}" target="_blank"
                   class="text-blue-500 hover:text-blue-700" title="Bulletin PDF individuel">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </a>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="13" class="px-4 py-10 text-center text-gray-400">
            @if($run->status === 'brouillon')
                Saisissez les variables mensuelles si nécessaire, puis cliquez sur « Calculer la paie ».
            @else Aucun employé traité.
            @endif
        </td></tr>
        @endforelse
        </tbody>
        @if($run->items->count() > 0)
        <tfoot class="bg-gray-50 font-semibold text-xs border-t-2 border-gray-300">
            <tr>
                <td colspan="6" class="px-3 py-2 text-right text-gray-500 uppercase text-xs">Totaux</td>
                <td class="px-3 py-2 text-right font-mono">{{ number_format($run->total_brut, 0, ',', ' ') }}</td>
                <td class="px-3 py-2 text-right font-mono text-red-600">{{ number_format($run->total_cnss_employee, 0, ',', ' ') }}</td>
                <td class="px-3 py-2 text-right font-mono text-amber-600">{{ number_format($run->total_cnss_employer, 0, ',', ' ') }}</td>
                <td class="px-3 py-2 text-right font-mono text-purple-600">{{ number_format($run->total_iuts, 0, ',', ' ') }}</td>
                <td></td>
                <td class="px-3 py-2 text-right font-mono text-emerald-700">{{ number_format($run->total_net, 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

{{-- Modal paiement --}}
<div x-ref="modalPaid" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-80 p-6">
        <h3 class="text-base font-semibold mb-4">Confirmer le paiement</h3>
        <form method="POST" action="{{ route('rh.paie.mark-paid', $run) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de paiement</label>
                <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            </div>
            <p class="text-xs text-gray-500 mb-4">
                Net total à payer : <strong>{{ number_format($run->total_net, 0, ',', ' ') }} FCFA</strong>
                pour {{ $run->employee_count }} employé(s).
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="$refs.modalPaid.classList.add('hidden')"
                        class="px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium">Confirmer</button>
            </div>
        </form>
    </div>
</div>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function payrollShow() {
    return {
        variables: [],
        showVarForm: false,
        saving: false,
        newVar: {
            employee_id: '', type: 'hs_25', label: '', qty: 0,
            unit: 'heures', amount: 0, is_gain: true, is_taxable: true, is_social_charged: true,
        },

        loadVariables() {
            fetch('{{ route('rh.paie.variables', $run) }}')
                .then(r => r.json()).then(data => { this.variables = data; });
        },

        onTypeChange() {
            const typesMeta = @json(\App\Models\PayrollVariable::TYPES);
            const meta = typesMeta[this.newVar.type];
            if (meta) {
                this.newVar.label = meta.label;
                this.newVar.unit  = meta.unit;
                this.newVar.is_gain = meta.gain;
                this.newVar.is_taxable = meta.taxable;
                this.newVar.is_social_charged = meta.taxable;
            }
        },

        saveVariable() {
            if (!this.newVar.employee_id) { window.toast('Choisissez un employé.', 'error'); return; }
            if (!this.newVar.amount)      { window.toast('Saisissez un montant.', 'error'); return; }
            this.saving = true;
            fetch('{{ route('rh.paie.variables.store', $run) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(this.newVar),
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) { window.toast(data.error, 'error'); return; }
                this.variables.push(data.variable);
                this.newVar = { employee_id:'', type:'hs_25', label:'', qty:0, unit:'heures', amount:0, is_gain:true, is_taxable:true, is_social_charged:true };
                this.showVarForm = false;
                window.toast('Variable ajoutée.', 'success');
            })
            .finally(() => this.saving = false);
        },

        async deleteVariable(id) {
            const ok = await window.erpConfirm({
                message: 'Supprimer cette variable ?',
                confirmLabel: 'Supprimer',
                isDanger: true,
            });
            if (!ok) return;
            const resp = await fetch(`{{ url('rh/paie/'.$run->id.'/variables') }}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            if (resp.ok) {
                this.variables = this.variables.filter(v => v.id !== id);
                window.toast('Variable supprimée.', 'success');
            } else {
                window.toast('Erreur lors de la suppression.', 'error');
            }
        },

        empName(id) {
            const item = @json($run->items->keyBy('employee_id'));
            return item[id]?.employee_name ?? 'Employé #'+id;
        },
    };
}
</script>
@endpush
@endsection
