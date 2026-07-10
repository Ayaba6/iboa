@extends('layouts.erp')
@section('title', 'Nouveau décaissement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.decaissements.index') }}" class="hover:text-gray-700">Décaissements fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp  = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo= 'w-full h-8 px-2 border border-gray-200 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-gray-400 rounded-[3px] text-[14px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'text-[14px] font-bold text-emerald-700 mb-3';
    $err  = 'w-full h-8 px-2 border border-red-400 bg-red-50 rounded-[3px] text-[14px] focus:outline-none';
    $caret= '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel= 'bg-white border border-gray-200 rounded-[4px] p-4';
    $sfx  = '<span class="inline-flex items-center justify-center h-8 px-2 border border-l-0 border-gray-200 rounded-r-[3px] bg-gray-50 text-[12px] text-gray-500">XOF</span>';
@endphp

<div class="max-w-[1400px]"
     x-data="Object.assign(decPaymentForm({{ $selectedSupplier ? $selectedSupplier : 'null' }}), { tab: 'general', heure: '' })"
     x-init="setInterval(() => heure = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'}), 1000)">

    <form method="POST" action="{{ route('tresorerie.decaissements.store') }}" enctype="multipart/form-data"
          x-ref="form" @submit.prevent="submitForm()" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouveau décaissement fournisseur</h1>
            </div>
            <div class="flex items-center gap-1.5" x-data="{ menu: false }">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" @click="submitForm(true)" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Aperçu</button>
                <a href="{{ route('tresorerie.decaissements.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
                <div class="relative">
                    <button type="button" @click="menu = !menu" class="w-9 h-9 flex items-center justify-center text-gray-500 hover:text-gray-800 rounded-[4px] hover:bg-gray-100">⋮</button>
                    <div x-show="menu" @click.outside="menu = false" x-cloak class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-[4px] shadow-lg py-1 z-10 text-[13px]">
                        <a href="{{ route('tresorerie.decaissements.index') }}" class="block px-3 py-1.5 text-gray-600 hover:bg-gray-50">Liste des décaissements</a>
                        <button type="button" onclick="window.print()" class="block w-full text-left px-3 py-1.5 text-gray-600 hover:bg-gray-50">Imprimer</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs (ancres de défilement) --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general'=>'Général','reglement'=>'Règlement','imputation'=>'Imputation','pieces'=>'Pièces jointes','complement'=>'Complément'] as $tk => $tl)
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

        {{-- ═══ Rangée 1 : Informations générales | Détail du paiement ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            <section x-ref="sec_general" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}">Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-4"><label class="{{ $lbl }}">N° de décaissement</label><input type="text" value="DF-Auto" class="{{ $inpRo }} font-mono" readonly></div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                        <input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}">
                        <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
                    </div>

                    <div class="col-span-4"><label class="{{ $lbl }}">Date décaissement <span class="text-red-500">*</span></label><input type="date" name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}" class="{{ $errors->has('payment_date') ? $err : $inp }}"></div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Heure <span class="text-red-500">*</span></label><input type="text" :value="heure || '—'" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Statut</label><input type="text" value="Saisi" class="{{ $inpRo }}" readonly></div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Fournisseur <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="supplier_id" required x-model="supplierId" @change="loadInvoices()" class="{{ $errors->has('supplier_id') ? $err.' pr-8 appearance-none' : $lk }}">
                                <option value="">—</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ (old('supplier_id', $selectedSupplier) == $supplier->id) ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        @error('supplier_id')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-8"><label class="{{ $lbl }}">Référence paiement</label><input type="text" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="VIR F000245…" class="{{ $inp }}"></div>

                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                        <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Journal de trésorerie</label>
                        <input type="text" name="treasury_journal" maxlength="20" value="{{ old('treasury_journal', 'BQTR') }}" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Banque principale</p>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Mode de règlement</label>
                        <div class="relative">
                            <select name="payment_method_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Condition de paiement</label><input type="text" name="payment_condition" maxlength="60" value="{{ old('payment_condition') }}" placeholder="Fin de mois + 30 jours" class="{{ $inp }}"></div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Banque / Caisse <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="cash_account_id" required class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($cashAccounts as $ca)
                                    <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>{{ $ca->name }} ({{ number_format($ca->current_balance, 0, ',', ' ') }} FCFA)</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Tél. Mobile Money</label><input type="text" name="phone_number" maxlength="20" value="{{ old('phone_number') }}" placeholder="+226 70…" class="{{ $inp }}"></div>
                    <div class="col-span-4"></div>

                    <div class="col-span-12"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Règlement factures et avoirs fournisseur…">{{ old('notes') }}</textarea></div>
                </div>
            </section>

            <section x-ref="sec_reglement" class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}">Détail du paiement</h2>
                <div class="grid grid-cols-2 gap-x-3 gap-y-3">
                    <div>
                        <label class="{{ $lbl }}">Montant payé <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <input type="number" name="amount" min="1" step="1" required x-model.number="amount" class="{{ $errors->has('amount') ? $err.' rounded-r-none text-right tabular-nums' : $inp.' rounded-r-none text-right tabular-nums' }}">{!! $sfx !!}
                        </div>
                        @error('amount')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant à affecter</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format(amount||0)" class="{{ $inpRo }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>

                    <div>
                        <label class="{{ $lbl }}">Frais bancaires</label>
                        <div class="flex"><input type="number" name="bank_fees" min="0" step="1" x-model.number="bankFees" placeholder="0" class="{{ $inp }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant net</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format(netAmount)" class="{{ $inpRo }} rounded-r-none text-right tabular-nums font-semibold">{!! $sfx !!}</div>
                    </div>

                    <div><label class="{{ $lbl }}">N° de pièce</label><input type="text" name="piece_number" maxlength="60" value="{{ old('piece_number') }}" placeholder="VT…" class="{{ $inp }} font-mono"></div>
                    <div><label class="{{ $lbl }}">Référence bancaire</label><input type="text" name="bank_reference" maxlength="100" value="{{ old('bank_reference') }}" placeholder="TRX…" class="{{ $inp }} font-mono"></div>

                    <div><label class="{{ $lbl }}">Date valeur</label><input type="date" name="value_date" value="{{ old('value_date') }}" class="{{ $inp }}"></div>
                    <div>
                        <label class="{{ $lbl }}">Compte bancaire</label>
                        <input type="text" value="512100" class="{{ $inpRo }} font-mono" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">Lié à la banque/caisse sélectionnée</p>
                    </div>

                    <div class="col-span-2 space-y-1.5 pt-2 border-t border-gray-100 mt-1">
                        <label class="flex items-center gap-1.5 text-[12px] text-gray-700">
                            <input type="checkbox" disabled :checked="remainingToAllocate > 0 && totalAllocated > 0" class="w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600">
                            Paiement partiel
                        </label>
                        <label class="flex items-center gap-1.5 text-[12px] text-gray-400">
                            <input type="checkbox" disabled class="w-[15px] h-[15px] rounded-[2px] border-gray-300">
                            Décaissement validé <span class="text-[12px]">(workflow de validation)</span>
                        </label>
                        <label class="flex items-center gap-1.5 text-[12px] text-gray-700">
                            <input type="checkbox" checked disabled class="w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600">
                            Générer écriture comptable
                        </label>
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══ Factures / documents à régler ═══ --}}
        <section x-ref="sec_imputation" class="bg-white border border-gray-200 rounded-[4px]">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }} mb-0">Factures / documents à régler</h2>
                <div class="flex items-center gap-3">
                    <span class="text-[12px] text-gray-400" x-show="invoices.length" x-text="invoices.length + ' Résultat(s)'"></span>
                    <button type="button" @click="autoAllocate()" :disabled="invoices.length === 0 || amount <= 0"
                            class="inline-flex items-center gap-1.5 bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-[12px] font-semibold px-2.5 py-1 rounded-[4px] transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Répartir auto.
                    </button>
                </div>
            </div>

            <div x-show="!supplierId" class="px-4 py-6 text-gray-400 text-[13px]">Sélectionnez un fournisseur pour charger ses factures.</div>
            <div x-show="supplierId && loading" class="px-4 py-6 text-gray-400 text-[13px]">Chargement des factures…</div>
            <div x-show="supplierId && !loading && invoices.length === 0" class="px-4 py-6 text-gray-400 text-[13px]">Aucune facture impayée pour ce fournisseur.</div>

            <div x-show="supplierId && !loading && invoices.length > 0" class="overflow-x-auto">
                <table class="w-full text-[13px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="px-3 py-2 text-left w-10"><input type="checkbox" class="w-3.5 h-3.5 rounded border-gray-300" disabled></th>
                            <th class="px-3 py-2 text-left">Type document</th>
                            <th class="px-3 py-2 text-left">N° facture</th>
                            <th class="px-3 py-2 text-left">N° fournisseur</th>
                            <th class="px-3 py-2 text-left">Date facture</th>
                            <th class="px-3 py-2 text-left">Échéance</th>
                            <th class="px-3 py-2 text-right">Montant TTC</th>
                            <th class="px-3 py-2 text-right">Déjà réglé</th>
                            <th class="px-3 py-2 text-right">Reste à payer</th>
                            <th class="px-3 py-2 text-right">Montant affecté</th>
                            <th class="px-3 py-2 text-left">Devise</th>
                            <th class="px-3 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(inv, index) in invoices" :key="inv.id">
                            <tr class="hover:bg-emerald-50/40">
                                <td class="px-3 py-1.5"><input type="checkbox" class="w-3.5 h-3.5 rounded border-gray-300"></td>
                                <td class="px-3 py-1.5 text-gray-700">Facture</td>
                                <td class="px-3 py-1.5">
                                    <span class="font-mono text-emerald-700 underline" x-text="inv.number"></span>
                                    <input type="hidden" :name="`allocations[${index}][supplier_invoice_id]`" :value="inv.id">
                                    <input type="hidden" :name="`allocations[${index}][allocated_amount]`" :value="inv.allocated || 0">
                                </td>
                                <td class="px-3 py-1.5 text-gray-600 font-mono text-[12px]" x-text="inv.supplier_invoice_number || '—'"></td>
                                <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap" x-text="inv.received_at || '—'"></td>
                                <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap" x-text="inv.due_at || '—'"></td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-700 whitespace-nowrap" x-text="new Intl.NumberFormat('fr-FR').format(inv.total_ttc)"></td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 whitespace-nowrap" x-text="new Intl.NumberFormat('fr-FR').format((inv.total_ttc||0) - (inv.remaining_amount||0))"></td>
                                <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-800 whitespace-nowrap" x-text="new Intl.NumberFormat('fr-FR').format(inv.remaining_amount)"></td>
                                <td class="px-3 py-1.5 text-right">
                                    <input type="number" min="0" step="1" :max="inv.remaining_amount" x-model.number="inv.allocated"
                                           :class="inv.allocated > inv.remaining_amount ? 'border-red-400 bg-red-50' : 'border-gray-400'"
                                           class="w-28 h-7 border rounded-[3px] px-2 text-[13px] text-right focus:outline-none focus:ring-1 focus:ring-emerald-400 tabular-nums" placeholder="0">
                                </td>
                                <td class="px-3 py-1.5 text-gray-500 font-mono">XOF</td>
                                <td class="px-3 py-1.5 whitespace-nowrap">
                                    <span class="text-[12px] font-medium"
                                          :class="(inv.allocated || 0) >= inv.remaining_amount ? 'text-emerald-700' : ((inv.allocated || 0) > 0 ? 'text-orange-600' : 'text-blue-600')"
                                          x-text="(inv.allocated || 0) >= inv.remaining_amount ? 'Réglée' : ((inv.allocated || 0) > 0 ? 'Partiellement réglée' : 'À affecter')"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="border-t border-gray-300 bg-[#f7faf8] text-[12px] font-bold">
                        <tr>
                            <td colspan="6" class="px-3 py-2 text-gray-700">Total</td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-800" x-text="new Intl.NumberFormat('fr-FR').format(sumTtc)"></td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-500" x-text="new Intl.NumberFormat('fr-FR').format(sumPaid)"></td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-800" x-text="new Intl.NumberFormat('fr-FR').format(sumRemaining)"></td>
                            <td class="px-3 py-2 text-right tabular-nums text-emerald-700" x-text="new Intl.NumberFormat('fr-FR').format(totalAllocated)"></td>
                            <td colspan="2" class="px-3 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="px-4 py-2">
                    <p x-show="remainingToAllocate < 0" class="text-[12px] text-red-600">⚠ Le montant imputé dépasse le montant payé. Corrigez avant d'enregistrer.</p>
                    <p x-show="remainingToAllocate > 0 && totalAllocated > 0" class="text-[12px] text-amber-600">Le solde non imputé (<span x-text="new Intl.NumberFormat('fr-FR').format(remainingToAllocate)"></span> XOF) restera en avance fournisseur.</p>
                </div>
            </div>
        </section>

        {{-- ═══ Rangée 3 : Informations complémentaires | Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_complement" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}">Informations complémentaires</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-5"><label class="{{ $lbl }}">Objet du paiement</label><input type="text" name="payment_object" maxlength="150" value="{{ old('payment_object') }}" placeholder="Règlement fournisseurs…" class="{{ $inp }}"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Projet</label><input type="text" name="project" maxlength="60" value="{{ old('project') }}" class="{{ $inp }}"></div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Centre de coût</label><input type="text" name="cost_center" maxlength="30" value="{{ old('cost_center') }}" placeholder="CC100" class="{{ $inp }} font-mono"></div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Analytique</label><input type="text" name="analytic_section" maxlength="30" value="{{ old('analytic_section') }}" placeholder="ANA01" class="{{ $inp }} font-mono"></div>

                    <div class="col-span-4" x-ref="sec_pieces">
                        <label class="{{ $lbl }}">Pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[12px] border border-gray-400 rounded-[3px] px-2 py-1 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                    </div>
                    <div class="col-span-8"><label class="{{ $lbl }}">Observations</label><textarea name="observations" rows="2" maxlength="1000" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('observations') }}</textarea></div>
                </div>
            </section>

            <section class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}">Traçabilité</h2>
                <div class="grid grid-cols-2 gap-x-3 gap-y-3">
                    <div><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div><label class="{{ $lbl }}">Modifié le</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div><label class="{{ $lbl }}">Modifié par</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
function decPaymentForm(preselectedSupplierId) {
    return {
        supplierId: preselectedSupplierId ? String(preselectedSupplierId) : '',
        amount: '',
        bankFees: 0,
        invoices: [],
        loading: false,
        submitting: false,
        saveAndNew: false,

        get totalAllocated() {
            return this.invoices.reduce((sum, inv) => sum + (Number(inv.allocated) || 0), 0);
        },
        get remainingToAllocate() {
            return (Number(this.amount) || 0) - this.totalAllocated;
        },
        get netAmount() {
            return Math.max(0, (Number(this.amount) || 0) - (Number(this.bankFees) || 0));
        },
        get sumTtc() {
            return this.invoices.reduce((s, i) => s + (Number(i.total_ttc) || 0), 0);
        },
        get sumRemaining() {
            return this.invoices.reduce((s, i) => s + (Number(i.remaining_amount) || 0), 0);
        },
        get sumPaid() {
            return this.sumTtc - this.sumRemaining;
        },

        init() {
            if (this.supplierId) {
                this.loadInvoices();
            }
        },

        loadInvoices() {
            if (!this.supplierId) {
                this.invoices = [];
                return;
            }
            this.loading = true;
            this.invoices = [];

            fetch(`{{ route('tresorerie.decaissements.invoices') }}?supplier_id=${this.supplierId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.invoices = data.map(inv => ({ ...inv, allocated: 0 }));
                this.loading = false;
            })
            .catch(() => { this.loading = false; });
        },

        autoAllocate() {
            let remaining = Number(this.amount);
            for (let inv of this.invoices) {
                if (remaining <= 0) {
                    inv.allocated = 0;
                    continue;
                }
                const canAllocate = Math.min(remaining, inv.remaining_amount);
                inv.allocated = Math.floor(canAllocate);
                remaining -= inv.allocated;
            }
        },

        submitForm(andNew = false) {
            if (this.remainingToAllocate < 0 || (Number(this.amount) || 0) <= 0) return;
            this.saveAndNew = andNew === true;
            this.submitting = true;
            this.$nextTick(() => this.$refs.form.submit());
        },

        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' FCFA';
        }
    };
}
</script>
@endpush
