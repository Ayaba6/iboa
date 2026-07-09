@php $isEdit = isset($contribution) && $contribution->exists; @endphp

<div class="bg-white rounded-[4px] border border-gray-200 shadow-sm divide-y divide-gray-100">

    {{-- Identification --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Identification</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $contribution->code) }}"
                       @if($isEdit) readonly @endif
                       placeholder="Ex: CNSS_SAL, RETRAITE_SAL"
                       class="w-full font-mono uppercase border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 {{ $isEdit ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Organisme <span class="text-red-500">*</span></label>
                <select name="organisme" class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    <option value="cnss" @selected(old('organisme', $contribution->organisme) === 'cnss')>CNSS</option>
                    <option value="assurance" @selected(old('organisme', $contribution->organisme) === 'assurance')>Assurance</option>
                    <option value="retraite" @selected(old('organisme', $contribution->organisme) === 'retraite')>Retraite</option>
                    <option value="mutuelle" @selected(old('organisme', $contribution->organisme) === 'mutuelle')>Mutuelle</option>
                    <option value="autre" @selected(old('organisme', $contribution->organisme) === 'autre')>Autre</option>
                </select>
                @error('organisme')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="libelle" value="{{ old('libelle', $contribution->libelle) }}"
                   placeholder="Ex: Cotisation CNSS part salarié"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            @error('libelle')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Taux --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Taux de cotisation</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Taux salarié (%) <span class="text-red-500">*</span>
                    <span class="text-emerald-600 font-normal ml-1">— prélevé sur le bulletin</span>
                </label>
                <input type="number" name="taux_salarie" value="{{ old('taux_salarie', $contribution->taux_salarie) }}"
                       step="0.01" min="0" max="100" placeholder="5.50"
                       class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                @error('taux_salarie')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Taux employeur (%) <span class="text-red-500">*</span>
                    <span class="text-orange-500 font-normal ml-1">— charge de l'entreprise</span>
                </label>
                <input type="number" name="taux_employeur" value="{{ old('taux_employeur', $contribution->taux_employeur) }}"
                       step="0.01" min="0" max="100" placeholder="16.00"
                       class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                @error('taux_employeur')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- Base de cotisation --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Base de cotisation</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Assiette <span class="text-red-500">*</span></label>
                <select name="base_cotisable" id="base_cotisable"
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    <option value="salaire_brut" @selected(old('base_cotisable', $contribution->base_cotisable) === 'salaire_brut')>Salaire brut</option>
                    <option value="salaire_base" @selected(old('base_cotisable', $contribution->base_cotisable) === 'salaire_base')>Salaire de base</option>
                    <option value="plafonne" @selected(old('base_cotisable', $contribution->base_cotisable) === 'plafonne')>Plafonné</option>
                    <option value="custom" @selected(old('base_cotisable', $contribution->base_cotisable) === 'custom')>Personnalisé (référence rubrique)</option>
                </select>
                @error('base_cotisable')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Plafond mensuel (FCFA)</label>
                <input type="number" name="plafond" value="{{ old('plafond', $contribution->plafond) }}"
                       min="0" placeholder="650000"
                       class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                <p class="text-xs text-gray-400 mt-1">Requis si assiette = Plafonné</p>
                @error('plafond')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Référence rubrique (base custom)</label>
            <input type="text" name="base_ref" value="{{ old('base_ref', $contribution->base_ref) }}"
                   placeholder="Ex: SALAIRE_BASE"
                   class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            @error('base_ref')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Comptabilité --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Comptes comptables <span class="text-gray-300 font-normal normal-case">(optionnel)</span></p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Compte salarié</label>
                <input type="text" name="account_salarie" value="{{ old('account_salarie', $contribution->account_salarie) }}"
                       placeholder="Ex: 431000"
                       class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Compte employeur</label>
                <input type="text" name="account_employeur" value="{{ old('account_employeur', $contribution->account_employeur) }}"
                       placeholder="Ex: 431100"
                       class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
    </div>

    {{-- Validité --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Période de validité</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Valide à partir du</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $contribution->valid_from?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jusqu'au <span class="text-gray-400 font-normal">(vide = illimité)</span></label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $contribution->valid_until?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active_cot" value="1"
                   @checked(old('is_active', $contribution->is_active ?? true))
                   class="w-4 h-4 text-emerald-700 border-gray-300 rounded focus:ring-emerald-500">
            <label for="is_active_cot" class="text-sm text-gray-700 font-medium">Cotisation active</label>
        </div>
    </div>

    {{-- Notes --}}
    <div class="px-6 py-5">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes <span class="text-gray-400 font-normal">(optionnel)</span></label>
        <textarea name="notes" rows="2"
                  placeholder="Référence légale, conditions particulières…"
                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('notes', $contribution->notes) }}</textarea>
    </div>
</div>
