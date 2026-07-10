{{-- Formulaire partagé activité CRM --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-3">

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Activité</h3>
            <div class="space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                        <select name="type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                            @foreach(\App\Models\CrmActivity::TYPES as $k => $v)
                                <option value="{{ $k }}" {{ old('type', $activity->type ?? 'note') === $k ? 'selected' : '' }}>{{ $v['icon'] }} {{ $v['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priorité <span class="text-red-500">*</span></label>
                        <select name="priority" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                            @foreach(\App\Models\CrmActivity::PRIORITIES as $k => $v)
                                <option value="{{ $k }}" {{ old('priority', $activity->priority ?? 'normal') === $k ? 'selected' : '' }}>{{ $v['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sujet <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" value="{{ old('subject', $activity->subject ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 @error('subject') border-red-500 @enderror"
                           placeholder="Ex : Appel de suivi, RDV de présentation..." required>
                    @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="4"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                              placeholder="Détails, compte-rendu, prochaines étapes...">{{ old('description', $activity->description ?? '') }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date / Heure prévue</label>
                        <input type="datetime-local" name="due_at"
                               value="{{ old('due_at', isset($activity->due_at) ? $activity->due_at->format('Y-m-d\TH:i') : '') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Durée (minutes)</label>
                        <input type="number" name="duration_minutes"
                               value="{{ old('duration_minutes', $activity->duration_minutes ?? '') }}"
                               min="0" step="5" placeholder="Ex : 30, 60..."
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_done" id="is_done" value="1"
                           {{ old('is_done', $activity->is_done ?? false) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                    <label for="is_done" class="text-sm text-gray-700">Marquer comme fait</label>
                </div>
            </div>
        </div>

    </div>

    <div class="space-y-3">
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Associations</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact</label>
                    <select name="crm_contact_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucun —</option>
                        @foreach($contacts as $c)
                            <option value="{{ $c->id }}"
                                {{ old('crm_contact_id', $activity->crm_contact_id ?? $contactId ?? '') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}{{ $c->company_name ? ' (' . $c->company_name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Opportunité</label>
                    <select name="crm_opportunity_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucune —</option>
                        @foreach($opps as $o)
                            <option value="{{ $o->id }}"
                                {{ old('crm_opportunity_id', $activity->crm_opportunity_id ?? $opportunityId ?? '') == $o->id ? 'selected' : '' }}>
                                {{ Str::limit($o->title, 40) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                    <select name="user_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Moi-même —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('user_id', $activity->user_id ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
