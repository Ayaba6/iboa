@extends('layouts.erp')
@section('title', 'Opération diverse de caisse')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.operations.index') }}" class="hover:text-gray-700">Caisse — Opérations diverses</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp  = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo= 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'text-[13px] font-bold text-emerald-700 mb-3';
    $caret= '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel= 'bg-white border border-gray-200 rounded-[4px] p-4';
    $sfx  = '<span class="inline-flex items-center justify-center h-8 px-2 border border-l-0 border-gray-200 rounded-r-[3px] bg-gray-50 text-[12px] text-gray-500">XOF</span>';
@endphp

<div class="max-w-[1400px]"
     x-data="opForm({{ (int) old('amount') ?: 0 }}, {{ (int) old('fees') ?: 0 }})"
     x-init="setInterval(() => heure = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'}), 1000)">

    <form method="POST" action="{{ route('tresorerie.operations.store') }}" enctype="multipart/form-data"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">
        <input type="hidden" name="amount" :value="Math.round(amount || 0)">
        <input type="hidden" name="fees" :value="Math.round(fees || 0)">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Opération diverse de caisse</h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" @click="saveAndNew = true; $nextTick(() => $refs.form.submit())" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Aperçu</button>
                <a href="{{ route('tresorerie.operations.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general'=>'Général','imputation'=>'Imputation','validation'=>'Validation','pieces'=>'Pièces jointes','complement'=>'Complément'] as $tk => $tl)
            <button type="button" @click="tab='{{ $tk }}'; $refs['sec_{{ $tk }}']?.scrollIntoView({behavior:'smooth', block:'start'})"
                    class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-[4px] text-[14px]">
            <p class="font-semibold mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- ═══ Rangée 1 : 1. Infos générales | 2. Détails financiers ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            {{-- 1. Informations générales --}}
            <section x-ref="sec_general" class="{{ $panel }} xl:col-span-7">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">N° opération <span class="text-red-500">*</span></label><input type="text" value="ODC-Auto" class="{{ $inpRo }} font-mono" readonly></div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                        <input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}">
                        <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Caisse <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="cash_account_id" required class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($cashAccounts as $a)
                                <option value="{{ $a->id }}" {{ old('cash_account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="col-span-3"><label class="{{ $lbl }}">Date opération <span class="text-red-500">*</span></label><input type="date" name="operation_date" required value="{{ old('operation_date', date('Y-m-d')) }}" class="{{ $errors->has('operation_date') ? $inp.' border-red-400 bg-red-50' : $inp }}"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Heure <span class="text-red-500">*</span></label><input type="text" :value="heure || '—'" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Statut <span class="text-red-500">*</span></label><input type="text" value="Saisie" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Type opération <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="operation_type" class="{{ $lk }}">
                                @foreach(['operation_diverse' => 'Opération diverse', 'depot' => 'Dépôt espèces', 'retrait' => 'Retrait espèces', 'transfert' => 'Transfert', 'regularisation' => 'Régularisation'] as $ov => $ol)
                                <option value="{{ $ov }}" {{ old('operation_type', 'operation_diverse') === $ov ? 'selected' : '' }}>{{ $ol }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Sens <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4 h-8">
                            <label class="flex items-center gap-1.5 text-[13px] cursor-pointer"><input type="radio" name="direction" value="entree" x-model="direction" class="w-3.5 h-3.5 text-emerald-600"> Entrée</label>
                            <label class="flex items-center gap-1.5 text-[13px] cursor-pointer"><input type="radio" name="direction" value="sortie" x-model="direction" class="w-3.5 h-3.5 text-emerald-600"> Sortie</label>
                        </div>
                    </div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Référence</label><input type="text" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="QTE-CAI-…" class="{{ $inp }} font-mono"></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Libellé <span class="text-red-500">*</span></label><input type="text" name="label" maxlength="255" value="{{ old('label') }}" placeholder="Recette diverse — …" class="{{ $inp }}"></div>

                    <div class="col-span-3"><label class="{{ $lbl }}">Demandeur <span class="text-red-500">*</span></label><input type="text" name="requester" maxlength="100" value="{{ old('requester', auth()->user()->name) }}" class="{{ $inp }}"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Responsable caisse <span class="text-red-500">*</span></label><input type="text" name="cashier_name" maxlength="100" value="{{ old('cashier_name') }}" class="{{ $inp }}"></div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                        <input type="text" name="currency_code" maxlength="3" value="{{ old('currency_code', 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                    </div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Commentaire</label><textarea name="comment" rows="2" maxlength="500" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('comment') }}</textarea></div>
                </div>
            </section>

            {{-- 2. Détails financiers --}}
            <section class="{{ $panel }} xl:col-span-5">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Détails financiers</h2>
                <div class="grid grid-cols-3 gap-x-3 gap-y-3">
                    <div>
                        <label class="{{ $lbl }}">Montant <span class="text-red-500">*</span></label>
                        <div class="flex"><input type="number" min="1" step="1" required x-model.number="amount" class="{{ $errors->has('amount') ? $inp.' border-red-400 bg-red-50 rounded-r-none text-right tabular-nums' : $inp.' rounded-r-none text-right tabular-nums' }}">{!! $sfx !!}</div>
                        @error('amount')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Frais</label>
                        <div class="flex"><input type="number" min="0" step="1" x-model.number="fees" placeholder="0" class="{{ $inp }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant net</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format(Math.max(0, (amount||0) - (fees||0)))" class="{{ $inpRo }} rounded-r-none text-right tabular-nums font-semibold" style="color:var(--erp-primary, #00843d)">{!! $sfx !!}</div>
                    </div>

                    <div><label class="{{ $lbl }}">Taux de change <span class="text-red-500">*</span></label><input type="number" step="0.000001" name="exchange_rate" value="{{ old('exchange_rate', '1') }}" class="{{ $inp }} text-right tabular-nums"></div>
                    <div><label class="{{ $lbl }}">Centre de coût</label><input type="text" name="cost_center" maxlength="30" value="{{ old('cost_center') }}" placeholder="CC-100" class="{{ $inp }} font-mono"></div>
                    <div><label class="{{ $lbl }}">Section analytique</label><input type="text" name="analytic_section" maxlength="30" value="{{ old('analytic_section') }}" placeholder="ANAL01" class="{{ $inp }} font-mono"></div>

                    <div>
                        <label class="{{ $lbl }}">Compte général <span class="text-red-500">*</span></label>
                        <input type="text" name="general_account" maxlength="20" value="{{ old('general_account') }}" placeholder="530100" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Caisse en Francs CFA</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Compte de contrepartie <span class="text-red-500">*</span></label>
                        <input type="text" name="counterpart_account" maxlength="20" value="{{ old('counterpart_account') }}" placeholder="707200" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Ventes diverses</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Mode de règlement <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="payment_method" class="{{ $lk }}">
                                @foreach(['Espèces','Chèque','Virement','Mobile Money'] as $pm)
                                <option value="{{ $pm }}" {{ old('payment_method', 'Espèces') === $pm ? 'selected' : '' }}>{{ $pm }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div><label class="{{ $lbl }}">Date de valeur <span class="text-red-500">*</span></label><input type="date" name="value_date" value="{{ old('value_date', date('Y-m-d')) }}" class="{{ $inp }}"></div>
                </div>
            </section>
        </div>

        {{-- ═══ 3. Lignes comptables / imputations ═══ --}}
        <section x-ref="sec_imputation" class="bg-white border border-gray-200 rounded-[4px]">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }} mb-0"><span class="text-gray-400 font-normal">3.</span> Lignes comptables / imputations</h2>
                <div class="flex items-center gap-2">
                    <span class="text-[12px] text-gray-400" x-text="lines.length + ' Résultat' + (lines.length>1?'s':'')"></span>
                    <button type="button" @click="addLine()" class="w-6 h-6 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-[3px]" title="Ajouter une ligne">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[14px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="px-3 py-1.5 text-left w-12">Ligne</th>
                            <th class="px-3 py-1.5 text-left">Compte</th>
                            <th class="px-3 py-1.5 text-left">Intitulé</th>
                            <th class="px-3 py-1.5 text-right">Débit</th>
                            <th class="px-3 py-1.5 text-right">Crédit</th>
                            <th class="px-3 py-1.5 text-left">Centre de coût</th>
                            <th class="px-3 py-1.5 text-left">Section analytique</th>
                            <th class="px-3 py-1.5 text-left">Observation</th>
                            <th class="px-3 py-1.5 w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(l, i) in lines" :key="i">
                            <tr class="odd:bg-white even:bg-gray-50/40">
                                <td class="px-3 py-1 tabular-nums text-gray-500" x-text="i + 1"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${i}][account]`" x-model="l.account" class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] font-mono focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${i}][label]`" x-model="l.label" class="w-full h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1"><input type="number" min="0" :name="`lines[${i}][debit]`" x-model.number="l.debit" class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1"><input type="number" min="0" :name="`lines[${i}][credit]`" x-model.number="l.credit" class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${i}][cost_center]`" x-model="l.cost_center" class="w-20 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] font-mono focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${i}][analytic]`" x-model="l.analytic" class="w-20 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] font-mono focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${i}][observation]`" x-model="l.observation" class="w-full h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-emerald-400"></td>
                                <td class="px-2 py-1 text-center"><button type="button" @click="lines.splice(i,1)" class="text-gray-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M6 7V4a1 1 0 011-1h10a1 1 0 011 1v3"/></svg></button></td>
                            </tr>
                        </template>
                        <tr x-show="lines.length === 0"><td colspan="9" class="px-4 py-6 text-center text-gray-400 text-[13px]">Aucune ligne — cliquez sur + pour ajouter une imputation.</td></tr>
                    </tbody>
                    <tfoot class="border-t border-gray-300 bg-[#f7faf8] text-[13px] font-bold">
                        <tr>
                            <td colspan="3" class="px-3 py-2 text-right text-gray-700">Total</td>
                            <td class="px-3 py-2 text-right tabular-nums" :class="totalDebit !== totalCredit ? 'text-red-600' : 'text-gray-800'" x-text="new Intl.NumberFormat('fr-FR').format(totalDebit)"></td>
                            <td class="px-3 py-2 text-right tabular-nums" :class="totalDebit !== totalCredit ? 'text-red-600' : 'text-gray-800'" x-text="new Intl.NumberFormat('fr-FR').format(totalCredit)"></td>
                            <td colspan="4" class="px-3 py-2 text-[12px] font-normal" :class="totalDebit === totalCredit ? 'text-emerald-600' : 'text-red-600'" x-text="totalDebit === totalCredit ? 'Équilibré' : 'Déséquilibré (débit ≠ crédit)'"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="px-4 py-2 text-[12px] text-gray-500">Aperçu de saisie analytique. L'écriture comptable officielle (SYSCOHADA) est générée automatiquement à l'enregistrement selon le compte général, la contrepartie et le sens.</p>
        </section>

        {{-- ═══ Rangée 3 : 4. Validation | 5. Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_validation" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">4.</span> Validation</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">Demandeur <span class="text-red-500">*</span></label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Validateur</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date demande</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date validation</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-12"><label class="{{ $lbl }}">Observations</label><textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Opération conforme — …">{{ old('notes') }}</textarea></div>
                </div>
            </section>

            <section x-ref="sec_complement" class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">5.</span> Traçabilité</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Dernier statut</label><input type="text" value="Saisie" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">N° version</label><input type="text" value="1" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-12" x-ref="sec_pieces">
                        <label class="{{ $lbl }}">Pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-gray-400 rounded-[3px] px-2 py-1 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                    </div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
function opForm(amount, fees) {
    return {
        tab: 'general',
        heure: '',
        direction: '{{ old('direction', $direction) }}',
        amount: amount || null,
        fees: fees || 0,
        lines: @json(old('lines', [])),
        saveAndNew: false,
        submitting: false,
        get totalDebit()  { return this.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0); },
        get totalCredit() { return this.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0); },
        addLine() { this.lines.push({ account: '', label: '', debit: 0, credit: 0, cost_center: '', analytic: '', observation: '' }); },
    };
}
</script>
@endpush
