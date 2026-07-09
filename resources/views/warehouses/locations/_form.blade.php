<div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-3">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" value="{{ old('code', $location->code ?? '') }}" required maxlength="30"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500"
                   placeholder="A1-R2-N3">
            <p class="mt-1 text-xs text-gray-400">Identifiant unique dans l'entrepôt</p>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom / Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $location->name ?? '') }}" required maxlength="100"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                   placeholder="Allée A, Rack 2, Niveau 3">
        </div>
    </div>

    <div class="border-t border-gray-100 pt-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Coordonnées physiques</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                <input type="text" name="zone" value="{{ old('zone', $location->zone ?? '') }}" maxlength="50"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                       placeholder="Zone A">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Allée</label>
                <input type="text" name="aisle" value="{{ old('aisle', $location->aisle ?? '') }}" maxlength="20"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                       placeholder="A1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rack / Étagère</label>
                <input type="text" name="rack" value="{{ old('rack', $location->rack ?? '') }}" maxlength="20"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                       placeholder="R2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Niveau</label>
                <input type="text" name="level" value="{{ old('level', $location->level ?? '') }}" maxlength="20"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                       placeholder="N3">
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="2"
                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 resize-none">{{ old('description', $location->description ?? '') }}</textarea>
    </div>

    <label class="flex items-center gap-3 cursor-pointer">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
               {{ old('is_active', $location->is_active ?? true) ? 'checked' : '' }}>
        <span class="text-sm font-medium text-gray-700">Emplacement actif</span>
    </label>
</div>
