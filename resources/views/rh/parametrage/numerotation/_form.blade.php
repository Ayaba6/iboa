{{--
    Partial : formulaire règle de numérotation.
    Variables attendues : $numbering (instance), $isEdit (bool)
--}}
<div x-data="numerotationForm()" x-init="init()" class="space-y-8">

    {{-- Section Identification --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">1</span>
            Identification
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Code <span class="text-red-500">*</span>
                </label>
                <input type="text" name="code" value="{{ old('code', $numbering->code) }}"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 uppercase"
                       placeholder="BUL-STD" maxlength="30" pattern="[A-Z0-9_\-]+"
                       {{ $isEdit ? 'readonly' : '' }}
                       oninput="this.value=this.value.toUpperCase()" required>
                @if($isEdit)
                <p class="text-xs text-gray-400 mt-1">Le code ne peut pas être modifié.</p>
                @else
                <p class="text-xs text-gray-400 mt-1">Lettres majuscules, chiffres, tirets et underscores uniquement.</p>
                @endif
                @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Libellé <span class="text-red-500">*</span>
                </label>
                <input type="text" name="libelle" value="{{ old('libelle', $numbering->libelle) }}"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="Numérotation standard" maxlength="150" required>
                @error('libelle') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section Format --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">2</span>
            Format du numéro
        </h2>

        {{-- Aperçu live --}}
        <div class="mb-6 bg-[#eef5f0] border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs text-emerald-700 font-medium mb-1">Aperçu du format</p>
            <p class="font-mono text-[16px] font-bold text-emerald-900" x-text="preview">...</p>
            <p class="text-xs text-emerald-600 mt-1">
                Basé sur la date actuelle · le numéro de séquence réel dépend des bulletins déjà générés.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            {{-- Préfixe --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Préfixe <span class="text-red-500">*</span>
                </label>
                <input type="text" name="prefix" x-model="form.prefix"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="BUL" maxlength="20" required>
                @error('prefix') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            {{-- Séparateur --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Séparateur <span class="text-red-500">*</span>
                </label>
                <input type="text" name="separator" x-model="form.separator"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="-" maxlength="5" required>
                <p class="text-xs text-gray-400 mt-1">Ex : <code>-</code> <code>/</code> <code>.</code></p>
                @error('separator') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            {{-- Format année --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Format année</label>
                <select name="year_format" x-model="form.year_format"
                        class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="YYYY">YYYY (4 chiffres)</option>
                    <option value="YY">YY (2 chiffres)</option>
                    <option value="none">Aucun</option>
                </select>
                @error('year_format') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            {{-- Format mois --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Format mois</label>
                <select name="month_format" x-model="form.month_format"
                        class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="MM">MM (avec zéro)</option>
                    <option value="M">M (sans zéro)</option>
                    <option value="none">Aucun</option>
                </select>
                @error('month_format') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
            {{-- Longueur séquence --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Longueur du numéro de séquence <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-3">
                    <input type="range" name="seq_length" x-model.number="form.seq_length"
                           min="2" max="8" step="1"
                           class="flex-1 accent-indigo-600">
                    <span class="w-12 text-center font-mono text-sm font-bold text-emerald-800 bg-[#eef5f0] rounded px-2 py-1"
                          x-text="'0'.repeat(form.seq_length - 1) + '1'"></span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    <span x-text="form.seq_length"></span> chiffres (ex : <span x-text="'0'.repeat(form.seq_length - 1) + '1'"></span> … <span x-text="'9'.repeat(form.seq_length)"></span>)
                </p>
                @error('seq_length') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            {{-- Réinitialisation --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Réinitialisation de la séquence
                </label>
                <select name="reset_on" x-model="form.reset_on"
                        class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="year">Chaque année (remise à 1 le 1er janvier)</option>
                    <option value="month">Chaque mois (remise à 1 le 1er du mois)</option>
                    <option value="never">Jamais (séquence continue)</option>
                </select>
                @error('reset_on') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section Options --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">3</span>
            Options
        </h2>
        <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $numbering->is_active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 text-emerald-700 rounded border-gray-300 focus:ring-emerald-500">
                <div>
                    <span class="text-sm font-medium text-gray-900">Règle active</span>
                    <p class="text-xs text-gray-400">Seules les règles actives peuvent être utilisées pour numéroter les bulletins.</p>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1"
                       {{ old('is_default', $numbering->is_default ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 text-emerald-700 rounded border-gray-300 focus:ring-emerald-500">
                <div>
                    <span class="text-sm font-medium text-gray-900">Règle par défaut</span>
                    <p class="text-xs text-gray-400">Utilisée automatiquement si aucune règle n'est précisée lors de la génération du bulletin.</p>
                </div>
            </label>
        </div>

        <div class="mt-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
            <textarea name="notes" rows="3"
                      class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      maxlength="1000" placeholder="Informations complémentaires…">{{ old('notes', $numbering->notes) }}</textarea>
            @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('rh.numerotation.index') }}"
           class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-[4px] hover:bg-gray-50 transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="px-6 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            {{ $isEdit ? 'Mettre à jour' : 'Créer la règle' }}
        </button>
    </div>
</div>

@push('scripts')
<script>
function numerotationForm() {
    return {
        form: {
            prefix:       '{{ old('prefix', $numbering->prefix ?? 'BUL') }}',
            separator:    '{{ old('separator', $numbering->separator ?? '-') }}',
            year_format:  '{{ old('year_format', $numbering->year_format ?? 'YYYY') }}',
            month_format: '{{ old('month_format', $numbering->month_format ?? 'MM') }}',
            seq_length:   {{ old('seq_length', $numbering->seq_length ?? 4) }},
            reset_on:     '{{ old('reset_on', $numbering->reset_on ?? 'year') }}',
        },
        preview: '',
        init() {
            this.$watch('form', () => this.buildPreview(), { deep: true });
            this.buildPreview();
        },
        buildPreview() {
            const now = new Date();
            const pad = (n, z) => String(n).padStart(z, '0');
            const year4 = now.getFullYear();
            const year2 = String(year4).slice(-2);
            const monthPad = pad(now.getMonth() + 1, 2);
            const monthRaw = String(now.getMonth() + 1);
            const seqLen = parseInt(this.form.seq_length) || 4;
            const seq = pad(1, seqLen);

            let parts = [this.form.prefix || 'BUL'];
            const sep = this.form.separator || '-';

            if (this.form.year_format === 'YYYY') parts.push(year4);
            else if (this.form.year_format === 'YY') parts.push(year2);

            if (this.form.month_format === 'MM') parts.push(monthPad);
            else if (this.form.month_format === 'M') parts.push(monthRaw);

            parts.push(seq);
            this.preview = parts.join(sep);
        }
    };
}
</script>
@endpush
