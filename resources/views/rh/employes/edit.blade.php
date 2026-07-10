@extends('layouts.erp')
@section('title', 'Modifier – '.$employe->full_name)
@section('breadcrumb')
    <a href="{{ route('rh.employes.index') }}" class="hover:text-gray-700">Employés</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.employes.show', $employe) }}" class="hover:text-gray-700">{{ $employe->full_name }}</a>
    <span class="mx-1">/</span><span>Modifier</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto">
<h1 class="text-[16px] font-bold text-gray-900 mb-6">Modifier — {{ $employe->full_name }}</h1>

{{-- Les erreurs de validation sont affichées par la bannière globale du layout. --}}
<form method="POST" action="{{ route('rh.employes.update', $employe) }}">
@csrf @method('PUT')
<x-form-guard :model="$employe" />

<div class="space-y-3">

{{-- ─── Identité ────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 p-6">
    <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        Identité
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Matricule</label>
            <input type="text" value="{{ $employe->matricule }}" readonly
                   class="w-full border border-gray-200 rounded-[4px] px-3 py-2 text-sm bg-gray-50 text-gray-500 font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium {{ $errors->has('last_name') ? 'text-red-700' : 'text-gray-700' }} mb-1">
                Nom <span class="text-red-500">*</span>
            </label>
            <input type="text" name="last_name" value="{{ old('last_name', $employe->last_name) }}" required
                   @if($errors->has('last_name')) aria-invalid="true" @endif
                   class="w-full border rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 {{ $errors->has('last_name') ? 'border-red-400 bg-red-50 focus:ring-red-400' : 'border-gray-300' }}">
            @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium {{ $errors->has('first_name') ? 'text-red-700' : 'text-gray-700' }} mb-1">
                Prénom <span class="text-red-500">*</span>
            </label>
            <input type="text" name="first_name" value="{{ old('first_name', $employe->first_name) }}" required
                   @if($errors->has('first_name')) aria-invalid="true" @endif
                   class="w-full border rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 {{ $errors->has('first_name') ? 'border-red-400 bg-red-50 focus:ring-red-400' : 'border-gray-300' }}">
            @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sexe</label>
            <select name="gender" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="M" @selected(old('gender',$employe->gender)==='M')>Masculin</option>
                <option value="F" @selected(old('gender',$employe->gender)==='F')>Féminin</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
            <input type="date" name="birth_date" value="{{ old('birth_date', $employe->birth_date?->toDateString()) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lieu de naissance</label>
            <input type="text" name="birth_place" value="{{ old('birth_place', $employe->birth_place) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nationalité</label>
            <input type="text" name="nationality" value="{{ old('nationality', $employe->nationality) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">N° CIN / CNIB</label>
            <input type="text" name="cin_number" value="{{ old('cin_number', $employe->cin_number) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">N° CNSS</label>
            <input type="text" name="cnss_number" value="{{ old('cnss_number', $employe->cnss_number) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
            <input type="text" name="phone" value="{{ old('phone', $employe->phone) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $employe->email) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
            <input type="text" name="city" value="{{ old('city', $employe->city) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
            <input type="text" name="address" value="{{ old('address', $employe->address) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
    </div>
</div>

{{-- ─── Poste & Statut ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 p-6">
    <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        Poste & Statut
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Département</label>
            <select name="department_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">— Aucun —</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected(old('department_id',$employe->department_id)==$d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Intitulé du poste</label>
            <input type="text" name="job_title" value="{{ old('job_title', $employe->job_title) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <select name="category" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                @foreach(['cadre'=>'Cadre','agent_maitrise'=>'Agent de maîtrise','employe'=>'Employé','ouvrier'=>'Ouvrier'] as $v=>$l)
                    <option value="{{ $v }}" @selected(old('category',$employe->category)===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
            <select name="status" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                @foreach(['actif'=>'Actif','suspendu'=>'Suspendu','licencie'=>'Licencié','demissionne'=>'Démissionné'] as $v=>$l)
                    <option value="{{ $v }}" @selected(old('status',$employe->status)===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date d'embauche</label>
            <input type="date" name="hiring_date" value="{{ old('hiring_date', $employe->hiring_date?->toDateString()) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de sortie</label>
            <input type="date" name="leave_date" value="{{ old('leave_date', $employe->leave_date?->toDateString()) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
    </div>
</div>

{{-- ─── Situation familiale ─────────────────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 p-6">
    <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Situation familiale
        <span class="text-xs font-normal text-gray-400 normal-case">(quotient familial IUTS)</span>
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut familial</label>
            <select name="family_status" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                @foreach(['celibataire'=>'Célibataire','marie'=>'Marié(e)','veuf'=>'Veuf / Veuve','divorce'=>'Divorcé(e)'] as $v=>$l)
                    <option value="{{ $v }}" @selected(old('family_status',$employe->family_status)===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre d'enfants à charge</label>
            <input type="number" name="nb_children" value="{{ old('nb_children', $employe->nb_children) }}" min="0" max="20" step="1"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-center focus:ring-1 focus:ring-emerald-500">
        </div>
    </div>
</div>

{{-- ─── Coordonnées bancaires ──────────────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 p-6">
    <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
        </svg>
        Coordonnées bancaires
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
            <select name="payment_mode" id="edit_payment_mode"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                    onchange="document.getElementById('edit-bank-fields').style.display = this.value === 'virement' ? '' : 'none'">
                <option value="virement" @selected(old('payment_mode',$employe->payment_mode??'virement')==='virement')>Virement bancaire</option>
                <option value="especes"  @selected(old('payment_mode',$employe->payment_mode)==='especes')>Espèces</option>
                <option value="cheque"   @selected(old('payment_mode',$employe->payment_mode)==='cheque')>Chèque</option>
                <option value="mobile"   @selected(old('payment_mode',$employe->payment_mode)==='mobile')>Mobile Money</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Banque / Établissement</label>
            <input type="text" name="bank_name" value="{{ old('bank_name', $employe->bank_name) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
    </div>

    <div id="edit-bank-fields" style="{{ ($employe->payment_mode ?? 'virement') !== 'virement' && !old('payment_mode') ? 'display:none' : '' }}">
        <p class="text-xs text-gray-500 mb-3 bg-blue-50 border border-blue-100 rounded-[4px] px-3 py-2">
            <strong>RIB :</strong> Code banque (2) · Code guichet (5) · N° compte (11) · Clé (2)
            @if($employe->rib_formate)
            &nbsp;— <span class="font-mono text-blue-700">{{ $employe->rib_formate }}</span>
            @endif
        </p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Code banque</label>
                <input type="text" name="bank_code" value="{{ old('bank_code', $employe->bank_code) }}"
                       maxlength="5"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500"
                       placeholder="BF">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Code guichet</label>
                <input type="text" name="bank_branch" value="{{ old('bank_branch', $employe->bank_branch) }}"
                       maxlength="5"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500"
                       placeholder="01234">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Numéro de compte</label>
                <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $employe->bank_account_number) }}"
                       maxlength="11"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500"
                       placeholder="00123456789">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Clé RIB</label>
                <input type="text" name="bank_rib_key" value="{{ old('bank_rib_key', $employe->bank_rib_key) }}"
                       maxlength="2"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500"
                       placeholder="97">
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">N° de compte complet (format libre)</label>
            <input type="text" name="bank_account" value="{{ old('bank_account', $employe->bank_account) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500"
                   placeholder="BF99 xxxx xxxx xxxx">
        </div>
    </div>
</div>

{{-- ─── Accès Portail Employé ──────────────────────────────────────────── --}}
<div class="bg-white rounded-[4px] border border-gray-300 p-6">
    <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-1 flex items-center gap-2">
        <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Accès portail employé
    </h2>
    <p class="text-xs text-gray-400 mb-4">
        Liez ce dossier employé à un compte utilisateur pour qu'il accède à son espace RH (bulletins, congés, documents).
    </p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Compte utilisateur lié</label>
            <select name="user_id"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">— Aucun (portail désactivé) —</option>
                @foreach($users as $u)
                    @php $selected = old('user_id', $employe->user_id) == $u->id; @endphp
                    <option value="{{ $u->id }}" @selected($selected)>
                        {{ $u->name }} — {{ $u->email }}
                    </option>
                @endforeach
            </select>
            @error('user_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end">
            @if($employe->user_id)
            <div class="flex items-center gap-2 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-[4px] text-xs text-emerald-700">
                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Portail actif — compte lié
            </div>
            @else
            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-[4px] text-xs text-gray-500">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                Portail désactivé — aucun compte lié
            </div>
            @endif
        </div>
    </div>
</div>

</div>{{-- end space-y --}}

<div class="flex justify-end gap-3 mt-6">
    <a href="{{ route('rh.employes.show', $employe) }}"
       class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">
        Annuler
    </a>
    <button type="submit"
            class="px-6 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 focus:ring-1 focus:ring-emerald-500 focus:ring-offset-2">
        Enregistrer les modifications
    </button>
</div>
</form>
</div>
@endsection
