@extends('layouts.erp')
@section('title', $employe->full_name)

@section('breadcrumb')
    <a href="{{ route('rh.employes.index') }}" class="hover:text-gray-700">Employés</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $employe->full_name }}</span>
@endsection

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">{{ $employe->full_name }}</h1>
        <p class="text-sm text-gray-500 mt-1">Matricule <span class="font-mono">{{ $employe->matricule }}</span> · {{ $employe->category_label }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if($employe->user_id)
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-[3px] text-[11px] font-medium">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            Portail actif
        </span>
        @else
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 text-gray-400 rounded-[3px] text-[11px] font-medium">
            Sans portail
        </span>
        @endif
        <a href="{{ route('rh.employes.edit', $employe) }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
            Modifier
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

{{-- Colonne gauche : infos --}}
<div class="lg:col-span-2 space-y-3">

    {{-- Identité --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">Identité</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-gray-400">Sexe</dt><dd>{{ $employe->gender === 'M' ? 'Masculin' : 'Féminin' }}</dd></div>
            <div><dt class="text-gray-400">Date de naissance</dt><dd>{{ $employe->birth_date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Nationalité</dt><dd>{{ $employe->nationality ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">N° CIN</dt><dd class="font-mono">{{ $employe->cin_number ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">N° CNSS</dt><dd class="font-mono">{{ $employe->cnss_number ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Téléphone</dt><dd>{{ $employe->phone ?? '—' }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-400">Email</dt><dd>{{ $employe->email ?? '—' }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-400">Adresse</dt><dd>{{ $employe->address ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Poste --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">Poste & Emploi</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-gray-400">Département</dt><dd>{{ $employe->department?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Intitulé poste</dt><dd>{{ $employe->job_title ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Catégorie</dt><dd>{{ $employe->category_label }}</dd></div>
            <div><dt class="text-gray-400">Date d'embauche</dt><dd>{{ $employe->hiring_date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Situation familiale</dt>
                <dd>{{ ['celibataire'=>'Célibataire','marie'=>'Marié(e)','veuf'=>'Veuf/Veuve','divorce'=>'Divorcé(e)'][$employe->family_status] }}</dd>
            </div>
            <div><dt class="text-gray-400">Enfants à charge</dt><dd>{{ $employe->nb_children }}</dd></div>
            <div><dt class="text-gray-400">Parts fiscales IUTS</dt><dd class="font-semibold">{{ number_format($employe->nb_parts, 1) }} parts</dd></div>
            <div><dt class="text-gray-400">Banque / Compte</dt>
                <dd class="font-mono text-xs">{{ $employe->bank_name ? $employe->bank_name . ' — ' . $employe->bank_account : '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Contrats --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Contrats</h2>
            <button onclick="document.getElementById('modal-contract').classList.remove('hidden')"
                    class="text-xs text-blue-600 hover:underline">+ Nouveau contrat</button>
        </div>
        <div class="space-y-3">
            @forelse($employe->contracts as $contract)
            <div class="flex items-center justify-between p-3 rounded-[4px] border {{ $contract->status === 'actif' ? 'border-green-200 bg-green-50' : 'border-gray-100 bg-gray-50' }}">
                <div>
                    <span class="font-medium text-sm">{{ $contract->type }}</span>
                    <span class="text-xs text-gray-500 ml-2">Du {{ $contract->start_date->format('d/m/Y') }}{{ $contract->end_date ? ' au ' . $contract->end_date->format('d/m/Y') : '' }}</span>
                </div>
                <div class="text-right">
                    <div class="font-mono font-semibold text-sm">{{ number_format($contract->base_salary, 0, ',', ' ') }} FCFA</div>
                    <span class="text-xs {{ $contract->status === 'actif' ? 'text-green-600' : 'text-gray-400' }}">{{ ucfirst($contract->status) }}</span>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400">Aucun contrat.</p>
            @endforelse
        </div>
    </div>

    {{-- Primes / Indemnités --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Primes & Indemnités</h2>
            <button onclick="document.getElementById('modal-allowance').classList.remove('hidden')"
                    class="text-xs text-blue-600 hover:underline">+ Ajouter</button>
        </div>
        @if($employe->allowances->isNotEmpty())
        <table class="w-full text-sm">
            <thead><tr class="text-xs text-gray-400 border-b">
                <th class="pb-2 text-left">Type</th>
                <th class="pb-2 text-center">Imposable</th>
                <th class="pb-2 text-right">Montant</th>
                <th class="pb-2"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @foreach($employe->allowances as $allowance)
            <tr>
                <td class="py-2 font-medium">{{ $allowance->type?->name }}</td>
                <td class="py-2 text-center text-xs">{{ $allowance->type?->is_taxable ? '✓ IUTS' : '— Exo.' }}</td>
                <td class="py-2 text-right font-mono">{{ number_format($allowance->amount, 0, ',', ' ') }} F</td>
                <td class="py-2 text-right">
                    <form method="POST" action="{{ route('rh.employes.allowances.destroy', [$employe, $allowance]) }}"
                          onsubmit="return confirm('Supprimer cette prime ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">✕</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-gray-400">Aucune prime enregistrée.</p>
        @endif
    </div>

    {{-- Documents --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Documents</h2>
            <button onclick="document.getElementById('modal-document').classList.remove('hidden')"
                    class="text-xs text-blue-600 hover:underline">+ Ajouter</button>
        </div>

        @if(session('success'))
            <div class="mb-3 p-3 bg-emerald-50 border border-emerald-200 rounded-[4px] text-emerald-700 text-xs">
                {{ session('success') }}
            </div>
        @endif

        @if($employe->documents->isNotEmpty())
        <div class="space-y-2">
            @foreach($employe->documents as $doc)
            @php
                $docIcons = [
                    'cnib'=>'🪪','passeport'=>'📘','contrat'=>'📝','avenant'=>'📋',
                    'diplome'=>'🎓','attestation'=>'📄','medical'=>'🏥','cnss'=>'🏛',
                    'photo'=>'🖼','autre'=>'📎',
                ];
                $docLabels = [
                    'cnib'=>'CNIB','passeport'=>'Passeport','contrat'=>'Contrat',
                    'avenant'=>'Avenant','diplome'=>'Diplôme','attestation'=>'Attestation',
                    'medical'=>'Médical','cnss'=>'CNSS','photo'=>'Photo','autre'=>'Autre',
                ];
                $isExpired = $doc->expires_at && $doc->expires_at->isPast();
                $expiringSoon = $doc->expires_at && !$isExpired && $doc->expires_at->diffInDays(now()) <= 30;
            @endphp
            <div class="flex items-center justify-between p-3 rounded-[4px] border
                {{ $isExpired ? 'border-red-200 bg-red-50' : ($expiringSoon ? 'border-amber-200 bg-amber-50' : 'border-gray-100 bg-gray-50') }}">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-lg flex-shrink-0">{{ $docIcons[$doc->document_type] ?? '📎' }}</span>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-800 truncate">{{ $doc->label }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $docLabels[$doc->document_type] ?? $doc->document_type }}
                            @if($doc->document_date) · {{ $doc->document_date->format('d/m/Y') }}@endif
                            @if($doc->expires_at)
                                · <span class="{{ $isExpired ? 'text-red-600 font-semibold' : ($expiringSoon ? 'text-amber-600 font-semibold' : '') }}">
                                    Exp. {{ $doc->expires_at->format('d/m/Y') }}
                                    @if($isExpired) ⚠ Expiré @elseif($expiringSoon) ⚡ Bientôt @endif
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                    <a href="{{ route('rh.employes.documents.download', [$employe, $doc]) }}"
                       class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">
                        Télécharger
                    </a>
                    <form method="POST" action="{{ route('rh.employes.documents.destroy', [$employe, $doc]) }}"
                          onsubmit="return confirm('Supprimer ce document ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">✕</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400">Aucun document enregistré.</p>
        @endif
    </div>

</div>

{{-- Colonne droite : photo + statut + simulation --}}
<div class="space-y-3">

    {{-- Photo de profil --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 text-center">
        <div class="flex flex-col items-center gap-3">
            @if($employe->photo_path)
                <img src="{{ route('rh.employes.photo', $employe) }}"
                     alt="Photo {{ $employe->full_name }}"
                     class="w-24 h-24 rounded-full object-cover border-2 border-gray-200 shadow-sm">
            @else
                <div class="w-24 h-24 rounded-full bg-emerald-100 flex items-center justify-center border-2 border-gray-200">
                    <span class="text-[19px] font-bold text-emerald-500">
                        {{ strtoupper(substr($employe->last_name, 0, 1)) }}
                    </span>
                </div>
            @endif
            <form method="POST" action="{{ route('rh.employes.photo.update', $employe) }}"
                  enctype="multipart/form-data" class="w-full">
                @csrf
                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-[4px] text-xs text-gray-600 hover:bg-gray-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Changer la photo
                    <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.form.submit()">
                </label>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Statut</h2>
        @php $color = $employe->status_color @endphp
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
            {{ $employe->status_label }}
        </span>
        @if($employe->activeContract)
        <div class="mt-4 pt-4 border-t border-gray-100 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Salaire de base</span>
                <span class="font-mono font-semibold">{{ number_format($employe->activeContract->base_salary, 0, ',', ' ') }} F</span>
            </div>
            @php
                $base    = $employe->activeContract->base_salary;
                $cnssBase = min($base, $payroll->cnss_ceiling);
                $cnssEmp  = round($cnssBase * $payroll->cnss_employee_rate / 100);
                $imposable = $base - $cnssEmp;
                $parts = $employe->nb_parts;
            @endphp
            <div class="flex justify-between text-red-600">
                <span>CNSS ({{ $payroll->cnss_employee_rate }}%)</span>
                <span class="font-mono">- {{ number_format($cnssEmp, 0, ',', ' ') }} F</span>
            </div>
            <div class="flex justify-between text-blue-600 border-t pt-2">
                <span>Base IUTS</span>
                <span class="font-mono">{{ number_format($imposable, 0, ',', ' ') }} F</span>
            </div>
            <div class="text-xs text-gray-400">Parts fiscales : {{ number_format($parts, 1) }}</div>
        </div>
        @endif
    </div>

    <a href="{{ route('rh.paie.index') }}"
       class="block text-center px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700">
        Voir les bulletins de paie
    </a>
</div>

</div>

{{-- Modal : Nouveau contrat --}}
<div id="modal-contract" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nouveau contrat</h3>
        <form method="POST" action="{{ route('rh.employes.contracts.store', $employe) }}">
            @csrf
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                            @foreach(['CDI'=>'CDI','CDD'=>'CDD','stage'=>'Stage','consultant'=>'Consultant'] as $v=>$l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salaire (FCFA)</label>
                        <input type="number" name="base_salary" min="0" step="1000" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date fin (CDD)</label>
                        <input type="date" name="end_date" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-contract').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Ajouter document --}}
<div id="modal-document" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Ajouter un document</h3>
        <form method="POST" action="{{ route('rh.employes.documents.store', $employe) }}" enctype="multipart/form-data">
            @csrf
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type de document</label>
                        <select name="document_type" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                            @foreach(['cnib'=>'CNIB','passeport'=>'Passeport','contrat'=>'Contrat','avenant'=>'Avenant','diplome'=>'Diplôme','attestation'=>'Attestation','medical'=>'Médical','cnss'=>'CNSS','photo'=>'Photo','autre'=>'Autre'] as $v=>$l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Libellé</label>
                        <input type="text" name="label" required maxlength="200"
                               placeholder="ex: CNIB 2024"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date du document</label>
                        <input type="date" name="document_date"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration</label>
                        <input type="date" name="expires_at"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fichier <span class="text-xs text-gray-400">(PDF, image, Word, Excel — max 10 Mo)</span></label>
                    <input type="file" name="document_file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
                    <input type="text" name="notes" maxlength="500"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-document').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Ajouter prime --}}
<div id="modal-allowance" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Ajouter une prime / indemnité</h3>
        <form method="POST" action="{{ route('rh.employes.allowances.store', $employe) }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de prime</label>
                    <select name="payroll_allowance_type_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        @foreach($allowanceTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->is_taxable ? 'imposable' : 'exonérée' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA)</label>
                        <input type="number" name="amount" min="0" step="100" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dès le</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jusqu'au</label>
                        <input type="date" name="end_date" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-allowance').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">Ajouter</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
