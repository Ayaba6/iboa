@extends('layouts.erp')
@section('title', 'Nouvel employé')

@section('breadcrumb')
    <a href="{{ route('rh.employes.index') }}" class="hover:text-gray-700">Employés</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<script>
function createEmployeeForm() {
    return {
        tab: 1,
        tabs: ['État civil', 'Affectation', 'Contrat & Rémunération', 'Coordonnées bancaires'],

        /* ── NB_PARTS live ── */
        familyStatus: '{{ old('family_status','celibataire') }}',
        nbChildren:   {{ old('nb_children', 0) }},
        partsSingle:  {{ $payroll->parts_base_single }},
        partsMarried: {{ $payroll->parts_base_married }},
        partsWidowed: {{ $payroll->parts_base_widowed }},
        partsPerChild:{{ $payroll->parts_per_child }},
        nbPartsMax:   {{ $payroll->nb_parts_max }},
        get nbParts() {
            let base = this.familyStatus === 'marie' ? this.partsMarried
                     : this.familyStatus === 'veuf'  ? this.partsWidowed
                     : this.partsSingle;
            let n = Math.min(parseFloat(base) + (parseInt(this.nbChildren)||0) * parseFloat(this.partsPerChild), parseFloat(this.nbPartsMax));
            return n.toFixed(1);
        },

        /* ── SMIG indicator ── */
        baseSalary:      {{ old('base_salary', 0) }},
        smig:            {{ $payroll->smig }},
        cnssEmpRate:     {{ $payroll->cnss_employee_rate }},
        cnssEmpRate_pat: {{ $payroll->cnss_employer_rate }},
        cnssCeiling:     {{ $payroll->cnss_ceiling }},
        get salaryStatus() {
            let s = parseInt(this.baseSalary) || 0;
            if (s === 0) return 'neutral';
            if (s < this.smig) return 'danger';
            if (s < this.smig * 2) return 'ok';
            return 'good';
        },
        get salaryRatio() {
            let s = parseInt(this.baseSalary) || 0;
            if (s === 0) return 0;
            return Math.min(Math.round((s / (this.smig * 4)) * 100), 100);
        },

        /* ── Contrat ── */
        contractType: '{{ old('contract_type','CDI') }}',
        paymentMode:  '{{ old('payment_mode','virement') }}',

        /* ── Primes & indemnités ── */
        allowanceTypes: {!! $allowanceTypes->map(fn($t) => ['id'=>$t->id,'name'=>$t->name,'code'=>$t->code,'taxable'=>(bool)$t->is_taxable])->values()->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},
        allowances: {!! collect(old('allowances',[]))->map(fn($r)=>['type_id'=>(int)($r['type_id']??0),'amount'=>(int)($r['amount']??0)])->filter(fn($r)=>$r['type_id']>0)->values()->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},
        newTypeId: '',
        newAmount: 0,
        isAutoComputed(typeId) {
            let t = this.allowanceTypes.find(a => a.id == typeId);
            return t && t.code === 'ANCIENNETE';
        },
        addAllowance() {
            if (!this.newTypeId) return;
            if (this.allowances.find(a => a.type_id == this.newTypeId)) return;
            let auto = this.isAutoComputed(this.newTypeId);
            if (!auto && this.newAmount <= 0) return;
            this.allowances.push({ type_id: parseInt(this.newTypeId), amount: auto ? 0 : parseInt(this.newAmount) });
            this.newTypeId = ''; this.newAmount = 0;
        },
        removeAllowance(i) { this.allowances.splice(i, 1); },
        typeName(id) { let t = this.allowanceTypes.find(a => a.id == id); return t ? t.name : ''; },
        get totalAllowances() { return this.allowances.reduce((s,a) => s + (parseInt(a.amount)||0), 0); },

        /* ── Tab completion ── */
        isTabOk(t) {
            if (t === 1) return this.lastName && this.firstName;
            if (t === 3) return this.baseSalary > 0;
            return true;
        },
        lastName:  '{{ old('last_name','') }}',
        firstName: '{{ old('first_name','') }}',

        /* ── Submit : validation manuelle pour éviter le focus sur champ masqué ── */
        formError: '',
        submitForm(event) {
            this.formError = '';
            // Tab 1 : nom & prénom
            if (!this.lastName.trim() || !this.firstName.trim()) {
                this.tab = 1;
                this.formError = 'Veuillez renseigner le nom et le prénom (onglet État civil).';
                event.target.querySelector('[name="last_name"]').focus();
                return;
            }
            // Tab 3 : contrat & salaire
            let contractStart = event.target.querySelector('[name="contract_start"]').value;
            if (!contractStart) {
                this.tab = 3;
                this.formError = 'Veuillez renseigner la date de début de contrat (onglet Contrat).';
                return;
            }
            if (!(parseInt(this.baseSalary) > 0)) {
                this.tab = 3;
                this.formError = 'Veuillez renseigner le salaire de base (onglet Contrat).';
                return;
            }
            // OK → soumettre
            event.target.submit();
        },
    };
}
</script>
<div x-data="createEmployeeForm()" class="max-w-6xl mx-auto">

{{-- ══ Bandeau SAGE ═══════════════════════════════════════════════════════════ --}}
<div class="bg-white border border-gray-300 rounded-[4px] mb-4">
    <div class="flex items-center justify-between px-3 py-1.5 bg-gradient-to-b from-gray-50 to-white">
        <h1 class="text-[15px] font-bold text-gray-900">
            Salarié : Création complète
            <span class="font-mono text-emerald-700 ml-1">{{ $nextMatricule }}</span>
        </h1>
        <div class="flex items-center gap-2">
            <button type="button" onclick="document.getElementById('employee-form').requestSubmit()"
                    class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('rh.employes.index') }}"
               class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('rh.employes.store') }}" id="employee-form"
      @submit.prevent="submitForm($event)" novalidate>
@csrf
<x-form-guard />

<div class="flex gap-5 items-start">

{{-- ══ Colonne principale ═══════════════════════════════════════════════════════ --}}
<div class="flex-1 min-w-0">

    {{-- ── Navigation onglets ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden mb-4">
        <div class="flex border-b border-gray-200">
            @php
            $tabDefs = [
                1 => ['icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',    'label' => 'État civil',                'sub' => 'Identité & famille'],
                2 => ['icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label' => 'Affectation',              'sub' => 'Poste & service'],
                3 => ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Contrat & Rémunération', 'sub' => 'Salaire & SMIG'],
                4 => ['icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',                              'label' => 'Coordonnées bancaires', 'sub' => 'Paiement du salaire'],
            ];
            @endphp
            @foreach($tabDefs as $n => $def)
            <button type="button"
                    @click="tab = {{ $n }}"
                    :class="tab === {{ $n }} ? 'border-b-2 border-emerald-600 text-emerald-800 bg-[#eef5f0]' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="flex-1 flex flex-col items-center gap-0.5 px-3 py-3 text-xs font-medium transition-colors relative">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $def['icon'] }}"/>
                </svg>
                <span class="hidden sm:block font-semibold">{{ $def['label'] }}</span>
                <span class="hidden lg:block text-xs font-normal opacity-70">{{ $def['sub'] }}</span>
                {{-- Numéro de badge --}}
                <span :class="tab === {{ $n }} ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500'"
                      class="absolute top-2 right-2 w-4 h-4 rounded-full text-[10px] font-bold flex items-center justify-center">{{ $n }}</span>
            </button>
            @endforeach
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET 1 — État civil                                                   --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 1" x-cloak class="space-y-4">

        {{-- Identité --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-600 rounded"></span> Identité
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Matricule</label>
                    <input type="text" value="{{ $nextMatricule }}" readonly
                           class="w-full border border-gray-200 rounded-[4px] px-3 py-2 text-sm bg-gray-50 text-gray-400 font-mono cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}"
                           x-model="lastName" required autocomplete="off"
                           placeholder="Ex : KABORÉ"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 uppercase"
                           style="text-transform:uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prénom(s) <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}"
                           x-model="firstName" required autocomplete="off"
                           placeholder="Ex : Adama"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sexe <span class="text-red-500">*</span></label>
                    <select name="gender" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-500 bg-white">
                        <option value="M" {{ old('gender','M') === 'M' ? 'selected' : '' }}>🧑 Masculin</option>
                        <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>👩 Féminin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date de naissance</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Lieu de naissance</label>
                    <input type="text" name="birth_place" value="{{ old('birth_place') }}"
                           placeholder="Ex : Ouagadougou"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nationalité</label>
                    <input type="text" name="nationality" value="{{ old('nationality', 'Burkinabè') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">N° CNIB / CIN</label>
                    <input type="text" name="cin_number" value="{{ old('cin_number') }}"
                           placeholder="Ex : B1234567"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">N° CNSS</label>
                    <input type="text" name="cnss_number" value="{{ old('cnss_number') }}"
                           placeholder="Ex : 12345678"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Niveau d'études</label>
                    <select name="education_level" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Non renseigné —</option>
                        @foreach(['Sans diplôme','CEP','BEPC','BAC','BTS / DUT','Licence','Master / DEA','Doctorat','Formation professionnelle'] as $niv)
                        <option value="{{ $niv }}" @selected(old('education_level') === $niv)>{{ $niv }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           placeholder="Ex : +226 70 00 00 00"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Email professionnel</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           placeholder="prenom.nom@entreprise.bf"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ville</label>
                    <input type="text" name="city" value="{{ old('city', 'Ouagadougou') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Adresse</label>
                    <input type="text" name="address" value="{{ old('address') }}"
                           placeholder="Secteur, quartier, rue..."
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        {{-- Situation familiale + NB_PARTS --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-500 rounded"></span>
                Situation familiale
                <span class="text-gray-400 font-normal normal-case">— Quotient IUTS</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Statut matrimonial <span class="text-red-500">*</span></label>
                    <select name="family_status" x-model="familyStatus"
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="celibataire" @selected(old('family_status','celibataire')==='celibataire')>Célibataire</option>
                        <option value="marie"       @selected(old('family_status')==='marie')>Marié(e)</option>
                        <option value="veuf"        @selected(old('family_status')==='veuf')>Veuf / Veuve</option>
                        <option value="divorce"     @selected(old('family_status')==='divorce')>Divorcé(e)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Enfants à charge <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="if(nbChildren>0) nbChildren--"
                                class="w-8 h-9 flex items-center justify-center border border-gray-300 rounded-[4px] text-gray-600 hover:bg-gray-100 text-[16px] font-bold">−</button>
                        <input type="number" name="nb_children" x-model="nbChildren"
                               min="0" max="20" step="1"
                               class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-center font-semibold focus:ring-1 focus:ring-emerald-500">
                        <button type="button" @click="if(nbChildren<20) nbChildren++"
                                class="w-8 h-9 flex items-center justify-center border border-gray-300 rounded-[4px] text-gray-600 hover:bg-gray-100 text-[16px] font-bold">+</button>
                    </div>
                </div>
                {{-- NB_PARTS calculé --}}
                <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-200 rounded-[4px] p-3 text-center">
                    <p class="text-xs text-emerald-600 font-medium mb-0.5">Quotient familial (NB_PARTS)</p>
                    <p class="text-[19px] font-bold text-emerald-700" x-text="nbParts"></p>
                    <p class="text-xs text-emerald-500 mt-0.5">
                        <span x-show="familyStatus === 'marie'">Marié + </span>
                        <span x-show="familyStatus !== 'marie'">Célibataire + </span>
                        <span x-text="Math.min(parseInt(nbChildren)||0, 6)"></span>
                        enfant(s) × 0.5
                    </p>
                </div>
            </div>
        </div>

        {{-- Contact d'urgence --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-orange-400 rounded"></span> Contact d'urgence
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nom du contact</label>
                    <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}"
                           placeholder="Nom et prénom"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Téléphone d'urgence</label>
                    <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}"
                           placeholder="+226 70 00 00 00"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" @click="tab = 2"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-[4px] text-sm font-medium transition-colors">
                Suivant : Affectation
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET 2 — Affectation                                                  --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 2" x-cloak class="space-y-4">

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-600 rounded"></span> Poste & Organisation
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Service / Département</label>
                    <select name="department_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucun —</option>
                        @foreach($departments as $dep)
                            <option value="{{ $dep->id }}" @selected(old('department_id') == $dep->id)>{{ $dep->name }}</option>
                        @endforeach
                    </select>
                    @if($departments->isEmpty())
                    <p class="text-xs text-amber-600 mt-1">
                        <a href="{{ route('rh.departments.index') }}" class="underline">Créer des départements</a>
                    </p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Intitulé du poste</label>
                    <input type="text" name="job_title" value="{{ old('job_title') }}"
                           placeholder="Ex : Comptable principal"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fonction</label>
                    <input type="text" name="fonction" value="{{ old('fonction') }}"
                           placeholder="Ex : Responsable comptabilité"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Catégorie professionnelle <span class="text-red-500">*</span></label>
                    <select name="category" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        @foreach([
                            'cadre'          => 'Cadre',
                            'agent_maitrise' => 'Agent de maîtrise',
                            'employe'        => 'Employé',
                            'ouvrier'        => 'Ouvrier',
                        ] as $v => $l)
                        <option value="{{ $v }}" @selected(old('category','employe') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date d'embauche</label>
                    <input type="date" name="hiring_date" value="{{ old('hiring_date', now()->toDateString()) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="flex items-end">
                    <div class="w-full bg-blue-50 border border-blue-100 rounded-[4px] p-3 text-center">
                        <p class="text-xs text-blue-500 font-medium">Ancienneté estimée</p>
                        <p class="text-sm font-bold text-blue-700 mt-0.5">À partir de ce jour</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-between">
            <button type="button" @click="tab = 1"
                    class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                État civil
            </button>
            <button type="button" @click="tab = 3"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-[4px] text-sm font-medium transition-colors">
                Suivant : Contrat & Rémunération
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET 3 — Contrat & Rémunération                                       --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 3" x-cloak class="space-y-4">

        {{-- Contrat --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-600 rounded"></span> Contrat de travail <span class="text-red-500 font-normal">*</span>
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                @foreach(['CDI' => ['CDI','Durée indéterminée','bg-blue-50 border-blue-300 text-blue-700'], 'CDD' => ['CDD','Durée déterminée','bg-amber-50 border-amber-300 text-amber-700'], 'stage' => ['Stage','Convention de stage','bg-green-50 border-green-300 text-green-700'], 'consultant' => ['Consultant','Prestation de service','bg-purple-50 border-purple-300 text-purple-700']] as $v => [$short, $long, $cls])
                <label class="relative flex flex-col items-center p-3 rounded-[4px] border-2 cursor-pointer transition-all
                    has-[:checked]:{{ $cls }} has-[:checked]:shadow-sm
                    border-gray-200 hover:border-gray-300">
                    <input type="radio" name="contract_type" value="{{ $v }}" x-model="contractType"
                           {{ old('contract_type','CDI') === $v ? 'checked' : '' }} class="sr-only">
                    <span class="text-base font-bold">{{ $short }}</span>
                    <span class="text-xs text-center opacity-75 mt-0.5">{{ $long }}</span>
                </label>
                @endforeach
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date de début <span class="text-red-500">*</span></label>
                    <input type="date" name="contract_start" value="{{ old('contract_start', now()->toDateString()) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Date de fin
                        <span class="text-gray-400 font-normal" x-show="contractType === 'CDI'">— N/A pour CDI</span>
                    </label>
                    <input type="date" name="contract_end" value="{{ old('contract_end') }}"
                           :disabled="contractType === 'CDI'"
                           :class="contractType === 'CDI' ? 'bg-gray-50 text-gray-400' : ''"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        {{-- Rémunération --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-600 rounded"></span> Rémunération mensuelle
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-start">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Salaire de base ({{ $payroll->currency_code }}) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" name="base_salary" x-model="baseSalary"
                               value="{{ old('base_salary') }}" min="0" step="1000"
                               placeholder="Ex : 250 000"
                               class="w-full border border-gray-300 rounded-[4px] pl-3 pr-14 py-2.5 text-sm font-mono text-right focus:ring-1 focus:ring-emerald-500 text-lg">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">{{ $payroll->currency_code }}</span>
                    </div>
                    {{-- Barre SMIG --}}
                    <div class="mt-2">
                        <div class="flex justify-between text-xs mb-1">
                            <span :class="{
                                'text-red-600 font-semibold': salaryStatus === 'danger',
                                'text-green-600': salaryStatus === 'ok' || salaryStatus === 'good',
                                'text-gray-400': salaryStatus === 'neutral'
                            }">
                                <span x-show="salaryStatus === 'danger'">⚠ En-dessous du SMIG</span>
                                <span x-show="salaryStatus === 'ok'">✓ Au-dessus du SMIG</span>
                                <span x-show="salaryStatus === 'good'">✓ Rémunération correcte</span>
                                <span x-show="salaryStatus === 'neutral'" x-text="'SMIG = ' + parseInt(smig).toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></span>
                            </span>
                            <span class="text-gray-400">SMIG : <strong x-text="parseInt(smig).toLocaleString('fr-FR')"></strong> {{ $payroll->currency_code }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 :class="{
                                     'bg-red-500': salaryStatus === 'danger',
                                     'bg-green-500': salaryStatus === 'ok',
                                     'bg-emerald-500': salaryStatus === 'good',
                                     'bg-gray-300': salaryStatus === 'neutral'
                                 }"
                                 :style="`width:${salaryRatio}%`"></div>
                        </div>
                    </div>
                </div>

                {{-- Aperçu charges --}}
                <div class="bg-gray-50 rounded-[4px] border border-gray-300 p-4">
                    <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-3">Aperçu des charges (estimé)</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Salaire brut</span>
                            <span class="font-mono font-semibold text-gray-800"
                                  x-text="parseInt(baseSalary||0).toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></span>
                        </div>
                        <div class="flex justify-between text-red-600">
                            <span x-text="'CNSS salarié (' + cnssEmpRate + '%)'"></span>
                            <span class="font-mono" x-text="'− ' + Math.round(Math.min(parseInt(baseSalary||0), cnssCeiling) * cnssEmpRate / 100).toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></span>
                        </div>
                        <div class="flex justify-between text-gray-500 border-t border-gray-200 pt-1.5 mt-1">
                            <span class="font-medium text-gray-700">Net imposable (approx.)</span>
                            <span class="font-mono font-semibold text-gray-800"
                                  x-text="Math.round(parseInt(baseSalary||0) - Math.min(parseInt(baseSalary||0), cnssCeiling) * cnssEmpRate / 100).toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></span>
                        </div>
                        <p class="text-gray-400 text-[10px] mt-1">* IUTS calculé à la validation du bulletin de paie selon le NB_PARTS = <span x-text="nbParts"></span></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Primes & Indemnités --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-600 rounded"></span> Primes & Indemnités
                <span class="ml-auto text-[10px] font-normal text-gray-400">Facultatif — modifiable après création</span>
            </h3>

            {{-- Ligne d'ajout --}}
            <div class="flex gap-2 mb-3">
                <select x-model="newTypeId"
                        class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
                    <option value="">— Choisir un type de prime —</option>
                    @foreach($allowanceTypes as $atype)
                    <option value="{{ $atype->id }}"
                        :disabled="allowances.find(a => a.type_id == {{ $atype->id }}) !== undefined">
                        {{ $atype->name }}{{ $atype->is_taxable ? '' : ' (non imposable)' }}
                    </option>
                    @endforeach
                </select>
                <div class="relative w-44">
                    <input type="number" x-model.number="newAmount" min="0" step="1000"
                           placeholder="Montant"
                           :disabled="isAutoComputed(newTypeId)"
                           :placeholder="isAutoComputed(newTypeId) ? 'Auto' : 'Montant'"
                           :class="isAutoComputed(newTypeId) ? 'bg-gray-50 text-gray-400 italic' : ''"
                           class="w-full border border-gray-300 rounded-[4px] pl-3 pr-16 py-2 text-sm text-right focus:ring-1 focus:ring-purple-500">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">
                        <span x-show="!isAutoComputed(newTypeId)">{{ $payroll->currency_code }}</span>
                        <span x-show="isAutoComputed(newTypeId)" class="text-purple-400">%×ans</span>
                    </span>
                </div>
                <button type="button" @click="addAllowance()"
                        :disabled="!newTypeId || (!isAutoComputed(newTypeId) && newAmount <= 0)"
                        class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 disabled:bg-gray-200 disabled:text-gray-400 text-white rounded-[4px] text-sm font-medium transition-colors">
                    + Ajouter
                </button>
            </div>

            {{-- Liste des primes ajoutées --}}
            <div x-show="allowances.length > 0" class="border border-gray-100 rounded-[4px] overflow-hidden mb-2">
                <table class="w-full text-sm">
                    <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
                        <tr>
                            <th class="text-left px-3 py-2">Prime / Indemnité</th>
                            <th class="text-right px-3 py-2">Montant mensuel</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, i) in allowances" :key="i">
                            <tr class="border-t border-gray-100">
                                {{-- Inputs cachés pour soumission --}}
                                <template x-if="true">
                                    <input type="hidden" :name="'allowances['+i+'][type_id]'" :value="row.type_id">
                                </template>
                                <template x-if="true">
                                    <input type="hidden" :name="'allowances['+i+'][amount]'" :value="row.amount">
                                </template>
                                <td class="px-3 py-2 text-gray-700" x-text="typeName(row.type_id)"></td>
                                <td class="px-3 py-2 text-right font-mono font-semibold"
                                    :class="isAutoComputed(row.type_id) ? 'text-purple-600 italic' : 'text-gray-800'"
                                    x-text="isAutoComputed(row.type_id) ? '⚙ Calculé auto (2 %/an)' : parseInt(row.amount).toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" @click="removeAllowance(i)"
                                            class="text-red-400 hover:text-red-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr class="border-t-2 border-gray-200 bg-gray-50" x-show="allowances.length > 1">
                            <td class="px-3 py-2 text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total primes</td>
                            <td class="px-3 py-2 text-right font-mono font-bold text-purple-700"
                                x-text="totalAllowances.toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-[10px] text-gray-400" x-show="allowances.length === 0">Aucune prime ajoutée — le salaire de base suffit pour créer l'employé.</p>
        </div>

        <div class="flex justify-between">
            <button type="button" @click="tab = 2"
                    class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Affectation
            </button>
            <button type="button" @click="tab = 4"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-[4px] text-sm font-medium transition-colors">
                Suivant : Coordonnées bancaires
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET 4 — Coordonnées bancaires                                        --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 4" x-cloak class="space-y-4">

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                <span class="w-5 h-0.5 bg-emerald-600 rounded"></span> Mode de paiement
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
                @foreach([
                    'virement' => ['🏦', 'Virement', 'bancaire'],
                    'especes'  => ['💵', 'Espèces', ''],
                    'cheque'   => ['📄', 'Chèque', ''],
                    'mobile'   => ['📱', 'Mobile', 'Money'],
                ] as $v => [$ic, $l, $sub])
                <label class="flex flex-col items-center p-3 rounded-[4px] border-2 cursor-pointer transition-all text-center
                    has-[:checked]:border-teal-400 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-800
                    border-gray-200 hover:border-gray-300 text-gray-600">
                    <input type="radio" name="payment_mode" value="{{ $v }}" x-model="paymentMode"
                           {{ old('payment_mode','virement') === $v ? 'checked' : '' }} class="sr-only">
                    <span class="text-xl mb-0.5">{{ $ic }}</span>
                    <span class="text-xs font-semibold">{{ $l }}</span>
                    @if($sub)<span class="text-[10px] opacity-60">{{ $sub }}</span>@endif
                </label>
                @endforeach
            </div>

            {{-- Champs bancaires (virement uniquement) --}}
            <div x-show="paymentMode === 'virement'" x-transition>
                <div class="bg-blue-50 border border-blue-100 rounded-[4px] px-3 py-2.5 mb-4 text-xs text-blue-700">
                    <strong>Format RIB :</strong> Code banque (2 car.) · Code guichet (5 car.) · N° compte (11 car.) · Clé RIB (2 car.)
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Code banque</label>
                        <input type="text" name="bank_code" value="{{ old('bank_code') }}" maxlength="5" placeholder="BF"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Code guichet</label>
                        <input type="text" name="bank_branch" value="{{ old('bank_branch') }}" maxlength="5" placeholder="01234"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">N° de compte</label>
                        <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}" maxlength="11" placeholder="00123456789"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Clé RIB</label>
                        <input type="text" name="bank_rib_key" value="{{ old('bank_rib_key') }}" maxlength="2" placeholder="97"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Banque / Établissement</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="Ex : ECOBANK BF"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">N° de compte complet (optionnel)</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account') }}" placeholder="Format libre"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>
            <div x-show="paymentMode !== 'virement'" x-transition class="text-center py-6 text-sm text-gray-400">
                <span x-show="paymentMode === 'especes'">💵 Paiement en espèces — aucune coordonnée bancaire requise.</span>
                <span x-show="paymentMode === 'cheque'">📄 Paiement par chèque — aucune coordonnée bancaire requise.</span>
                <span x-show="paymentMode === 'mobile'">📱 Paiement Mobile Money — aucune coordonnée bancaire requise.</span>
            </div>
        </div>

        <div class="flex justify-between">
            <button type="button" @click="tab = 3"
                    class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Contrat & Rémunération
            </button>
            {{-- Bouton final --}}
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-[4px] text-sm font-semibold transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Créer l'employé
            </button>
        </div>
    </div>

</div>{{-- /colonne principale --}}

{{-- ══ Panneau récapitulatif ══════════════════════════════════════════════════ --}}
<div class="w-64 flex-shrink-0 space-y-4 sticky top-4">

    {{-- Fiche résumé --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5" style="background:linear-gradient(135deg,#047857,#065f46)">
            <p class="text-xs font-semibold text-blue-100 uppercase tracking-wider">Fiche en cours</p>
            <p class="text-white font-bold text-sm mt-0.5" x-text="(lastName || '···') + ' ' + (firstName || '')"></p>
            <p class="text-blue-200 text-xs mt-0.5 font-mono">{{ $nextMatricule }}</p>
        </div>
        <div class="p-4 space-y-3 text-xs">
            <div class="flex justify-between items-center">
                <span class="text-gray-500">NB_PARTS (IUTS)</span>
                <span class="font-bold text-emerald-600 text-base" x-text="nbParts"></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-500">Salaire de base</span>
                <span class="font-semibold font-mono text-gray-800"
                      x-text="parseInt(baseSalary||0) > 0 ? parseInt(baseSalary).toLocaleString('fr-FR') + ' F' : '—'"></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-500">Type contrat</span>
                <span class="font-semibold text-gray-700" x-text="contractType"></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-500">Mode paiement</span>
                <span class="font-semibold text-gray-700 capitalize" x-text="paymentMode"></span>
            </div>
            <div class="border-t border-gray-100 pt-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-500" x-text="'CNSS patronal (' + cnssEmpRate_pat + '%)'"></span>
                    <span class="font-mono text-gray-600"
                          x-text="'+ ' + Math.round(Math.min(parseInt(baseSalary||0), cnssCeiling) * cnssEmpRate_pat / 100).toLocaleString('fr-FR') + ' F'"></span>
                </div>
                <div class="flex justify-between items-center mt-1">
                    <span class="text-gray-500 font-semibold">Coût total employeur</span>
                    <span class="font-mono font-bold text-gray-900"
                          x-text="(parseInt(baseSalary||0) + Math.round(Math.min(parseInt(baseSalary||0), cnssCeiling) * cnssEmpRate_pat / 100)).toLocaleString('fr-FR') + ' F'"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Progression onglets --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-3">Progression</p>
        <div class="space-y-2">
            @foreach([1 => 'État civil', 2 => 'Affectation', 3 => 'Contrat & Paie', 4 => 'Banque'] as $n => $lbl)
            <button type="button" @click="tab = {{ $n }}"
                    :class="tab === {{ $n }} ? 'bg-blue-50 border-blue-200 text-blue-700' : 'border-transparent text-gray-500 hover:bg-gray-50'"
                    class="w-full flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-[4px] border transition-colors text-left">
                <span :class="tab === {{ $n }} ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-500'"
                      class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] flex-shrink-0">{{ $n }}</span>
                {{ $lbl }}
                @if($n === 1)
                <svg x-show="lastName && firstName" class="w-3 h-3 text-green-500 ml-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                @endif
                @if($n === 3)
                <svg x-show="baseSalary > 0" class="w-3 h-3 text-green-500 ml-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- Rappel SMIG --}}
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-3 text-xs">
        <p class="font-semibold text-amber-700 mb-1">⚖ SMIG Burkina Faso</p>
        <p class="text-amber-600">Salaire minimum : <strong x-text="parseInt(smig).toLocaleString('fr-FR') + ' {{ $payroll->currency_code }}'"></strong>/mois</p>
        <p class="text-amber-500 mt-1">(Décret n°2024 — en vigueur)</p>
    </div>

    {{-- Message d'erreur de validation côté client --}}
    <div x-show="formError" x-cloak
         class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded-[4px] text-xs text-red-700"
         x-text="formError"></div>

    {{-- Bouton submit accessible depuis tous les onglets --}}
    <button type="submit"
            class="w-full flex items-center justify-center gap-2 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-[4px] text-sm font-semibold transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Créer l'employé
    </button>
</div>

</div>{{-- /flex wrapper --}}
</form>
</div>
@endsection
