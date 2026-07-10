{{-- Formulaire opportunité --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-3">

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Opportunité</h3>
            <div class="space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $opportunity->title ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 @error('title') border-red-500 @enderror"
                           placeholder="Ex : Fourniture de matériel informatique — SONABEL" required>
                    @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant estimé (FCFA) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" value="{{ old('amount', $opportunity->amount ?? 0) }}"
                               min="0" step="1000"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Probabilité (%) <span class="text-red-500">*</span></label>
                        <input type="number" name="probability" value="{{ old('probability', $opportunity->probability ?? 25) }}"
                               min="0" max="100"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Étape <span class="text-red-500">*</span></label>
                        <select name="stage" id="stage-select"
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500" required>
                            @foreach(\App\Models\CrmOpportunity::STAGES as $k => $s)
                                <option value="{{ $k }}"
                                        data-prob="{{ $s['prob'] }}"
                                        {{ old('stage', $opportunity->stage ?? ($contactId ? 'prospection' : 'prospection')) === $k ? 'selected' : '' }}>
                                    {{ $s['icon'] }} {{ $s['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de clôture estimée</label>
                        <input type="date" name="expected_close"
                               value="{{ old('expected_close', isset($opportunity->expected_close) ? $opportunity->expected_close->format('Y-m-d') : '') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produit / Service concerné</label>
                    <input type="text" name="product_service" value="{{ old('product_service', $opportunity->product_service ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Ex : Logiciel ERP, Prestation de service, ...">
                </div>

                <div id="lost-reason-row" class="{{ old('stage', $opportunity->stage ?? '') === 'perdu' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Raison de la perte</label>
                    <input type="text" name="lost_reason" value="{{ old('lost_reason', $opportunity->lost_reason ?? '') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Prix trop élevé, concurrent retenu, ...">
                </div>

            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Notes</h3>
            <textarea name="notes" rows="4"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                      placeholder="Contexte, enjeux, obstacles...">{{ old('notes', $opportunity->notes ?? '') }}</textarea>
        </div>
    </div>

    <div class="space-y-3">
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Associations</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact / Prospect</label>
                    <select name="crm_contact_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucun —</option>
                        @foreach($contacts as $c)
                            <option value="{{ $c->id }}"
                                {{ old('crm_contact_id', $opportunity->crm_contact_id ?? $contactId) == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}{{ $c->company_name ? ' (' . $c->company_name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Commercial responsable</label>
                    <select name="user_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Non assigné —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('user_id', $opportunity->user_id ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Valeur pondérée --}}
        <div class="bg-[#eef5f0] rounded-[4px] border border-emerald-100 p-5">
            <h3 class="text-xs font-semibold text-emerald-800 uppercase tracking-wide mb-3">Valeur pondérée</h3>
            <p class="text-[16px] font-bold text-emerald-900" id="weighted-value">— FCFA</p>
            <p class="text-xs text-emerald-600 mt-1">Montant × Probabilité</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const amountInput = document.querySelector('[name="amount"]');
    const probInput   = document.querySelector('[name="probability"]');
    const stageSelect = document.getElementById('stage-select');
    const lostRow     = document.getElementById('lost-reason-row');
    const wv          = document.getElementById('weighted-value');

    function updateWeighted() {
        const a = parseFloat(amountInput?.value) || 0;
        const p = parseFloat(probInput?.value)   || 0;
        const w = Math.round(a * p / 100);
        if (wv) wv.textContent = w.toLocaleString('fr-FR') + ' FCFA';
    }

    amountInput?.addEventListener('input', updateWeighted);
    probInput?.addEventListener('input', updateWeighted);
    updateWeighted();

    stageSelect?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const prob = opt.getAttribute('data-prob');
        if (prob !== null && probInput) {
            probInput.value = prob;
            updateWeighted();
        }
        if (lostRow) {
            lostRow.classList.toggle('hidden', this.value !== 'perdu');
        }
    });
})();
</script>
@endpush
