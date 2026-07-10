{{-- Partial partagé entre create et edit --}}
<div x-data="rubricForm()" class="space-y-3">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Code --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" value="{{ old('code', $rubric->code) }}"
                   maxlength="30" placeholder="ex: PRIME_RESP" pattern="[A-Za-z0-9_\-]+"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase @error('code') border-red-400 @enderror"
                   {{ isset($rubric->id) ? 'readonly' : '' }}>
            @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            @if(isset($rubric->id))
                <p class="text-xs text-gray-400 mt-1">Le code ne peut pas être modifié après création.</p>
            @endif
        </div>

        {{-- Libellé --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="libelle" value="{{ old('libelle', $rubric->libelle) }}"
                   maxlength="150" placeholder="ex: Prime de responsabilité"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm @error('libelle') border-red-400 @enderror">
            @error('libelle')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Type --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
            <select name="type" x-model="type"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm @error('type') border-red-400 @enderror">
                <option value="gain"           @selected(old('type', $rubric->type) === 'gain')>Gain</option>
                <option value="retenue"        @selected(old('type', $rubric->type) === 'retenue')>Retenue</option>
                <option value="cotisation_pat" @selected(old('type', $rubric->type) === 'cotisation_pat')>Cotisation patronale</option>
                <option value="information"    @selected(old('type', $rubric->type) === 'information')>Information / Total</option>
            </select>
        </div>

        {{-- Mode de calcul --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mode de calcul <span class="text-red-500">*</span></label>
            <select name="calc_type" x-model="calcType"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm @error('calc_type') border-red-400 @enderror">
                <option value="manuel"  @selected(old('calc_type', $rubric->calc_type) === 'manuel')>Saisie manuelle</option>
                <option value="taux"    @selected(old('calc_type', $rubric->calc_type) === 'taux')>Taux (base × %)</option>
                <option value="fixe"    @selected(old('calc_type', $rubric->calc_type) === 'fixe')>Montant fixe</option>
                <option value="formule" @selected(old('calc_type', $rubric->calc_type) === 'formule')>Formule PHP</option>
            </select>
        </div>

        {{-- Base de référence (visible si taux) --}}
        <div x-show="calcType === 'taux'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1">Base de référence</label>
            <select name="base_ref"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                <option value="salaire_base" @selected(old('base_ref', $rubric->base_ref) === 'salaire_base')>Salaire de base</option>
                <option value="salaire_brut" @selected(old('base_ref', $rubric->base_ref) === 'salaire_brut')>Salaire brut</option>
                <option value="cnss_base"    @selected(old('base_ref', $rubric->base_ref) === 'cnss_base')>Base CNSS</option>
                <option value="imposable"    @selected(old('base_ref', $rubric->base_ref) === 'imposable')>Salaire imposable</option>
            </select>
        </div>

        {{-- Taux % (visible si taux) --}}
        <div x-show="calcType === 'taux'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1">Taux (%)</label>
            <div class="relative">
                <input type="number" name="rate" step="0.01" min="0" max="10000"
                       value="{{ old('rate', $rubric->rate) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm pr-8 @error('rate') border-red-400 @enderror">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
            </div>
            @error('rate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Montant fixe (visible si fixe) --}}
        <div x-show="calcType === 'fixe'" x-cloak class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Montant fixe (FCFA)</label>
            <input type="number" name="fixed_amount" min="0"
                   value="{{ old('fixed_amount', $rubric->fixed_amount) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm @error('fixed_amount') border-red-400 @enderror">
            @error('fixed_amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Formule (visible si formule) --}}
        <div x-show="calcType === 'formule'" x-cloak class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Formule PHP</label>
            <input type="text" name="formula"
                   value="{{ old('formula', $rubric->formula) }}"
                   placeholder="ex: $salaire_brut * 0.05 ou 0 (calculé par le moteur)"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono @error('formula') border-red-400 @enderror">
            <p class="text-xs text-gray-400 mt-1">
                Variables disponibles : <code>$salaire_base</code>, <code>$salaire_brut</code>,
                <code>$cnss_base</code>, <code>$imposable</code>. Mettre <code>0</code> si calculé par le moteur.
            </p>
            @error('formula')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Ordre d'affichage --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ordre d'affichage</label>
            <input type="number" name="display_order" min="0" max="9999"
                   value="{{ old('display_order', $rubric->display_order ?? 50) }}"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        </div>

        {{-- Description --}}
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="2" maxlength="500"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none"
                      placeholder="Description courte (optionnel)">{{ old('description', $rubric->description) }}</textarea>
        </div>
    </div>

    {{-- Checkboxes --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_taxable" value="0">
            <input type="checkbox" name="is_taxable" value="1"
                   class="rounded border-gray-300 text-emerald-700"
                   {{ old('is_taxable', $rubric->is_taxable) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Imposable (IUTS)</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_cnss_base" value="0">
            <input type="checkbox" name="is_cnss_base" value="1"
                   class="rounded border-gray-300 text-emerald-700"
                   {{ old('is_cnss_base', $rubric->is_cnss_base) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Base CNSS</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_in_brut" value="0">
            <input type="checkbox" name="is_in_brut" value="1"
                   class="rounded border-gray-300 text-emerald-700"
                   {{ old('is_in_brut', $rubric->is_in_brut) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Inclus dans le brut</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="show_on_bulletin" value="0">
            <input type="checkbox" name="show_on_bulletin" value="1"
                   class="rounded border-gray-300 text-emerald-700"
                   {{ old('show_on_bulletin', $rubric->show_on_bulletin ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Afficher sur bulletin</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   class="rounded border-gray-300 text-emerald-700"
                   {{ old('is_active', $rubric->is_active ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Rubrique active</span>
        </label>
    </div>
</div>

@push('scripts')
<script>
function rubricForm() {
    return {
        calcType: '{{ old('calc_type', $rubric->calc_type ?? 'manuel') }}',
        type:     '{{ old('type', $rubric->type ?? 'gain') }}',
    }
}
</script>
@endpush
