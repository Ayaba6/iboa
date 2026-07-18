{{-- Formulaire partagé create/edit contact CRM --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Colonne principale --}}
    <div class="lg:col-span-2 space-y-3">

        {{-- Identité --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Identité</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $contact->name ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('name') border-red-500 @enderror"
                           placeholder="Jean Dupont" required>
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fonction / Poste</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $contact->job_title ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Directeur commercial">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Société</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $contact->company_name ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="SARL Exemple">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $contact->email ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $contact->phone ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="+226 70 00 00 00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                    <input type="text" name="mobile" value="{{ old('mobile', $contact->mobile ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
                    <input type="url" name="website" value="{{ old('website', $contact->website ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="https://exemple.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secteur d'activité</label>
                    <input type="text" name="sector" value="{{ old('sector', $contact->sector ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="BTP, Agriculture, Commerce...">
                </div>
            </div>
        </div>

        {{-- Adresse --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Adresse</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" name="address" value="{{ old('address', $contact->address ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                    <input type="text" name="city" value="{{ old('city', $contact->city ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Ouagadougou">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                    <input type="text" name="country" value="{{ old('country', $contact->country ?? 'BF') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="BF" maxlength="5">
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Notes</h3>
            <textarea name="notes" rows="4"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                      placeholder="Informations complémentaires, contexte, historique...">{{ old('notes', $contact->notes ?? '') }}</textarea>
        </div>

    </div>

    {{-- Colonne latérale --}}
    <div class="space-y-3">

        {{-- Qualification --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Qualification</h3>
            <div class="space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                    <select name="type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                        @foreach(\App\Models\CrmContact::TYPES as $k => $v)
                            <option value="{{ $k }}" {{ old('type', $contact->type ?? 'prospect') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                        @foreach(\App\Models\CrmContact::STATUSES as $k => $v)
                            <option value="{{ $k }}" {{ old('status', $contact->status ?? 'new') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Source <span class="text-red-500">*</span></label>
                    <select name="source" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                        @foreach(\App\Models\CrmContact::SOURCES as $k => $v)
                            <option value="{{ $k }}" {{ old('source', $contact->source ?? 'direct') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Score (0-100)</label>
                    <input type="number" name="score" value="{{ old('score', $contact->score ?? 0) }}"
                           min="0" max="100"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-1">Estimation de la qualité du lead</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tags (séparés par virgule)</label>
                    <input type="text" name="tags"
                           value="{{ old('tags', isset($contact) ? implode(', ', $contact->tags ?? []) : '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="vip, urgent, chaud">
                </div>
            </div>
        </div>

        {{-- Responsable --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Responsable</h3>
            <select name="user_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">— Non assigné —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('user_id', $contact->user_id ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

    </div>
</div>
