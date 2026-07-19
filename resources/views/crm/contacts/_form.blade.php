{{-- Formulaire partagé create/edit contact CRM — sections design system X3 --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

    {{-- Colonne principale --}}
    <div class="lg:col-span-2 space-y-3">

        <x-x3.section number="1" title="Identité">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="sm:col-span-2">
                    <label for="c-name" class="block text-xs font-medium text-gray-600 mb-1">Nom complet <span class="text-red-500">*</span></label>
                    <input id="c-name" type="text" name="name" value="{{ old('name', $contact->name ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('name') border-red-500 @enderror"
                           placeholder="Jean Dupont" required>
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="c-job" class="block text-xs font-medium text-gray-600 mb-1">Fonction / Poste</label>
                    <input id="c-job" type="text" name="job_title" value="{{ old('job_title', $contact->job_title ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Directeur commercial">
                </div>

                <div>
                    <label for="c-company" class="block text-xs font-medium text-gray-600 mb-1">Société</label>
                    <input id="c-company" type="text" name="company_name" value="{{ old('company_name', $contact->company_name ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="SARL Exemple">
                </div>

                <div>
                    <label for="c-email" class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                    <input id="c-email" type="email" name="email" value="{{ old('email', $contact->email ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label for="c-phone" class="block text-xs font-medium text-gray-600 mb-1">Téléphone</label>
                    <input id="c-phone" type="text" name="phone" value="{{ old('phone', $contact->phone ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="+226 70 00 00 00">
                </div>

                <div>
                    <label for="c-mobile" class="block text-xs font-medium text-gray-600 mb-1">Mobile</label>
                    <input id="c-mobile" type="text" name="mobile" value="{{ old('mobile', $contact->mobile ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label for="c-website" class="block text-xs font-medium text-gray-600 mb-1">Site web</label>
                    <input id="c-website" type="url" name="website" value="{{ old('website', $contact->website ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="https://exemple.com">
                </div>

                <div>
                    <label for="c-sector" class="block text-xs font-medium text-gray-600 mb-1">Secteur d'activité</label>
                    <input id="c-sector" type="text" name="sector" value="{{ old('sector', $contact->sector ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="BTP, Agriculture, Commerce...">
                </div>
            </div>
        </x-x3.section>

        <x-x3.section number="2" title="Adresse">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="c-address" class="block text-xs font-medium text-gray-600 mb-1">Adresse</label>
                    <input id="c-address" type="text" name="address" value="{{ old('address', $contact->address ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="c-city" class="block text-xs font-medium text-gray-600 mb-1">Ville</label>
                    <input id="c-city" type="text" name="city" value="{{ old('city', $contact->city ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Ouagadougou">
                </div>
                <div>
                    <label for="c-country" class="block text-xs font-medium text-gray-600 mb-1">Pays</label>
                    <input id="c-country" type="text" name="country" value="{{ old('country', $contact->country ?? 'BF') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="BF" maxlength="5">
                </div>
            </div>
        </x-x3.section>

        <x-x3.section number="3" title="Notes">
            <textarea name="notes" rows="4" aria-label="Notes"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                      placeholder="Informations complémentaires, contexte, historique...">{{ old('notes', $contact->notes ?? '') }}</textarea>
        </x-x3.section>

    </div>

    {{-- Colonne latérale --}}
    <div class="space-y-3">

        <x-x3.section title="Qualification">
            <div class="space-y-4">

                <div>
                    <label for="c-type" class="block text-xs font-medium text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                    <select id="c-type" name="type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                        @foreach(\App\Models\CrmContact::TYPES as $k => $v)
                            <option value="{{ $k }}" {{ old('type', $contact->type ?? 'prospect') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="c-status" class="block text-xs font-medium text-gray-600 mb-1">Statut <span class="text-red-500">*</span></label>
                    <select id="c-status" name="status" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                        @foreach(\App\Models\CrmContact::STATUSES as $k => $v)
                            <option value="{{ $k }}" {{ old('status', $contact->status ?? 'new') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="c-source" class="block text-xs font-medium text-gray-600 mb-1">Source <span class="text-red-500">*</span></label>
                    <select id="c-source" name="source" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                        @foreach(\App\Models\CrmContact::SOURCES as $k => $v)
                            <option value="{{ $k }}" {{ old('source', $contact->source ?? 'direct') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="c-score" class="block text-xs font-medium text-gray-600 mb-1">Score (0-100)</label>
                    <input id="c-score" type="number" name="score" value="{{ old('score', $contact->score ?? 0) }}"
                           min="0" max="100"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right font-mono tabular-nums focus:ring-1 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-1">Estimation de la qualité du lead</p>
                </div>

                <div>
                    <label for="c-tags" class="block text-xs font-medium text-gray-600 mb-1">Tags (séparés par virgule)</label>
                    <input id="c-tags" type="text" name="tags"
                           value="{{ old('tags', isset($contact) ? implode(', ', $contact->tags ?? []) : '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="vip, urgent, chaud">
                </div>
            </div>
        </x-x3.section>

        <x-x3.section title="Responsable">
            <select name="user_id" aria-label="Responsable" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">— Non assigné —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('user_id', $contact->user_id ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </x-x3.section>

    </div>
</div>
