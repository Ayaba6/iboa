@extends('layouts.erp')
@section('title', 'Verrouillage des périodes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Verrouillage des périodes</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="max-w-5xl mx-auto space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Verrouillage des périodes mensuelles</h1>
            <p class="text-sm text-gray-500 mt-0.5">Figez les arrêtés mensuels après revue : aucune écriture ne pourra plus être créée, modifiée ou validée.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Exercice</label>
                <select name="fiscal_year_id" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    @foreach($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" {{ $fiscalYear?->id === $fy->id ? 'selected' : '' }}>
                        {{ $fy->label }}
                        @if($fy->status !== 'ouvert') ({{ ucfirst($fy->status) }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>


    {{-- Aide --}}
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Comment ça marche</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700 text-xs">
            <li>Verrouiller un mois empêche toute création/modification/validation/suppression d'écriture dont la date tombe dans ce mois.</li>
            <li>Avant verrouillage, tous les brouillons du mois doivent être validés ou supprimés.</li>
            <li>Un mois verrouillé peut être déverrouillé à tout moment par un validateur comptable.</li>
            <li>Cette protection s'ajoute au verrouillage d'exercice — utile pour figer un arrêté mensuel sans clôturer l'année.</li>
        </ul>
    </div>

    @if(!$fiscalYear)
        <div class="bg-gray-50 border border-gray-300 rounded-[4px] p-6 text-center text-gray-500 text-sm">Aucun exercice fiscal défini.</div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Mois de l'exercice {{ $fiscalYear->label }}</h2>
        </div>
        <div class="tbl-scroll">
        <table class="tbl tbl-sticky w-full">
            <thead>
                <tr>
                    <th class="text-left">Mois</th>
                    <th class="text-right">Écritures</th>
                    <th class="text-right">Volume validé</th>
                    <th class="text-center">État</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($months as $m)
                    @php $isLocked = $m['lock'] !== null; @endphp
                    <tr class="hover:bg-gray-50 {{ $isLocked ? 'bg-gray-50/60' : '' }}">
                        <td class="">
                            <span class="font-medium text-gray-900">{{ ucfirst($m['label']) }}</span>
                            @if($isLocked && $m['lock']->reason)
                                <p class="text-xs text-gray-500 mt-0.5 italic">« {{ $m['lock']->reason }} »</p>
                            @endif
                        </td>
                        <td class=" text-right tabular-nums text-gray-700">
                            {{ $m['total_count'] }}
                            @if($m['draft_count'] > 0)
                                <span class="text-xs text-amber-600 ml-1">({{ $m['draft_count'] }} brouillon{{ $m['draft_count']>1?'s':'' }})</span>
                            @endif
                        </td>
                        <td class=" text-right tabular-nums text-gray-700">{{ $fmt($m['total_volume']) }} FCFA</td>
                        <td class=" text-center">
                            @if($isLocked)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                    Verrouillé
                                </span>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    par {{ $m['lock']->lockedBy?->name ?? 'système' }}
                                    le {{ $m['lock']->locked_at?->format('d/m/Y') }}
                                </p>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a5 5 0 00-5 5v2H4a2 2 0 00-2 2v5a2 2 0 002 2h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-5-5zm3 7H7V7a3 3 0 016 0v2z"/></svg>
                                    Ouvert
                                </span>
                            @endif
                        </td>
                        <td class=" text-right">
                            @if($isLocked)
                                <form action="{{ route('comptabilite.periods.unlock', $m['lock']) }}" method="POST" class="inline"
                                      data-confirm="Déverrouiller {{ $m['label'] }} ? Les écritures seront à nouveau modifiables."
                                      data-confirm-title="Déverrouiller la période"
                                      data-confirm-label="Déverrouiller"
                                      data-confirm-danger="false">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-violet-600 hover:underline font-medium">Déverrouiller</button>
                                </form>
                            @else
                                <button type="button"
                                        onclick="document.getElementById('lock-form-{{ $m['year'] }}-{{ $m['month'] }}').classList.toggle('hidden')"
                                        class="text-xs text-red-600 hover:underline font-medium {{ $m['draft_count'] > 0 ? 'opacity-50 cursor-help' : '' }}"
                                        @if($m['draft_count']>0) title="{{ $m['draft_count'] }} brouillon(s) restant(s)" @endif>
                                    Verrouiller →
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if(!$isLocked)
                    <tr id="lock-form-{{ $m['year'] }}-{{ $m['month'] }}" class="hidden bg-red-50/50">
                        <td colspan="5" class="px-3 py-1.5">
                            <form action="{{ route('comptabilite.periods.lock') }}" method="POST" class="flex flex-wrap items-end gap-3"
                                  data-confirm="Verrouiller {{ $m['label'] }} ? Cette action peut être annulée à tout moment."
                                  data-confirm-title="Verrouiller la période"
                                  data-confirm-label="Verrouiller">
                                @csrf
                                <input type="hidden" name="year" value="{{ $m['year'] }}">
                                <input type="hidden" name="month" value="{{ $m['month'] }}">
                                <div class="flex-1 min-w-[240px]">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Motif (optionnel)</label>
                                    <input type="text" name="reason" maxlength="255"
                                           placeholder="Ex. : arrêté mensuel validé par DAF"
                                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                                </div>
                                <button type="submit"
                                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                                    Confirmer le verrouillage
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

</div>
@endsection
