@php $isEdit = isset($profile) && $profile->exists; @endphp

<div class="bg-white rounded-[4px] border border-gray-200 shadow-sm divide-y divide-gray-100">

    {{-- Identification --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Identification</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $profile->code) }}"
                       @if($isEdit) readonly @endif
                       placeholder="Ex: PROF-CADRE, PROF-EXEC"
                       class="w-full font-mono uppercase border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 {{ $isEdit ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">Majuscules, chiffres, tirets et underscores</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Catégorie d'employés <span class="text-red-500">*</span></label>
                <select name="categorie" class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    @foreach($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('categorie', $profile->categorie) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('categorie')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="libelle" value="{{ old('libelle', $profile->libelle) }}"
                   placeholder="Ex: Profil cadre supérieur, Profil employé standard"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            @error('libelle')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <textarea name="description" rows="2"
                      placeholder="Usage, particularités, conditions d'application…"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('description', $profile->description) }}</textarea>
        </div>
    </div>

    {{-- Plan source --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Plan de paie source</p>
        <p class="text-xs text-gray-400 mb-4">Les rubriques du plan seront héritées automatiquement. Vous pourrez ensuite en activer, désactiver ou surcharger certaines.</p>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Plan <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <select name="plan_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                <option value="">— Sans plan (rubriques ajoutées manuellement) —</option>
                @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected(old('plan_id', $profile->plan_id) == $plan->id)>
                    {{ $plan->libelle }} ({{ $plan->code }})
                </option>
                @endforeach
            </select>
            @error('plan_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Validité --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Validité & Options</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Valide à partir du</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $profile->valid_from?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jusqu'au <span class="text-gray-400 font-normal">(vide = illimité)</span></label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $profile->valid_until?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
        <div class="mt-4 flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="profil_is_active" value="1"
                       @checked(old('is_active', $profile->is_active ?? true))
                       class="w-4 h-4 text-emerald-700 border-gray-300 rounded focus:ring-emerald-500">
                <label for="profil_is_active" class="text-sm text-gray-700 font-medium">Profil actif</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" id="profil_is_default" value="1"
                       @checked(old('is_default', $profile->is_default ?? false))
                       class="w-4 h-4 text-emerald-700 border-gray-300 rounded focus:ring-emerald-500">
                <label for="profil_is_default" class="text-sm text-gray-700 font-medium">Profil par défaut</label>
                <span class="text-xs text-gray-400">(appliqué si aucun profil n'est choisi sur le contrat)</span>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    <div class="px-6 py-5">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes internes <span class="text-gray-400 font-normal">(optionnel)</span></label>
        <textarea name="notes" rows="2"
                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('notes', $profile->notes) }}</textarea>
    </div>
</div>
