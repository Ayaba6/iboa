@php $isEdit = isset($plan) && $plan->exists; @endphp

<div class="bg-white rounded-[4px] border border-gray-200 shadow-sm divide-y divide-gray-100">

    {{-- Identification --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Identification</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $plan->code) }}"
                       @if($isEdit) readonly @endif
                       placeholder="Ex: PL-BF-STD, PL-CI-CADRE"
                       class="w-full font-mono uppercase border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 {{ $isEdit ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">Lettres majuscules, chiffres et tirets</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Libellé <span class="text-red-500">*</span></label>
                <input type="text" name="libelle" value="{{ old('libelle', $plan->libelle) }}"
                       placeholder="Ex: Plan standard Burkina Faso"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                @error('libelle')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <textarea name="description" rows="2"
                      placeholder="Usage, type d'employés couverts, particularités…"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('description', $plan->description) }}</textarea>
        </div>
    </div>

    {{-- Localisation --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Localisation</p>
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Pays</label>
                <input type="text" name="pays" value="{{ old('pays', $plan->pays) }}"
                       placeholder="Ex: Burkina Faso"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Code ISO</label>
                <input type="text" name="country_code" value="{{ old('country_code', $plan->country_code) }}"
                       maxlength="5" placeholder="BF"
                       class="w-full font-mono uppercase border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Devise</label>
            <input type="text" name="devise" value="{{ old('devise', $plan->devise) }}"
                   placeholder="FCFA"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300 max-w-xs">
        </div>
    </div>

    {{-- Validité & Options --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Validité & Options</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Valide à partir du</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $plan->valid_from?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jusqu'au <span class="text-gray-400 font-normal">(vide = illimité)</span></label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $plan->valid_until?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
        <div class="mt-4 flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="plan_is_active" value="1"
                       @checked(old('is_active', $plan->is_active ?? true))
                       class="w-4 h-4 text-emerald-700 border-gray-300 rounded focus:ring-emerald-500">
                <label for="plan_is_active" class="text-sm text-gray-700 font-medium">Plan actif</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" id="plan_is_default" value="1"
                       @checked(old('is_default', $plan->is_default ?? false))
                       class="w-4 h-4 text-emerald-700 border-gray-300 rounded focus:ring-emerald-500">
                <label for="plan_is_default" class="text-sm text-gray-700 font-medium">Plan par défaut</label>
                <span class="text-xs text-gray-400">(utilisé si aucun plan n'est assigné à un employé)</span>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    <div class="px-6 py-5">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes internes <span class="text-gray-400 font-normal">(optionnel)</span></label>
        <textarea name="notes" rows="2"
                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('notes', $plan->notes) }}</textarea>
    </div>
</div>
