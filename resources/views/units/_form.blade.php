@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $txa = 'w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $sw  = "relative w-9 h-5 flex-shrink-0 bg-gray-200 peer-checked:bg-emerald-600 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:bg-white after:rounded-full after:shadow after:transition-transform peer-checked:after:translate-x-4";
    $parentUnits = $parentUnits ?? collect();
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-x-6 gap-y-3"
     x-data="{
        abbr: @js(old('abbreviation', $unit->abbreviation ?? '')),
        factor: @js((string) old('conversion_factor', $unit->conversion_factor ?? '1')),
        parent: @js((string) old('parent_unit_id', $unit->parent_unit_id ?? '')),
        parents: @js($parentUnits->mapWithKeys(fn ($p) => [(string) $p->id => $p->abbreviation])->all()),
        get hint() {
            const f = parseFloat(String(this.factor).replace(',', '.')) || 1;
            const target = this.parent ? (this.parents[this.parent] ?? '?') : (this.abbr || '—');
            return '1 ' + (this.abbr || '—') + ' = ' + f.toLocaleString('fr-FR', {minimumFractionDigits: 6}) + ' ' + target;
        }
     }">

    {{-- ── Colonne 1 : identité ── --}}
    <div class="space-y-3">
        <div>
            <label for="code" class="{{ $lbl }}">Code <span class="text-red-500">*</span></label>
            <input type="text" id="code" name="code" maxlength="10"
                   value="{{ old('code', $unit->code ?? '') }}" placeholder="KG"
                   class="{{ $inp }} font-mono uppercase @error('code') !border-red-400 !bg-red-50 @enderror">
            @error('code')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="{{ $lbl }}">Nom <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" required
                   value="{{ old('name', $unit->name ?? '') }}" placeholder="Kilogramme"
                   class="{{ $inp }} @error('name') !border-red-400 !bg-red-50 @enderror">
            @error('name')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name_en" class="{{ $lbl }}">Nom (anglais)</label>
            <input type="text" id="name_en" name="name_en" maxlength="100"
                   value="{{ old('name_en', $unit->name_en ?? '') }}" placeholder="Kilogram"
                   class="{{ $inp }}">
        </div>
        <div>
            <label for="abbreviation" class="{{ $lbl }}">Symbole <span class="text-red-500">*</span></label>
            <input type="text" id="abbreviation" name="abbreviation" required maxlength="20"
                   value="{{ old('abbreviation', $unit->abbreviation ?? '') }}" placeholder="kg"
                   x-model="abbr"
                   class="{{ $inp }} font-mono @error('abbreviation') !border-red-400 !bg-red-50 @enderror">
            @error('abbreviation')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="type" class="{{ $lbl }}">Type d'unité <span class="text-red-500">*</span></label>
            <select id="type" name="type" class="{{ $inp }}">
                <option value="">— Sélectionner —</option>
                @foreach(['quantite' => 'Quantité', 'poids' => 'Masse / Poids', 'volume' => 'Volume', 'longueur' => 'Longueur', 'surface' => 'Surface', 'temps' => 'Temps', 'autre' => 'Autre'] as $val => $label)
                <option value="{{ $val }}" {{ old('type', $unit->type ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="dimension" class="{{ $lbl }}">Dimension</label>
            <select id="dimension" name="dimension" class="{{ $inp }}">
                <option value="">— Sélectionner —</option>
                @foreach(['masse' => 'Masse', 'longueur' => 'Longueur', 'surface' => 'Surface', 'volume' => 'Volume', 'temps' => 'Temps', 'quantite' => 'Quantité', 'autre' => 'Autre'] as $val => $label)
                <option value="{{ $val }}" {{ old('dimension', $unit->dimension ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ── Colonne 2 : conversion & précision ── --}}
    <div class="space-y-3">
        <div>
            <label for="parent_unit_id" class="{{ $lbl }}">Unité parente</label>
            <select id="parent_unit_id" name="parent_unit_id" x-model="parent" class="{{ $inp }}">
                <option value="">Aucune (Unité de base)</option>
                @foreach($parentUnits as $p)
                <option value="{{ $p->id }}" {{ (string) old('parent_unit_id', $unit->parent_unit_id ?? '') === (string) $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->abbreviation }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="conversion_factor" class="{{ $lbl }}">Facteur de conversion <span class="text-red-500">*</span></label>
            <input type="number" id="conversion_factor" name="conversion_factor" step="0.000001" min="0.000001"
                   value="{{ old('conversion_factor', $unit->conversion_factor ?? '1.000000') }}"
                   x-model="factor"
                   class="{{ $inp }} text-right font-mono @error('conversion_factor') !border-red-400 !bg-red-50 @enderror">
            <p class="text-[11px] text-gray-500 mt-1 font-mono" x-text="hint"></p>
            @error('conversion_factor')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="decimal_places" class="{{ $lbl }}">Décimales autorisées <span class="text-red-500">*</span></label>
            <select id="decimal_places" name="decimal_places" class="{{ $inp }}">
                @for($d = 0; $d <= 6; $d++)
                <option value="{{ $d }}" {{ (int) old('decimal_places', $unit->decimal_places ?? 2) === $d ? 'selected' : '' }}>{{ $d }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label for="rounding_mode" class="{{ $lbl }}">Arrondi</label>
            <select id="rounding_mode" name="rounding_mode" class="{{ $inp }}">
                @foreach(['mathematique' => 'Mathématique', 'superieur' => 'Au supérieur', 'inferieur' => 'À l\'inférieur'] as $val => $label)
                <option value="{{ $val }}" {{ old('rounding_mode', $unit->rounding_mode ?? 'mathematique') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="is_active" class="{{ $lbl }}">Statut <span class="text-red-500">*</span></label>
            <input type="hidden" name="is_active" value="0">
            <select id="is_active" name="is_active" class="{{ $inp }}">
                <option value="1" {{ old('is_active', ($unit->is_active ?? true) ? '1' : '0') == '1' ? 'selected' : '' }}>● Actif</option>
                <option value="0" {{ old('is_active', ($unit->is_active ?? true) ? '1' : '0') == '0' ? 'selected' : '' }}>○ Inactif</option>
            </select>
        </div>
    </div>

    {{-- ── Colonne 3 : description & défauts ── --}}
    <div class="space-y-3">
        <div>
            <label for="description" class="{{ $lbl }}">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="1000"
                      placeholder="Unité de mesure par défaut pour les produits en masse."
                      class="{{ $txa }}">{{ old('description', $unit->description ?? '') }}</textarea>
        </div>
        <div>
            <label for="unit_category" class="{{ $lbl }}">Catégorie d'unités</label>
            <select id="unit_category" name="unit_category" class="{{ $inp }}">
                <option value="">— Sélectionner —</option>
                @foreach(['masse' => 'Masse', 'longueur' => 'Longueur', 'surface' => 'Surface', 'volume' => 'Volume', 'quantite' => 'Quantité', 'temps' => 'Temps', 'conditionnement' => 'Conditionnement', 'autre' => 'Autre'] as $val => $label)
                <option value="{{ $val }}" {{ old('unit_category', $unit->unit_category ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="pt-1">
            <p class="{{ $lbl }}">Unité d'inventaire par défaut</p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="is_default_inventory" value="0">
                <input type="checkbox" name="is_default_inventory" value="1" {{ old('is_default_inventory', $unit->is_default_inventory ?? false) ? 'checked' : '' }} class="sr-only peer">
                <span class="{{ $sw }} mt-0.5"></span>
                <span class="text-[11.5px] text-gray-500 leading-snug">Utilisée par défaut dans les mouvements de stock et les inventaires.</span>
            </label>
        </div>
        <div class="pt-1">
            <p class="{{ $lbl }}">Unité de vente par défaut</p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="is_default_sales" value="0">
                <input type="checkbox" name="is_default_sales" value="1" {{ old('is_default_sales', $unit->is_default_sales ?? false) ? 'checked' : '' }} class="sr-only peer">
                <span class="{{ $sw }} mt-0.5"></span>
                <span class="text-[11.5px] text-gray-500 leading-snug">Utilisée par défaut dans les documents de vente.</span>
            </label>
        </div>
    </div>
</div>
