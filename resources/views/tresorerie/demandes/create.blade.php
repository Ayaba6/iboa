@extends('layouts.erp')
@section('title', 'Nouvelle demande de paiement')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.demandes.index') }}" class="hover:text-gray-700">Demandes de paiement</a>
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
     x-data="prForm()"
     x-init="setInterval(() => heure = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'}), 1000)">

    <form method="POST" action="{{ route('tresorerie.demandes.store') }}" enctype="multipart/form-data"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">
        <input type="hidden" name="amount" :value="Math.round(amount || 0)">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvelle demande de paiement</h1>
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
                <a href="{{ route('tresorerie.demandes.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Tabs (ancres) --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general'=>'Général','validation'=>'Validation','imputation'=>'Imputation','pieces'=>'Pièces jointes','complement'=>'Complément'] as $tk => $tl)
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

        {{-- ═══ Rangée 1 : 1. Infos générales | 2. Bénéficiaire | 3. Détails financiers ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            {{-- 1. Informations générales --}}
            <section x-ref="sec_general" class="{{ $panel }} xl:col-span-5">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-4"><label class="{{ $lbl }}">N° demande <span class="text-red-500">*</span></label><input type="text" value="DP-Auto" class="{{ $inpRo }} font-mono" readonly></div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                        <input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}">
                        <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
                    </div>

                    <div class="col-span-4"><label class="{{ $lbl }}">Date demande <span class="text-red-500">*</span></label><input type="text" value="{{ now()->format('d/m/Y') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Heure <span class="text-red-500">*</span></label><input type="text" :value="heure || '—'" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Statut</label><input type="text" value="Brouillon" class="{{ $inpRo }}" readonly></div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Demandeur <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly>
                    </div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Service</label><input type="text" name="service" maxlength="60" value="{{ old('service') }}" placeholder="Achats" class="{{ $inp }}"></div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Type de demande <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="request_type" class="{{ $lk }}">
                                @foreach(['paiement_fournisseur' => 'Paiement fournisseur', 'remboursement_frais' => 'Remboursement frais', 'avance' => 'Avance', 'autre' => 'Autre'] as $rv => $rl)
                                <option value="{{ $rv }}" {{ old('request_type', 'paiement_fournisseur') === $rv ? 'selected' : '' }}>{{ $rl }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Priorité</label>
                        <div class="relative">
                            <select name="priority" required class="{{ $lk }}">
                                @foreach(['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'] as $pv => $pl)
                                <option value="{{ $pv }}" {{ old('priority', 'normale') === $pv ? 'selected' : '' }}>{{ $pl }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-8"><label class="{{ $lbl }}">Référence interne</label><input type="text" name="internal_reference" maxlength="100" value="{{ old('internal_reference') }}" placeholder="FOURN-05-2026-102" class="{{ $inp }}"></div>

                    <div class="col-span-12"><label class="{{ $lbl }}">Objet de la demande <span class="text-red-500">*</span></label><input type="text" name="object" required maxlength="255" value="{{ old('object') }}" placeholder="Règlement factures fournisseurs — Mai 2026" class="{{ $errors->has('object') ? $inp.' border-red-400 bg-red-50' : $inp }}"></div>
                </div>
            </section>

            {{-- 2. Bénéficiaire / Fournisseur --}}
            <section class="{{ $panel }} xl:col-span-3">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Bénéficiaire / Fournisseur</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-12">
                        <label class="{{ $lbl }}">Fournisseur / bénéficiaire</label>
                        <div class="relative">
                            <select name="supplier_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-12"><label class="{{ $lbl }}">Raison sociale (bénéficiaire libre)</label><input type="text" name="beneficiary" maxlength="150" value="{{ old('beneficiary') }}" placeholder="Si non référencé fournisseur" class="{{ $inp }}"></div>
                    <div class="col-span-12">
                        <label class="{{ $lbl }}">Compte bancaire</label>
                        <input type="text" name="bank_account" maxlength="50" value="{{ old('bank_account') }}" placeholder="512100" class="{{ $inp }} font-mono">
                    </div>
                    <div class="col-span-12">
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
                </div>
            </section>

            {{-- 3. Détails financiers --}}
            <section x-ref="sec_imputation" class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">3.</span> Détails financiers</h2>
                <div class="grid grid-cols-2 gap-x-3 gap-y-3">
                    <div>
                        <label class="{{ $lbl }}">Montant demandé <span class="text-red-500">*</span></label>
                        <div class="flex"><input type="number" min="1" step="1" required x-model.number="amount" class="{{ $errors->has('amount') ? $inp.' border-red-400 bg-red-50 rounded-r-none text-right tabular-nums' : $inp.' rounded-r-none text-right tabular-nums' }}">{!! $sfx !!}</div>
                        @error('amount')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">TVA</label>
                        <div class="flex"><input type="number" name="tax_amount" min="0" step="1" x-model.number="tva" placeholder="0" class="{{ $inp }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant HT</label>
                        <div class="flex"><input type="text" name="amount_ht" readonly :value="Math.max(0, (amount||0) - (tva||0))" class="{{ $inpRo }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant TTC</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format(amount||0)" class="{{ $inpRo }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Frais divers</label>
                        <div class="flex"><input type="number" name="misc_fees" min="0" step="1" x-model.number="frais" placeholder="0" class="{{ $inp }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant net à payer</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format((amount||0) + (frais||0))" class="{{ $inpRo }} rounded-r-none text-right tabular-nums font-semibold" style="color:var(--erp-primary, #00843d)">{!! $sfx !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Date d'échéance</label><input type="date" name="due_date" value="{{ old('due_date') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Centre de coût</label><input type="text" name="cost_center" maxlength="30" value="{{ old('cost_center') }}" placeholder="CC100" class="{{ $inp }} font-mono"></div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Section analytique</label><input type="text" name="analytic_section" maxlength="30" value="{{ old('analytic_section') }}" placeholder="ANA01" class="{{ $inp }} font-mono"></div>
                </div>
            </section>
        </div>

        {{-- ═══ 4. Documents à régler / pièces justificatives ═══ --}}
        <section class="bg-white border border-gray-200 rounded-[4px]">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }} mb-0"><span class="text-gray-400 font-normal">4.</span> Documents à régler / pièces justificatives</h2>
                <span class="text-[12px] text-gray-400">Facture fournisseur liée (optionnelle)</span>
            </div>
            <div class="px-4 pb-4 grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-6 xl:col-span-4">
                    <label class="{{ $lbl }}">Facture fournisseur à régler</label>
                    <div class="relative">
                        <select name="supplier_invoice_id" class="{{ $lk }}">
                            <option value="">— Aucune (montant libre) —</option>
                        </select>{!! $caret !!}
                    </div>
                    <p class="text-[12px] text-gray-500 mt-0.5">La liste se restreint aux factures dues du fournisseur choisi (rattachement au traitement).</p>
                </div>
                <div class="col-span-6 xl:col-span-4" x-ref="sec_pieces">
                    <label class="{{ $lbl }}">Pièces justificatives</label>
                    <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full text-[13px] border border-gray-400 rounded-[3px] px-2 py-1 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                    <p class="text-[12px] text-gray-500 mt-0.5">PDF, images, Word, Excel — max 5 Mo par fichier.</p>
                </div>
            </div>
        </section>

        {{-- ═══ Rangée 3 : 5. Validation | 6. Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_validation" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">5.</span> Validation</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">Demandeur <span class="text-red-500">*</span></label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date demande</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Validateur</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date validation</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <p class="col-span-12 text-[12px] text-gray-500 -mt-1">Circuit : soumission → validation (selon seuil DAF / DG) → décaissement. Renseigné automatiquement au fil du workflow.</p>
                    <div class="col-span-12"><label class="{{ $lbl }}">Observations</label><textarea name="notes" rows="2" maxlength="1000" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Demande de règlement des factures fournisseurs…">{{ old('notes') }}</textarea></div>
                </div>
            </section>

            <section x-ref="sec_complement" class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">6.</span> Traçabilité</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Dernier statut</label><input type="text" value="Brouillon" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">N° version</label><input type="text" value="1" class="{{ $inpRo }} tabular-nums" readonly></div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
function prForm() {
    return {
        tab: 'general',
        heure: '',
        amount: {{ (int) old('amount') ?: 'null' }},
        tva: {{ (int) old('tax_amount') ?: 0 }},
        frais: {{ (int) old('misc_fees') ?: 0 }},
        saveAndNew: false,
        submitting: false,
    };
}
</script>
@endpush
