{{--
    Partial : formulaire modèle de bulletin.
    Variables attendues : $template (instance), $isEdit (bool)
--}}
<div class="space-y-8">

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
                <input type="text" name="code" value="{{ old('code', $template->code) }}"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 uppercase"
                       placeholder="TMPL-STD" maxlength="30" pattern="[A-Z0-9_\-]+"
                       {{ $isEdit ? 'readonly' : '' }}
                       oninput="this.value=this.value.toUpperCase()" required>
                @if($isEdit)
                <p class="text-xs text-gray-400 mt-1">Le code ne peut pas être modifié.</p>
                @endif
                @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Libellé <span class="text-red-500">*</span>
                </label>
                <input type="text" name="libelle" value="{{ old('libelle', $template->libelle) }}"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="Modèle standard" maxlength="150" required>
                @error('libelle') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2"
                          class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                          maxlength="500" placeholder="Description courte du modèle…">{{ old('description', $template->description) }}</textarea>
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section Mise en page --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">2</span>
            Mise en page
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Format papier</label>
                <select name="paper_size"
                        class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="A4" {{ old('paper_size', $template->paper_size) === 'A4' ? 'selected' : '' }}>A4</option>
                    <option value="letter" {{ old('paper_size', $template->paper_size) === 'letter' ? 'selected' : '' }}>Letter</option>
                </select>
                @error('paper_size') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Orientation</label>
                <select name="orientation"
                        class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="portrait" {{ old('orientation', $template->orientation) === 'portrait' ? 'selected' : '' }}>Portrait</option>
                    <option value="landscape" {{ old('orientation', $template->orientation) === 'landscape' ? 'selected' : '' }}>Paysage</option>
                </select>
                @error('orientation') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Couleur principale</label>
                <select name="primary_color"
                        class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    @foreach(['indigo' => 'Indigo', 'blue' => 'Bleu', 'green' => 'Vert', 'red' => 'Rouge', 'orange' => 'Orange', 'teal' => 'Teal', 'gray' => 'Gris'] as $val => $label)
                    <option value="{{ $val }}" {{ old('primary_color', $template->primary_color) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('primary_color') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section Sections affichées --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">3</span>
            Sections à afficher
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @php
            $toggles = [
                ['name' => 'show_logo',            'label' => 'Logo de l\'entreprise',             'desc' => 'Affiche le logo en haut du bulletin.'],
                ['name' => 'show_company_address',  'label' => 'Adresse de l\'entreprise',          'desc' => 'Coordonnées complètes de la société.'],
                ['name' => 'show_employee_photo',   'label' => 'Photo de l\'employé',               'desc' => 'Photo de profil si disponible.'],
                ['name' => 'show_net_a_payer_box',  'label' => 'Encadré Net à Payer',               'desc' => 'Mise en évidence du montant net en pied de bulletin.'],
                ['name' => 'show_cumuls',           'label' => 'Tableau des cumuls annuels',        'desc' => 'Cumuls brut, net, cotisations depuis le 1er janvier.'],
                ['name' => 'show_conges_solde',     'label' => 'Solde de congés',                   'desc' => 'Jours acquis, pris et restants.'],
                ['name' => 'show_cout_employeur',   'label' => 'Coût employeur total',              'desc' => 'Affiche le coût total chargé employeur.'],
            ];
            @endphp
            @foreach($toggles as $toggle)
            <label class="flex items-start gap-3 p-3 rounded-[4px] border border-gray-300 hover:border-emerald-400 hover:bg-emerald-50 cursor-pointer transition-colors">
                <input type="hidden" name="{{ $toggle['name'] }}" value="0">
                <input type="checkbox" name="{{ $toggle['name'] }}" value="1"
                       {{ old($toggle['name'], $template->{$toggle['name']} ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 mt-0.5 text-emerald-700 rounded border-gray-300 focus:ring-emerald-500">
                <div>
                    <span class="text-sm font-medium text-gray-900">{{ $toggle['label'] }}</span>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $toggle['desc'] }}</p>
                </div>
            </label>
            @endforeach
        </div>
    </div>

    {{-- Section Textes en-tête / pied de page --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">4</span>
            Textes personnalisés
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Texte d'en-tête</label>
                <textarea name="header_text" rows="3"
                          class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                          maxlength="1000" placeholder="Texte affiché en haut du bulletin…">{{ old('header_text', $template->header_text) }}</textarea>
                @error('header_text') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Texte de pied de page</label>
                <textarea name="footer_text" rows="3"
                          class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                          maxlength="1000" placeholder="Texte affiché en bas du bulletin (mentions légales, etc.)…">{{ old('footer_text', $template->footer_text) }}</textarea>
                @error('footer_text') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section Options --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <span class="w-7 h-7 rounded-[4px] bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">5</span>
            Options
        </h2>
        <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 text-emerald-700 rounded border-gray-300 focus:ring-emerald-500">
                <div>
                    <span class="text-sm font-medium text-gray-900">Modèle actif</span>
                    <p class="text-xs text-gray-400">Seuls les modèles actifs peuvent être utilisés lors de la génération.</p>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1"
                       {{ old('is_default', $template->is_default ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 text-emerald-700 rounded border-gray-300 focus:ring-emerald-500">
                <div>
                    <span class="text-sm font-medium text-gray-900">Modèle par défaut</span>
                    <p class="text-xs text-gray-400">Utilisé automatiquement si aucun modèle n'est précisé.</p>
                </div>
            </label>
        </div>
        <div class="mt-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
            <textarea name="notes" rows="2"
                      class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      maxlength="1000" placeholder="Notes ou instructions d'utilisation…">{{ old('notes', $template->notes) }}</textarea>
            @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('rh.modeles-bulletins.index') }}"
           class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-[4px] hover:bg-gray-50 transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="px-6 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            {{ $isEdit ? 'Mettre à jour' : 'Créer le modèle' }}
        </button>
    </div>
</div>
