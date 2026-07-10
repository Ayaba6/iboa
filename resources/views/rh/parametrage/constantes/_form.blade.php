@php $isEdit = isset($constant) && $constant->exists; @endphp

<div class="bg-white rounded-[4px] border border-gray-200 shadow-sm divide-y divide-gray-100">

    {{-- Identification --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Identification</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $constant->code) }}"
                       @if($isEdit) readonly @endif
                       placeholder="Ex: SMIG, CNSS_SAL_TAUX"
                       class="w-full font-mono uppercase border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 {{ $isEdit ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">Lettres majuscules et underscores uniquement</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Groupe <span class="text-red-500">*</span></label>
                <select name="groupe" class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    @foreach($groupes as $key => $label)
                    <option value="{{ $key }}" @selected(old('groupe', $constant->groupe) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="libelle" value="{{ old('libelle', $constant->libelle) }}"
                   placeholder="Ex: Salaire minimum interprofessionnel garanti"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            @error('libelle')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Valeur --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Valeur</p>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Type de valeur <span class="text-red-500">*</span></label>
                <select name="value_type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    @foreach($valueTypes as $key => $label)
                    <option value="{{ $key }}" @selected(old('value_type', $constant->value_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Valeur <span class="text-red-500">*</span></label>
                <input type="text" name="value_raw" value="{{ old('value_raw', $constant->value_raw) }}"
                       placeholder="Ex: 45000"
                       class="w-full font-mono border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                @error('value_raw')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Unité</label>
                <input type="text" name="unit" value="{{ old('unit', $constant->unit) }}"
                       placeholder="FCFA, %, jours, h"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
    </div>

    {{-- Validité --}}
    <div class="px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Période de validité</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Valide à partir du</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $constant->valid_from?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jusqu'au <span class="text-gray-400 font-normal">(vide = illimité)</span></label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $constant->valid_until?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   @checked(old('is_active', $constant->is_active ?? true))
                   class="w-4 h-4 text-emerald-700 border-gray-300 rounded focus:ring-emerald-500">
            <label for="is_active" class="text-sm text-gray-700 font-medium">Constante active</label>
        </div>
    </div>

    {{-- Description --}}
    <div class="px-6 py-5">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(optionnel)</span></label>
        <textarea name="description" rows="2"
                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('description', $constant->description) }}</textarea>
    </div>
</div>
