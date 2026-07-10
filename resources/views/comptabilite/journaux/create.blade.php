@extends('layouts.erp')
@section('title', 'Nouvelle écriture comptable')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle écriture comptable</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    // py-0 : neutralise le py-2 du plugin @tailwindcss/forms sur <select>
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'text-[13px] font-bold text-emerald-700 mb-3';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel = 'bg-white border border-gray-200 rounded-[4px] p-4';
    $company    = currentCompany();
    $fiscalYear = $company?->currentFiscalYear;
@endphp

{{-- Pas de x-init="init()" : Alpine 3 appelle automatiquement init() du data object (sinon doublé → 4 lignes) --}}
<div class="max-w-[1400px]" x-data="journalEntryForm()">

    <form action="{{ route('comptabilite.journaux.store') }}" method="POST" enctype="multipart/form-data"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_validate" :value="saveAndValidate ? 1 : 0">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvelle écriture comptable</h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" :disabled="submitting || simulation || totalDebit === 0 || totalDebit !== totalCredit"
                        @click="saveAndValidate = true; $nextTick(() => $refs.form.submit())"
                        :title="simulation ? 'Écriture de simulation — non validable' : (totalDebit !== totalCredit ? 'Écriture déséquilibrée — validation impossible' : '')"
                        class="text-[14px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed px-5 py-2 rounded-[4px] transition-colors">
                    Valider
                </button>
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Brouillon
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
                <a href="{{ route('comptabilite.journaux.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['entete' => 'En-tête', 'lignes' => 'Lignes', 'taxes' => 'Taxes', 'pieces' => 'Pièces jointes', 'historique' => 'Historique'] as $tk => $tl)
            <button type="button" @click="tab = '{{ $tk }}'; $refs['sec_{{ $tk }}']?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
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

        {{-- ═══ 1. En-tête ═══ --}}
        <section x-ref="sec_entete" class="{{ $panel }} scroll-mt-24">
            <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> En-tête</h2>
            <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Journal <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="journal_type_id" required class="{{ $lk }} font-mono"
                                @change="journalName = $el.selectedOptions[0]?.dataset.name || ''">
                            <option value="">—</option>
                            @foreach($journalTypes as $jt)
                            <option value="{{ $jt->id }}" data-name="{{ $jt->name }}" @selected(old('journal_type_id') == $jt->id)>{{ $jt->code }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Type de journal</label>
                    <input type="text" :value="journalName || '—'" class="{{ $inpRo }}" readonly>
                </div>
                <div class="col-span-6 sm:col-span-1">
                    <label class="{{ $lbl }}">N° pièce</label>
                    <input type="text" value="Auto" class="{{ $inpRo }} font-mono text-[12px]" readonly>
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Date comptable <span class="text-red-500">*</span></label>
                    <input type="date" name="entry_date" required value="{{ old('entry_date', date('Y-m-d')) }}" x-model="entryDate"
                           class="{{ $errors->has('entry_date') ? $inp.' border-red-400 bg-red-50' : $inp }}">
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Date document</label>
                    <input type="date" name="value_date" value="{{ old('value_date', date('Y-m-d')) }}" class="{{ $inp }}">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="{{ $lbl }}">Exercice / Période</label>
                    <input type="text" :value="'{{ $fiscalYear?->label ?? '—' }}' + ' · ' + periodLabel" class="{{ $inpRo }} tabular-nums" readonly>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <label class="{{ $lbl }}">Société</label>
                    <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Site</label>
                    <input type="text" value="01 — Site principal" class="{{ $inpRo }}" readonly>
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Devise</label>
                    <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
                    <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Statut</label>
                    <input type="text" value="Brouillon" class="{{ $inpRo }}" readonly>
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="{{ $lbl }}">Mode de saisie</label>
                    <input type="text" value="Saisie manuelle" class="{{ $inpRo }}" readonly>
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Référence / Pièce d'origine</label>
                    <input type="text" name="reference" maxlength="50" value="{{ old('reference') }}" placeholder="FACT-…" class="{{ $inp }} font-mono">
                </div>
                <div class="col-span-12 sm:col-span-5">
                    <label class="{{ $lbl }}">Libellé <span class="text-red-500">*</span></label>
                    <input type="text" name="description" required maxlength="255" value="{{ old('description') }}"
                           x-model="description" placeholder="Achat de matières premières — Facture n° …"
                           class="{{ $errors->has('description') ? $inp.' border-red-400 bg-red-50' : $inp }}">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="{{ $lbl }}">Tiers</label>
                    <input type="text" name="partner_name" maxlength="100" value="{{ old('partner_name') }}" placeholder="FOURNISSEUR SARL" class="{{ $inp }}">
                </div>
                <div class="col-span-6 sm:col-span-2">
                    <label class="{{ $lbl }}">Simulation</label>
                    <label class="flex items-center gap-2 h-8 cursor-pointer">
                        <input type="hidden" name="is_simulation" value="0">
                        <input type="checkbox" name="is_simulation" value="1" x-model="simulation" @checked(old('is_simulation'))
                               class="w-4 h-4 rounded border-gray-400 text-emerald-600 focus:ring-emerald-400">
                        <span class="text-[13px]" :class="simulation ? 'text-orange-600 font-semibold' : 'text-gray-500'"
                              x-text="simulation ? 'Pièce provisoire — non validable' : 'Écriture réelle'"></span>
                    </label>
                </div>
            </div>
        </section>

        {{-- ═══ 2. Lignes d'écriture ═══ --}}
        <section x-ref="sec_lignes" class="bg-white border border-gray-200 rounded-[4px] scroll-mt-24">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }} mb-0"><span class="text-gray-400 font-normal">2.</span> Lignes d'écriture <span class="text-gray-400 font-normal" x-text="'(' + lines.length + ')'"></span></h2>
                <div class="flex items-center gap-1">
                    <button type="button" @click="addLine()" class="w-6 h-6 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-[3px]" title="Ajouter une ligne">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button type="button" @click="duplicateLastLine()" class="w-6 h-6 flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-[3px]" title="Dupliquer la dernière ligne">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                    <button type="button" @click="removeEmptyLines()" class="w-6 h-6 flex items-center justify-center border border-gray-300 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-[3px]" title="Supprimer les lignes vides">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M6 7V4a1 1 0 011-1h10a1 1 0 011 1v3"/></svg>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[14px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="px-2 py-1.5 w-6"></th>
                            <th class="px-3 py-1.5 text-left w-10">N°</th>
                            <th class="px-3 py-1.5 text-left">Compte général <span class="text-red-300">*</span></th>
                            <th class="px-3 py-1.5 text-left">Intitulé compte</th>
                            <th class="px-3 py-1.5 text-left">Tiers</th>
                            <th class="px-3 py-1.5 text-left">Centre de coût</th>
                            <th class="px-3 py-1.5 text-left">Référence</th>
                            <th class="px-3 py-1.5 text-left">Libellé ligne</th>
                            <th class="px-3 py-1.5 text-right">Débit (XOF)</th>
                            <th class="px-3 py-1.5 text-right">Crédit (XOF)</th>
                            <th class="px-3 py-1.5 text-left">Taxe</th>
                            <th class="px-3 py-1.5 text-left">Date échéance</th>
                            <th class="px-3 py-1.5 text-left">Devise</th>
                            <th class="px-3 py-1.5 w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(line, index) in lines" :key="line.id">
                            <tr class="odd:bg-white even:bg-gray-50/40"
                                @dragover.prevent
                                @drop.prevent="moveLine(dragIdx, index)"
                                :class="dragIdx === index ? 'opacity-50' : ''">
                                <td class="px-2 py-1 text-gray-300 cursor-grab select-none" draggable="true"
                                    @dragstart="dragIdx = index" @dragend="dragIdx = null" title="Glisser pour réordonner">⋮⋮</td>
                                <td class="px-3 py-1 tabular-nums text-gray-500" x-text="index + 1"></td>
                                <td class="px-2 py-1">
                                    <select :name="`lines[${index}][account_id]`" x-model="line.account_id"
                                            @change="line.account_name = $el.selectedOptions[0]?.dataset.name || ''"
                                            class="appearance-none w-28 h-7 py-0 pl-1.5 pr-6 border border-gray-300 rounded-[3px] text-[13px] font-mono bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                        <option value="">—</option>
                                        @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" data-name="{{ $account->name }}">{{ $account->code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1 text-[12px] text-gray-600 min-w-[130px]" x-text="line.account_name || '—'"></td>
                                <td class="px-2 py-1">
                                    <input type="text" :name="`lines[${index}][partner_name]`" x-model="line.partner_name" maxlength="100"
                                           class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1">
                                    <input type="text" :name="`lines[${index}][cost_center]`" x-model="line.cost_center" maxlength="30" placeholder="CC01"
                                           class="w-16 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] font-mono focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1">
                                    <input type="text" :name="`lines[${index}][reconciliation_ref]`" x-model="line.reference" maxlength="50"
                                           class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] font-mono focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1">
                                    <input type="text" :name="`lines[${index}][label]`" x-model="line.label"
                                           :placeholder="description || 'Libellé…'"
                                           class="w-full min-w-[120px] h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1">
                                    <input type="number" :name="`lines[${index}][debit]`" x-model="line.debit" @input="onDebitChange(index)"
                                           min="0" step="1"
                                           class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1">
                                    <input type="number" :name="`lines[${index}][credit]`" x-model="line.credit" @input="onCreditChange(index)"
                                           min="0" step="1"
                                           class="w-24 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1">
                                    <select :name="`lines[${index}][tax_code]`" x-model="line.tax_code"
                                            class="appearance-none w-20 h-7 py-0 pl-1.5 pr-5 border border-gray-300 rounded-[3px] text-[13px] font-mono bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                        <option value="">—</option>
                                        <option value="TX0">TX0</option>
                                        <option value="TVA18">TVA18</option>
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <input type="date" :name="`lines[${index}][due_date]`" x-model="line.due_date"
                                           class="h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-400">
                                </td>
                                <td class="px-2 py-1 text-[12px] text-gray-500 font-mono">XOF</td>
                                <td class="px-2 py-1 text-center">
                                    <button type="button" @click="removeLine(index)" class="text-gray-400 hover:text-red-600" title="Supprimer la ligne">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M6 7V4a1 1 0 011-1h10a1 1 0 011 1v3"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="text-white font-bold" style="background:#065f46">
                            <td colspan="8" class="px-3 py-1.5 text-right text-[11px] uppercase">Total</td>
                            <td class="px-3 py-1.5 text-right font-mono tabular-nums" x-text="formatAmount(totalDebit)"></td>
                            <td class="px-3 py-1.5 text-right font-mono tabular-nums" x-text="formatAmount(totalCredit)"></td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="px-4 py-2 text-[12px] text-gray-500">Débit OU crédit par ligne — jamais les deux. Les lignes vides sont ignorées à l'enregistrement. Minimum 2 lignes effectives.</p>
        </section>

        {{-- ═══ Rangée : 3. Taxes | 4. Pièces jointes | 5. Historique ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_taxes" class="{{ $panel }} xl:col-span-5 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">3.</span> Taxes — récapitulatif</h2>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-gray-200 text-[11px] uppercase text-gray-500">
                            <th class="py-1 text-left">Code taxe</th>
                            <th class="py-1 text-right">Base débit</th>
                            <th class="py-1 text-right">Base crédit</th>
                            <th class="py-1 text-right">Lignes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="t in taxSummary" :key="t.code">
                            <tr>
                                <td class="py-1 font-mono font-semibold text-gray-700" x-text="t.code"></td>
                                <td class="py-1 text-right tabular-nums" x-text="formatAmount(t.debit)"></td>
                                <td class="py-1 text-right tabular-nums" x-text="formatAmount(t.credit)"></td>
                                <td class="py-1 text-right text-gray-500" x-text="t.count"></td>
                            </tr>
                        </template>
                        <tr x-show="taxSummary.length === 0">
                            <td colspan="4" class="py-3 text-center text-gray-400 text-[12px]">Aucun code taxe renseigné sur les lignes.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section x-ref="sec_pieces" class="{{ $panel }} xl:col-span-4 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">4.</span> Pièces jointes</h2>
                <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                       class="w-full text-[13px] border border-gray-400 rounded-[3px] px-2 py-1 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                <p class="text-[12px] text-gray-500 mt-1.5">PDF, images, Word, Excel — max 5 fichiers · 5 Mo chacun. Attachés à l'écriture à l'enregistrement.</p>
            </section>

            <section x-ref="sec_historique" class="{{ $panel }} xl:col-span-3 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">5.</span> Historique</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-12"><p class="text-[12px] text-gray-500">Le journal des validations et contre-passations s'affiche sur la fiche après création.</p></div>
                </div>
            </section>
        </div>

        {{-- ═══ Bandeau équilibre (maquette X3) ═══ --}}
        <div class="bg-white border rounded-[4px] p-3 grid grid-cols-2 lg:grid-cols-5 gap-3 items-center"
             :class="totalDebit > 0 && totalDebit === totalCredit ? 'border-emerald-300' : (totalDebit > 0 ? 'border-red-300' : 'border-gray-200')">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex w-9 h-9 rounded-full items-center justify-center flex-shrink-0"
                      :class="totalDebit > 0 && totalDebit === totalCredit ? 'bg-emerald-100 text-emerald-600' : (totalDebit > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400')">
                    <svg x-show="totalDebit === 0 || totalDebit === totalCredit" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="totalDebit > 0 && totalDebit !== totalCredit" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
                <div>
                    <p class="text-[13px] font-bold" :class="totalDebit > 0 && totalDebit === totalCredit ? 'text-emerald-800' : (totalDebit > 0 ? 'text-red-700' : 'text-gray-500')"
                       x-text="totalDebit === 0 ? 'En attente de saisie' : (totalDebit === totalCredit ? 'Écriture équilibrée' : 'Écriture déséquilibrée')"></p>
                    <p class="text-[11px] text-gray-400">Le total débit doit être égal au total crédit.</p>
                </div>
            </div>
            <div class="text-center border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total débit</p>
                <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight" x-text="formatAmount(totalDebit)"></p>
                <p class="text-[11px] text-gray-400">XOF</p>
            </div>
            <div class="text-center border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total crédit</p>
                <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight" x-text="formatAmount(totalCredit)"></p>
                <p class="text-[11px] text-gray-400">XOF</p>
            </div>
            <div class="text-center border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Écart</p>
                <p class="text-[17px] font-bold tabular-nums leading-tight"
                   :class="totalDebit === totalCredit ? 'text-emerald-700' : 'text-red-600'"
                   x-text="formatAmount(Math.abs(totalDebit - totalCredit))"></p>
                <p class="text-[11px]" :class="totalDebit === totalCredit ? 'text-gray-400' : 'text-orange-500 font-semibold'"
                   x-text="totalDebit === totalCredit ? 'XOF' : 'Pièce non validable'"></p>
            </div>
            <div class="text-center border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Statut de la pièce</p>
                <p class="text-[15px] font-bold leading-tight text-orange-500">Pièce non validée</p>
                <p class="text-[11px] text-gray-400">Statut : Brouillon</p>
            </div>
        </div>

    </form>
</div>

@push('scripts')
<script>
function journalEntryForm() {
    return {
        tab: 'entete',
        lines: [],
        nextId: 0,
        description: '',
        entryDate: '{{ old('entry_date', date('Y-m-d')) }}',
        journalName: '',
        simulation: {{ old('is_simulation') ? 'true' : 'false' }},
        dragIdx: null,
        saveAndValidate: false,
        submitting: false,

        init() {
            this.addLine();
            this.addLine();
        },

        get periodLabel() {
            if (!this.entryDate) return '—';
            const d = new Date(this.entryDate);
            return d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
        },

        addLine() {
            this.lines.push({ id: this.nextId++, account_id: '', account_name: '', partner_name: '', cost_center: '', reference: '', label: '', debit: 0, credit: 0, tax_code: '', due_date: '' });
        },

        removeLine(index) {
            if (this.lines.length > 2) this.lines.splice(index, 1);
        },

        duplicateLastLine() {
            const last = this.lines[this.lines.length - 1];
            if (!last) return this.addLine();
            this.lines.push({ ...last, id: this.nextId++, debit: 0, credit: 0 });
        },

        removeEmptyLines() {
            const kept = this.lines.filter(l => (parseInt(l.debit) || 0) > 0 || (parseInt(l.credit) || 0) > 0 || l.account_id);
            while (kept.length < 2) kept.push({ id: this.nextId++, account_id: '', account_name: '', partner_name: '', cost_center: '', reference: '', label: '', debit: 0, credit: 0, tax_code: '', due_date: '' });
            this.lines = kept;
        },

        moveLine(from, to) {
            if (from === null || from === to) return;
            const [moved] = this.lines.splice(from, 1);
            this.lines.splice(to, 0, moved);
            this.dragIdx = null;
        },

        get taxSummary() {
            const map = {};
            for (const l of this.lines) {
                if (!l.tax_code) continue;
                map[l.tax_code] ??= { code: l.tax_code, debit: 0, credit: 0, count: 0 };
                map[l.tax_code].debit  += parseInt(l.debit)  || 0;
                map[l.tax_code].credit += parseInt(l.credit) || 0;
                map[l.tax_code].count++;
            }
            return Object.values(map);
        },

        onDebitChange(index) {
            if (parseFloat(this.lines[index].debit) > 0) this.lines[index].credit = 0;
        },

        onCreditChange(index) {
            if (parseFloat(this.lines[index].credit) > 0) this.lines[index].debit = 0;
        },

        get totalDebit() {
            return this.lines.reduce((s, l) => s + (parseInt(l.debit) || 0), 0);
        },

        get totalCredit() {
            return this.lines.reduce((s, l) => s + (parseInt(l.credit) || 0), 0);
        },

        formatAmount(val) {
            return new Intl.NumberFormat('fr-FR').format(val || 0);
        },
    };
}
</script>
@endpush
@endsection
