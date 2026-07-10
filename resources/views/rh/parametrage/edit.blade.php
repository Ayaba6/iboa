@extends('layouts.erp')
@section('title', 'Paramétrage de la paie')

@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Paramétrage de la paie</span>
@endsection

@section('content')
{{-- ══ Config Alpine dans <script> — @json() est safe dans les balises script ══ --}}
<script>
window.__parametrageCfg = @json($cfg);
</script>
<div x-data="parametrageSage(window.__parametrageCfg)"
     class="max-w-6xl mx-auto">

{{-- ══ En-tête ═══════════════════════════════════════════════════════════════ --}}
<div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-[4px] bg-emerald-700 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Paramétrage de la paie</h1>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $company->name }}
                @if($setting->updated_at)
                &bull; Modifié le {{ $setting->updated_at->format('d/m/Y à H:i') }}
                @if($setting->updatedBy) par {{ $setting->updatedBy->name }}@endif
                @endif
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <span class="px-3 py-1.5 rounded-[3px] text-[11px] font-semibold bg-emerald-100 text-emerald-700">
            {{ $setting->country_code }} · {{ $setting->currency_code }}
        </span>
    </div>
</div>

<form method="POST" action="{{ route('rh.parametrage.update') }}">
@csrf @method('PUT')

<div class="flex gap-5 items-start">

{{-- ══ Colonne principale ══════════════════════════════════════════════════════ --}}
<div class="flex-1 min-w-0 space-y-4">

    {{-- ── Onglets ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <nav class="flex border-b border-gray-200">
            @php
            $navTabs = [
                [1, 'Cotisations',     'CNSS · AT/MP · SMIG'],
                [2, 'Temps de travail','Jours · HS · Congés'],
                [3, 'Quotient IUTS',   'Parts · Barème · Simulation'],
                [4, 'Bulletins',       'Numérotation · Devise'],
            ];
            @endphp
            @foreach($navTabs as [$n, $lbl, $sub])
            <button type="button" @click="tab = {{ $n }}"
                    :class="tab === {{ $n }}
                        ? 'border-b-2 border-emerald-600 text-emerald-800 bg-[#eef5f0]/60'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="flex-1 py-3 px-2 text-xs font-medium transition-colors text-center">
                <span class="block font-semibold">{{ $lbl }}</span>
                <span class="block text-[10px] opacity-60 mt-0.5 hidden sm:block">{{ $sub }}</span>
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ════ ONGLET 1 — Cotisations ════════════════════════════════════════════ --}}
    <div x-show="tab === 1" x-cloak class="space-y-4">

        {{-- CNSS --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-blue-50 border-b border-blue-100">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-blue-900">Caisse Nationale de Sécurité Sociale (CNSS)</p>
                    <p class="text-xs text-blue-500 mt-0.5">Cotisations Loi n°015-2006/AN · Décret n°2007-173/PRES/PM/MTSS</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-5">
                @php
                $cnssFields = [
                    ['cnss_employee_rate', 'Cotisation salarié',  '%',     '0.01', 'Retenue sur salaire brut', $setting->cnss_employee_rate],
                    ['cnss_employer_rate', 'Cotisation patronale','%',     '0.01', 'Charge sur salaire brut',  $setting->cnss_employer_rate],
                    ['cnss_ceiling',       'Plafond mensuel',     $setting->currency_code, '1000', 'Assiette maximale CNSS', $setting->cnss_ceiling],
                    ['cnss_at_rate',       'Taux AT/MP',          '%',     '0.01', 'Accidents du travail',     $setting->cnss_at_rate],
                ];
                @endphp
                @foreach($cnssFields as [$name, $label, $unit, $step, $help, $value])
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">{{ $label }}</label>
                    <div class="relative">
                        <input type="number" name="{{ $name }}" step="{{ $step }}" min="0"
                               value="{{ old($name, $value) }}"
                               x-on:input="{{ $name === 'cnss_employee_rate' ? 'cnssEmployee = $event.target.value' : ($name === 'cnss_employer_rate' ? 'cnssEmployer = $event.target.value' : '') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-emerald-500 @error($name) border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">{{ $unit }}</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">{{ $help }}</p>
                    @error($name)<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                @endforeach
            </div>
            {{-- Récap charges sur un salaire exemple --}}
            <div class="mx-5 mb-5 bg-blue-50 rounded-[4px] border border-blue-100 p-4">
                <p class="text-xs font-semibold text-blue-700 mb-3 uppercase tracking-wide">Simulation charges sur le SMIG</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                    <div>
                        <p class="text-blue-500">Brut (SMIG)</p>
                        <p class="font-bold font-mono text-blue-900 text-sm mt-0.5" x-text="parseInt(smig).toLocaleString('fr-FR') + ' F'"></p>
                    </div>
                    <div>
                        <p class="text-blue-500">CNSS salarié</p>
                        <p class="font-bold font-mono text-red-600 text-sm mt-0.5"
                           x-text="'- ' + Math.round(smig * cnssEmployee / 100).toLocaleString('fr-FR') + ' F'"></p>
                    </div>
                    <div>
                        <p class="text-blue-500">CNSS patronal</p>
                        <p class="font-bold font-mono text-orange-600 text-sm mt-0.5"
                           x-text="'+ ' + Math.round(smig * cnssEmployer / 100).toLocaleString('fr-FR') + ' F'"></p>
                    </div>
                    <div class="border-l border-blue-200">
                        <p class="text-blue-500">Coût employeur</p>
                        <p class="font-bold font-mono text-blue-900 text-sm mt-0.5"
                           x-text="Math.round(smig * (1 + cnssEmployer / 100)).toLocaleString('fr-FR') + ' F'"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- SMIG --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-emerald-50 border-b border-emerald-100">
                <svg class="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-emerald-900">Salaire Minimum Interprofessionnel Garanti (SMIG)</p>
                    <p class="text-xs text-emerald-500 mt-0.5">Référence légale — à mettre à jour lors de chaque revalorisation officielle</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-5 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Montant mensuel ({{ $setting->currency_code }})</label>
                    <div class="relative">
                        <input type="number" name="smig" min="0" step="500"
                               value="{{ old('smig', $setting->smig) }}"
                               x-model="smig"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm text-right pr-16 font-semibold text-base focus:ring-1 focus:ring-emerald-500 @error('smig') border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">{{ $setting->currency_code }}</span>
                    </div>
                    @error('smig')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2 grid grid-cols-3 gap-3">
                    <div class="bg-emerald-50 rounded-[4px] border border-emerald-100 p-3 text-center">
                        <p class="text-[10px] text-emerald-600 font-medium uppercase">Mensuel</p>
                        <p class="text-base font-bold text-emerald-800 font-mono mt-1" x-text="parseInt(smig).toLocaleString('fr-FR')"></p>
                        <p class="text-[10px] text-emerald-400 mt-0.5">{{ $setting->currency_code }}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-[4px] border border-emerald-100 p-3 text-center">
                        <p class="text-[10px] text-emerald-600 font-medium uppercase">Annuel</p>
                        <p class="text-base font-bold text-emerald-800 font-mono mt-1" x-text="(parseInt(smig)*12).toLocaleString('fr-FR')"></p>
                        <p class="text-[10px] text-emerald-400 mt-0.5">{{ $setting->currency_code }}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-[4px] border border-emerald-100 p-3 text-center">
                        <p class="text-[10px] text-emerald-600 font-medium uppercase">Journalier</p>
                        <p class="text-base font-bold text-emerald-800 font-mono mt-1"
                           x-text="workDaysMonth > 0 ? Math.round(parseInt(smig)/workDaysMonth).toLocaleString('fr-FR') : '—'"></p>
                        <p class="text-[10px] text-emerald-400 mt-0.5">{{ $setting->currency_code }}/j</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- [P3.B] Effort de paix --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-rose-50 border-b border-rose-100">
                <svg class="w-5 h-5 text-rose-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-rose-900">Retenue Effort de paix — Code 9000</p>
                    <p class="text-xs text-rose-500 mt-0.5">Loi des Finances Burkina Faso · Prélevée sur le salaire net imposable</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-5 items-end">
                {{-- Activation --}}
                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-3">Activation</label>
                    <label class="inline-flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="effort_paix_enabled" value="0">
                        <input type="checkbox" name="effort_paix_enabled" value="1"
                               {{ old('effort_paix_enabled', $setting->effort_paix_enabled) ? 'checked' : '' }}
                               x-model="effortPaixEnabled"
                               class="w-4 h-4 text-rose-600 border-gray-300 rounded focus:ring-rose-500">
                        <span class="text-sm text-gray-700">Retenue active</span>
                    </label>
                    <p class="text-[10px] text-gray-400 mt-2">Désactivez si non applicable à votre secteur</p>
                </div>
                {{-- Taux --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Taux (%)</label>
                    <div class="relative">
                        <input type="number" name="effort_paix_rate" step="0.5" min="0" max="20"
                               value="{{ old('effort_paix_rate', $setting->effort_paix_rate) }}"
                               x-model.number="effortPaixRate"
                               :disabled="!effortPaixEnabled"
                               @change="effortPaixRate = Math.max(0, Math.min(20, parseFloat($event.target.value)||0)); $event.target.value = effortPaixRate"
                               @blur="effortPaixRate = Math.max(0, Math.min(20, parseFloat($event.target.value)||0)); $event.target.value = effortPaixRate"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-8 focus:ring-1 focus:ring-rose-500 disabled:bg-gray-50 disabled:text-gray-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Légal BF : 1 %</p>
                    @error('effort_paix_rate')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                {{-- Aperçu réactif --}}
                <div class="bg-rose-50 rounded-[4px] border border-rose-100 p-3 text-center">
                    <p class="text-[10px] text-rose-600 font-medium uppercase">Sur le SMIG net imposable</p>
                    <p class="text-base font-bold text-rose-800 font-mono mt-1"
                       x-show="effortPaixEnabled"
                       x-text="Math.round(smig * (effortPaixRate||1) / 100).toLocaleString('fr-FR') + ' F'"></p>
                    <p class="text-base font-bold text-gray-400 mt-1" x-show="!effortPaixEnabled">—</p>
                    <p class="text-[10px] text-rose-400 mt-0.5" x-show="effortPaixEnabled">prélevés / mois</p>
                </div>
            </div>
        </div>

    </div>{{-- /tab 1 --}}

    {{-- ════ ONGLET 2 — Temps de travail ══════════════════════════════════════ --}}
    <div x-show="tab === 2" x-cloak class="space-y-4">

        {{-- Durée légale --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-amber-50 border-b border-amber-100">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-amber-900">Durée légale du travail</p>
                    <p class="text-xs text-amber-500 mt-0.5">Code du travail du Burkina Faso · Art. 133 et suivants</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Jours ouvrés / mois</label>
                    <div class="relative">
                        <input type="number" name="work_days_month" min="1" max="31"
                               value="{{ old('work_days_month', $setting->work_days_month) }}"
                               x-model="workDaysMonth"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-amber-500">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">jours</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Base salaire journalier</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Heures / jour</label>
                    <div class="relative">
                        <input type="number" name="work_hours_day" min="1" max="24"
                               value="{{ old('work_hours_day', $setting->work_hours_day) }}"
                               x-model="workHoursDay"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-10 focus:ring-1 focus:ring-amber-500">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">h</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Journée standard</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Congés annuels légaux</label>
                    <div class="relative">
                        <input type="number" name="leave_days_year" min="1" max="365"
                               value="{{ old('leave_days_year', $setting->leave_days_year) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-amber-500">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">jours</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Art. 200 Code du travail BF</p>
                </div>
            </div>
            {{-- Récap réactif --}}
            <div class="mx-5 mb-5 grid grid-cols-3 gap-3">
                <div class="bg-amber-50 rounded-[4px] border border-amber-100 p-3 text-center">
                    <p class="text-[10px] text-amber-600 font-medium uppercase">Heures / mois</p>
                    <p class="font-bold text-amber-900 text-lg mt-1 font-mono"
                       x-text="(workDaysMonth * workHoursDay).toLocaleString('fr-FR') + ' h'"></p>
                </div>
                <div class="bg-amber-50 rounded-[4px] border border-amber-100 p-3 text-center">
                    <p class="text-[10px] text-amber-600 font-medium uppercase">Heures / an</p>
                    <p class="font-bold text-amber-900 text-lg mt-1 font-mono"
                       x-text="(workDaysMonth * 12 * workHoursDay).toLocaleString('fr-FR') + ' h'"></p>
                </div>
                <div class="bg-amber-50 rounded-[4px] border border-amber-100 p-3 text-center">
                    <p class="text-[10px] text-amber-600 font-medium uppercase">Taux horaire SMIG</p>
                    <p class="font-bold text-amber-900 text-lg mt-1 font-mono"
                       x-text="(workDaysMonth * workHoursDay) > 0 ? Math.round(smig / (workDaysMonth * workHoursDay)).toLocaleString('fr-FR') + ' F/h' : '—'"></p>
                </div>
            </div>
        </div>

        {{-- Heures supplémentaires --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-orange-50 border-b border-orange-100">
                <svg class="w-5 h-5 text-orange-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-orange-900">Majorations — Heures supplémentaires</p>
                    <p class="text-xs text-orange-500 mt-0.5">Art. 146 Code du travail BF · Décret n°97-107/PRES/PM/MEFP</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-5">
                @php
                $hsFields = [
                    ['hs_rate_25',   'Jours ouvrés — 41e à 48e h',   'bg-yellow-50 border-yellow-200 text-yellow-700', '+25%', $setting->hs_rate_25],
                    ['hs_rate_50',   'Dimanches & jours fériés',      'bg-orange-50 border-orange-200 text-orange-700', '+50%', $setting->hs_rate_50],
                    ['hs_rate_nuit', 'Travail de nuit (21h — 5h)',    'bg-[#eef5f0] border-emerald-200 text-emerald-800', 'Nuit', $setting->hs_rate_nuit],
                ];
                @endphp
                @foreach($hsFields as [$name, $desc, $badgeCls, $badge, $value])
                <div class="bg-gray-50 rounded-[4px] border border-gray-300 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-gray-600">Majoration</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full border {{ $badgeCls }}">{{ $badge }}</span>
                    </div>
                    <div class="relative">
                        <input type="number" name="{{ $name }}" step="0.5" min="0" max="500"
                               value="{{ old($name, $value) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-8 font-semibold focus:ring-1 focus:ring-orange-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1.5">{{ $desc }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Ancienneté --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-emerald-50 border-b border-teal-100">
                <svg class="w-5 h-5 text-emerald-700 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-teal-900">Prime d'ancienneté</p>
                    <p class="text-xs text-teal-500 mt-0.5">Art. 109 Code du travail BF · Calculée automatiquement sur le salaire de base contractuel</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="bg-gray-50 rounded-[4px] border border-gray-300 p-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">
                        Taux par année complète de service
                    </label>
                    <div class="relative">
                        <input type="number" name="anc_rate_per_year" step="0.25" min="0" max="10"
                               value="{{ old('anc_rate_per_year', $setting->anc_rate_per_year) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-8 font-semibold focus:ring-1 focus:ring-emerald-400 @error('anc_rate_per_year') border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%/an</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1.5">BF référence : 2 % / an</p>
                    @error('anc_rate_per_year')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div class="bg-gray-50 rounded-[4px] border border-gray-300 p-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">
                        Plafond du taux d'ancienneté
                    </label>
                    <div class="relative">
                        <input type="number" name="anc_rate_max_pct" step="0.5" min="0" max="100"
                               value="{{ old('anc_rate_max_pct', $setting->anc_rate_max_pct) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-8 font-semibold focus:ring-1 focus:ring-emerald-400 @error('anc_rate_max_pct') border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1.5">BF référence : 25 % (atteint après 12 ans et demi)</p>
                    @error('anc_rate_max_pct')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

    </div>{{-- /tab 2 --}}

    {{-- ════ ONGLET 3 — Quotient IUTS ══════════════════════════════════════════ --}}
    <div x-show="tab === 3" x-cloak class="space-y-4">

        {{-- Parts de base --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-purple-50 border-b border-purple-100">
                <svg class="w-5 h-5 text-purple-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-purple-900">Quotient familial — Parts de base</p>
                    <p class="text-xs text-purple-500 mt-0.5">CGI Burkina Faso · Art. 73 et suivants · Valeurs modifiables selon évolution législative</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Célibataire / Divorcé(e)</label>
                    <div class="relative">
                        <input type="number" name="parts_base_single" step="0.25" min="0.5" max="5"
                               value="{{ old('parts_base_single', $setting->parts_base_single) }}"
                               x-model="partsSingle"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-purple-500 @error('parts_base_single') border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">parts</span>
                    </div>
                    @error('parts_base_single')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Marié(e)</label>
                    <div class="relative">
                        <input type="number" name="parts_base_married" step="0.25" min="0.5" max="5"
                               value="{{ old('parts_base_married', $setting->parts_base_married) }}"
                               x-model="partsMarried"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-purple-500 @error('parts_base_married') border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">parts</span>
                    </div>
                    @error('parts_base_married')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Veuf / Veuve</label>
                    <div class="relative">
                        <input type="number" name="parts_base_widowed" step="0.25" min="0.5" max="5"
                               value="{{ old('parts_base_widowed', $setting->parts_base_widowed) }}"
                               x-model="partsWidowed"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-purple-500 @error('parts_base_widowed') border-red-400 @enderror">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">parts</span>
                    </div>
                    @error('parts_base_widowed')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Parts par enfant</label>
                    <div class="relative">
                        <input type="number" name="parts_per_child" step="0.25" min="0" max="5"
                               value="{{ old('parts_per_child', $setting->parts_per_child) }}"
                               x-model="partsPerChild"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-purple-500">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">part</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Par enfant à charge (max 6)</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">NB_PARTS maximum autorisé</label>
                    <div class="relative">
                        <input type="number" name="nb_parts_max" min="1" max="20"
                               value="{{ old('nb_parts_max', $setting->nb_parts_max) }}"
                               x-model="nbPartsMax"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right pr-14 focus:ring-1 focus:ring-purple-500">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">parts</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Plafond légal du quotient familial</p>
                </div>
                {{-- Exemples réactifs --}}
                <div class="sm:col-span-2 bg-purple-50 rounded-[4px] border border-purple-100 p-3">
                    <p class="text-[10px] text-purple-600 font-semibold uppercase tracking-wide mb-2">Exemples de quotients</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Célibataire, 0 enfant</span>
                            <span class="font-bold text-purple-700 font-mono" x-text="Math.min(parseFloat(partsSingle),parseFloat(nbPartsMax)).toFixed(2) + ' parts'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Marié(e), 2 enfants</span>
                            <span class="font-bold text-purple-700 font-mono" x-text="Math.min(parseFloat(partsMarried) + 2*parseFloat(partsPerChild), parseFloat(nbPartsMax)).toFixed(2) + ' parts'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Marié(e), 6 enfants</span>
                            <span class="font-bold text-purple-700 font-mono" x-text="Math.min(parseFloat(partsMarried) + 6*parseFloat(partsPerChild), parseFloat(nbPartsMax)).toFixed(2) + ' parts'"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barème IUTS --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 bg-[#eef5f0] border-b border-emerald-100">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-emerald-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-emerald-900">Barème IUTS — par part, mensuel</p>
                        <p class="text-xs text-emerald-600 mt-0.5">Tranches sur revenu ÷ NB_PARTS · Annexe fiscale CGI BF</p>
                    </div>
                </div>
                <button type="button" @click="addBracket()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-medium rounded-[4px] transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Ajouter
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#eef5f0] text-[11px] text-emerald-900 font-bold uppercase tracking-wide border-b border-gray-300">
                        <tr>
                            <th class="px-3 py-2.5 text-center w-10">#</th>
                            <th class="px-3 py-2.5 text-right">De ({{ $setting->currency_code }}/part)</th>
                            <th class="px-3 py-2.5 text-right">Jusqu'à ({{ $setting->currency_code }}/part)</th>
                            <th class="px-3 py-2.5 text-center w-48">Taux</th>
                            <th class="px-3 py-2.5 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(b, i) in brackets" :key="i">
                            <tr :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50/40'">
                                <td class="px-3 py-1.5 text-center">
                                    <span class="inline-flex w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold items-center justify-center"
                                          x-text="i + 1"></span>
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    <span class="text-xs font-mono text-gray-500"
                                          x-text="i === 0 ? '0' : (parseFloat(brackets[i-1].limit)+1).toLocaleString('fr-FR')"></span>
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    <template x-if="i < brackets.length - 1">
                                        <input type="number" :name="`brackets[${i}][limit]`" x-model="b.limit"
                                               min="1" class="w-36 border border-gray-300 rounded-[4px] px-3 py-1.5 text-xs font-mono text-right focus:ring-1 focus:ring-emerald-500">
                                    </template>
                                    <template x-if="i === brackets.length - 1">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="text-emerald-600 font-bold text-base">∞</span>
                                            <span class="text-xs text-gray-400">illimité</span>
                                            <input type="hidden" :name="`brackets[${i}][limit]`" value="9999999999">
                                        </div>
                                    </template>
                                </td>
                                <td class="px-3 py-1.5">
                                    <div class="flex items-center gap-2">
                                        <input type="number" :name="`brackets[${i}][rate]`" x-model="b.rate"
                                               step="0.5" min="0" max="100"
                                               class="w-20 border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono text-right focus:ring-1 focus:ring-emerald-500">
                                        <span class="text-xs text-gray-400">%</span>
                                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-200"
                                                 :class="parseFloat(b.rate) === 0 ? 'bg-gray-300' : 'bg-emerald-600'"
                                                 :style="`width:${Math.min(parseFloat(b.rate)||0, 100)}%`"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <button type="button" @click="removeBracket(i)"
                                            x-show="brackets.length > 1"
                                            class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            @error('brackets')<p class="text-red-500 text-xs px-5 pb-3">{{ $message }}</p>@enderror
        </div>

        {{-- Simulateur IUTS --}}
        <div class="bg-white rounded-[4px] border border-emerald-200 overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3.5 bg-[#eef5f0] border-b border-emerald-100">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <p class="text-sm font-semibold text-emerald-900">Simulateur IUTS</p>
                <span class="text-xs text-emerald-500">— basé sur le barème et les parts ci-dessus</span>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Salaire brut imposable ({{ $setting->currency_code }})</label>
                    <input type="number" x-model="simBrut" step="5000" placeholder="Ex : 150 000"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">NB_PARTS du salarié</label>
                    <input type="number" x-model="simParts" step="0.5" min="1"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="bg-[#eef5f0] rounded-[4px] border border-emerald-200 p-4 text-center">
                    <p class="text-xs text-emerald-700 font-medium uppercase tracking-wide">IUTS calculé</p>
                    <p class="text-[16px] font-bold text-emerald-900 font-mono mt-1"
                       x-text="computeIuts().toLocaleString('fr-FR') + ' F'"></p>
                    <p class="text-[10px] text-emerald-500 mt-1"
                       x-show="simBrut > 0"
                       x-text="'soit ' + (computeIuts() / simBrut * 100).toFixed(2) + '% du brut'"></p>
                    <p class="text-[10px] text-emerald-500"
                       x-show="simBrut > 0"
                       x-text="'Net avant CNSS ≈ ' + Math.round(simBrut - computeIuts()).toLocaleString('fr-FR') + ' F'"></p>
                </div>
            </div>
        </div>

    </div>{{-- /tab 3 --}}

    {{-- ════ ONGLET 4 — Bulletins ══════════════════════════════════════════════ --}}
    <div x-show="tab === 4" x-cloak class="space-y-4">

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-3.5 bg-gray-50 border-b border-gray-200">
                <svg class="w-5 h-5 text-gray-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-gray-800">Numérotation & Identification des bulletins</p>
                    <p class="text-xs text-gray-400 mt-0.5">Format généré : PRÉFIXE-ANNÉE-NUMÉRO</p>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">N° affiliation CNSS</label>
                    <input type="text" name="cnss_affiliation" maxlength="30"
                           value="{{ old('cnss_affiliation', $setting->cnss_affiliation) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-400"
                           placeholder="Ex : 213762A">
                    <p class="text-[10px] text-gray-400 mt-1">Affiché sur le bulletin</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Téléphone (bulletin)</label>
                    <input type="text" name="phone" maxlength="30"
                           value="{{ old('phone', $setting->phone) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400"
                           placeholder="Ex : 50 31 02 91">
                    <p class="text-[10px] text-gray-400 mt-1">Affiché sur le bulletin</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Adresse (bulletin)</label>
                    <input type="text" name="address_bulletin" maxlength="200"
                           value="{{ old('address_bulletin', $setting->address_bulletin) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400"
                           placeholder="Ex : 01 BP 1234 Ouagadougou">
                    <p class="text-[10px] text-gray-400 mt-1">Adresse affichée sur le bulletin (peut différer de l'adresse légale)</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Préfixe bulletin</label>
                    <input type="text" name="bulletin_prefix" maxlength="10"
                           value="{{ old('bulletin_prefix', $setting->bulletin_prefix) }}"
                           x-model="bulletinPrefix"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-gray-400"
                           style="text-transform:uppercase">
                    <p class="text-[10px] text-gray-400 mt-1">Max 10 caractères</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Code devise</label>
                    <input type="text" name="currency_code" maxlength="10"
                           value="{{ old('currency_code', $setting->currency_code) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-gray-400">
                    <p class="text-[10px] text-gray-400 mt-1">Ex : FCFA, XOF, EUR</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Code pays</label>
                    <input type="text" name="country_code" maxlength="5"
                           value="{{ old('country_code', $setting->country_code) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-gray-400"
                           style="text-transform:uppercase">
                    <p class="text-[10px] text-gray-400 mt-1">ISO 3166 : BF, CI, SN…</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Notes internes</label>
                    <input type="text" name="notes" maxlength="500"
                           value="{{ old('notes', $setting->notes) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-gray-400"
                           placeholder="Observations…">
                </div>
            </div>
            {{-- Aperçu numérotation --}}
            <div class="mx-5 mb-5 bg-gray-50 rounded-[4px] border border-gray-300 p-4">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-3">Aperçu — Numérotation</p>
                <div class="flex items-center gap-2 font-mono text-sm flex-wrap">
                    <span class="px-3 py-1.5 bg-white border border-gray-300 rounded-[4px] text-gray-800 font-bold"
                          x-text="bulletinPrefix || '···'"></span>
                    <span class="text-gray-400 font-bold">—</span>
                    <span class="px-3 py-1.5 bg-white border border-gray-300 rounded-[4px] text-gray-500">{{ now()->format('Y') }}</span>
                    <span class="text-gray-400 font-bold">—</span>
                    <span class="px-3 py-1.5 bg-white border border-gray-300 rounded-[4px] text-gray-500">0001</span>
                    <span class="ml-2 text-xs text-gray-400">← exemple bulletin n°1</span>
                </div>
            </div>
        </div>

    </div>{{-- /tab 4 --}}

</div>{{-- /colonne principale --}}

{{-- ══ Panneau latéral ══════════════════════════════════════════════════════════ --}}
<div class="w-60 flex-shrink-0 space-y-4 sticky top-4">

    {{-- Résumé actuel (lu depuis DB) --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5" style="background:linear-gradient(135deg,#047857,#065f46)">
            <p class="text-white font-bold text-sm">Paramètres actuels</p>
            <p class="text-emerald-100 text-xs mt-0.5">{{ $company->name }}</p>
        </div>
        <div class="p-4 space-y-2 text-xs divide-y divide-gray-100">
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">CNSS salarié</span>
                <span class="font-bold text-red-600">{{ $setting->cnss_employee_rate }} %</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">CNSS patronal</span>
                <span class="font-bold text-orange-600">{{ $setting->cnss_employer_rate }} %</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">AT/MP</span>
                <span class="font-bold text-amber-600">{{ $setting->cnss_at_rate }} %</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">SMIG mensuel</span>
                <span class="font-bold text-emerald-600 font-mono">{{ number_format($setting->smig, 0, ',', ' ') }} F</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">Jours ouvrés</span>
                <span class="font-bold text-gray-700">{{ $setting->work_days_month }} j/mois</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">Parts célibataire</span>
                <span class="font-bold text-purple-600">{{ $setting->parts_base_single }}</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">Parts marié(e)</span>
                <span class="font-bold text-purple-600">{{ $setting->parts_base_married }}</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">NB_PARTS max</span>
                <span class="font-bold text-emerald-700">{{ $setting->nb_parts_max }}</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">Tranches IUTS</span>
                <span class="font-bold text-emerald-700">{{ count($brackets) }}</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-500">Effort de paix</span>
                <span class="font-bold {{ $setting->effort_paix_enabled ? 'text-rose-600' : 'text-gray-400' }}">
                    {{ $setting->effort_paix_enabled ? $setting->effort_paix_rate.' %' : 'OFF' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Navigation onglets --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-3 space-y-1">
        @foreach([1 => 'Cotisations sociales', 2 => 'Temps de travail', 3 => 'Quotient IUTS', 4 => 'Bulletins de paie'] as $n => $lbl)
        <button type="button" @click="tab = {{ $n }}"
                :class="tab === {{ $n }} ? 'bg-[#eef5f0] text-emerald-800 font-semibold' : 'text-gray-600 hover:bg-gray-50'"
                class="w-full text-left text-xs px-2.5 py-1.5 rounded-[4px] transition-colors flex items-center gap-2">
            <span :class="tab === {{ $n }} ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-500'"
                  class="w-4 h-4 rounded-full text-[9px] font-bold flex items-center justify-center flex-shrink-0">{{ $n }}</span>
            {{ $lbl }}
        </button>
        @endforeach
    </div>

    {{-- Boutons action --}}
    <button type="submit"
            class="w-full flex items-center justify-center gap-2 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-[4px] text-sm font-semibold transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Enregistrer
    </button>
    <a href="{{ route('rh.dashboard') }}"
       class="w-full flex items-center justify-center text-sm text-gray-600 hover:text-gray-800 py-2">
        Annuler
    </a>

</div>

</div>{{-- /flex --}}
</form>
</div>

@push('scripts')
<script>
function parametrageSage(cfg) {
    return {
        tab: 1,

        /* ── Données réactives (toutes issues de la DB via cfg) ── */
        smig:          cfg.smig,
        workDaysMonth: cfg.workDaysMonth,
        workHoursDay:  cfg.workHoursDay,
        cnssEmployee:  cfg.cnssEmployee,
        cnssEmployer:  cfg.cnssEmployer,
        cnssCeiling:   cfg.cnssCeiling,
        partsSingle:   cfg.partsSingle,
        partsMarried:  cfg.partsMarried,
        partsWidowed:  cfg.partsWidowed,
        partsPerChild: cfg.partsPerChild,
        nbPartsMax:    cfg.nbPartsMax,
        brackets:           cfg.brackets,
        bulletinPrefix:     cfg.bulletinPrefix,
        effortPaixEnabled:  cfg.effortPaixEnabled,  // [P3.B]
        effortPaixRate:     cfg.effortPaixRate,      // [P3.B]

        /* ── Simulateur ── */
        simBrut:  0,
        simParts: cfg.partsSingle,

        /* ── Barème : ajouter / supprimer une tranche ── */
        addBracket() {
            const last    = this.brackets.length - 1;
            const prevLim = last > 0 ? parseFloat(this.brackets[last - 1].limit) : 0;
            this.brackets.splice(last, 0, { limit: prevLim + 10000, rate: 0 });
        },
        removeBracket(i) {
            if (this.brackets.length <= 1) return;
            this.brackets.splice(i, 1);
        },

        /* ── Calcul IUTS par quotient familial ── */
        computeIuts() {
            const brut  = parseFloat(this.simBrut)  || 0;
            const parts = parseFloat(this.simParts) || 1;
            if (brut <= 0 || parts <= 0) return 0;

            const quotient = brut / parts;
            let tax = 0, prev = 0;

            for (const b of this.brackets) {
                const lim  = parseFloat(b.limit) || 9999999999;
                const rate = parseFloat(b.rate)  || 0;
                if (quotient <= prev) break;
                tax += (Math.min(quotient, lim) - prev) * rate / 100;
                prev = lim;
                if (quotient <= lim) break;
            }
            return Math.round(tax * parts);
        },
    };
}
</script>
@endpush
@endsection
