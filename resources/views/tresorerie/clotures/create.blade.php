@extends('layouts.erp')
@section('title', 'Clôture de caisse')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.clotures.index') }}" class="hover:text-gray-700">Clôtures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle clôture</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    // py-0 : neutralise le py-2 du plugin @tailwindcss/forms sur <select> (texte tronqué en h-8 sinon)
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'text-[13px] font-bold text-emerald-700 mb-3';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel = 'bg-white border border-gray-200 rounded-[4px] p-4';
    $accountsJson = $cashAccounts->mapWithKeys(fn ($a) => [$a->id => (int) $a->current_balance]);
    // Coupures FCFA courantes (billets + pièces)
    $denoms = [10000, 5000, 2000, 1000, 500, 250, 100, 50, 25];
@endphp

<div class="max-w-[1400px]"
     x-data="{
        tab: 'general', saveAndNew: false, submitting: false,
        account: '{{ old('cash_account_id', '') }}',
        counted: {{ (int) old('counted_balance', 0) }},
        manual: false,
        balances: {{ Js::from($accountsJson) }},
        denoms: {{ Js::from(collect($denoms)->mapWithKeys(fn ($d) => [$d => (int) old('denominations.' . $d, 0)])) }},
        get billetage() { return Object.entries(this.denoms).reduce((s, [d, q]) => s + d * (Number(q) || 0), 0); },
        get effective() { return this.manual ? (Number(this.counted) || 0) : this.billetage; },
        get theoretical() { return this.account ? (this.balances[this.account] ?? 0) : null; },
        get diff() { return this.theoretical === null ? null : this.effective - this.theoretical; },
        fmt(n) { return n === null ? '—' : new Intl.NumberFormat('fr-FR').format(n); }
     }">

    <form method="POST" action="{{ route('tresorerie.clotures.store') }}" enctype="multipart/form-data"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">
        <input type="hidden" name="counted_balance" :value="effective">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Clôture de caisse</h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting || !account"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" @click="saveAndNew = true; $nextTick(() => $refs.form.submit())" :disabled="submitting || !account"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Aperçu</button>
                <a href="{{ route('tresorerie.clotures.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general' => 'Général', 'billetage' => 'Billetage', 'validation' => 'Validation', 'pieces' => 'Pièces jointes'] as $tk => $tl)
            <button type="button" @click="tab = '{{ $tk }}'; $refs['sec_{{ $tk }}']?.scrollIntoView({behavior: 'smooth', block: 'start'})"
                    class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-[4px] text-[14px]">{{ session('error') }}</div>
        @endif
        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-[4px] text-[14px]">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- ═══ Rangée 1 : 1. Infos générales | 2. Comptage ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            <section x-ref="sec_general" class="{{ $panel }} xl:col-span-7">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">N° clôture <span class="text-red-500">*</span></label><input type="text" value="CLO-Auto" class="{{ $inpRo }} font-mono" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label><input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Site</label><input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}"></div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Caisse <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="cash_account_id" x-model="account" required class="{{ $lk }} @error('cash_account_id') border-red-500 @enderror">
                                <option value="">—</option>
                                @foreach($cashAccounts as $ca)
                                <option value="{{ $ca->id }}">{{ $ca->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="col-span-3"><label class="{{ $lbl }}">Date de clôture <span class="text-red-500">*</span></label><input type="date" name="closure_date" value="{{ old('closure_date', date('Y-m-d')) }}" required class="{{ $inp }}"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Heure</label><input type="text" value="{{ now()->format('H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Statut</label><input type="text" value="Brouillon" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Devise</label>
                        <input type="text" name="currency_code" maxlength="3" value="{{ old('currency_code', 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                    </div>

                    <div class="col-span-3"><label class="{{ $lbl }}">Caissier <span class="text-red-500">*</span></label><input type="text" name="cashier_name" maxlength="100" value="{{ old('cashier_name', auth()->user()->name) }}" class="{{ $inp }}"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Responsable caisse</label><input type="text" name="supervisor_name" maxlength="100" value="{{ old('supervisor_name') }}" class="{{ $inp }}"></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Notes</label><input type="text" name="notes" maxlength="1000" value="{{ old('notes') }}" class="{{ $inp }}" placeholder="Observations de la journée…"></div>
                </div>
            </section>

            {{-- 2. Comptage --}}
            <section class="{{ $panel }} xl:col-span-5">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Comptage</h2>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="border border-gray-200 rounded-[4px] px-2 py-3">
                        <p class="text-[11px] font-bold text-gray-500 uppercase">Solde théorique</p>
                        <p class="mt-1 text-[16px] font-bold font-mono tabular-nums text-gray-700" x-text="fmt(theoretical)"></p>
                    </div>
                    <div class="border border-gray-200 rounded-[4px] px-2 py-3">
                        <p class="text-[11px] font-bold text-gray-500 uppercase">Compté</p>
                        <p class="mt-1 text-[16px] font-bold font-mono tabular-nums text-gray-900" x-text="fmt(effective)"></p>
                    </div>
                    <div class="border rounded-[4px] px-2 py-3"
                         :class="diff === null || diff === 0 ? 'border-gray-200' : (diff > 0 ? 'border-emerald-300 bg-emerald-50' : 'border-red-300 bg-red-50')">
                        <p class="text-[11px] font-bold text-gray-500 uppercase">Écart</p>
                        <p class="mt-1 text-[16px] font-bold font-mono tabular-nums"
                           :class="diff === null || diff === 0 ? 'text-gray-400' : (diff > 0 ? 'text-emerald-700' : 'text-red-700')"
                           x-text="diff === null ? '—' : (diff > 0 ? '+' : '') + fmt(diff)"></p>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="flex items-center gap-1.5 text-[12px] text-gray-700 cursor-pointer mb-1.5">
                        <input type="checkbox" x-model="manual" class="w-[14px] h-[14px] rounded-[2px] border-gray-400 text-emerald-600">
                        Saisie directe du montant compté (sans billetage)
                    </label>
                    <div x-show="manual" x-cloak>
                        <input type="number" x-model.number="counted" min="0" step="1"
                               class="{{ $inp }} text-right font-mono font-semibold text-[16px] h-10">
                    </div>
                    <p x-show="!manual" class="text-[12px] text-gray-500">Le montant compté est calculé depuis le <strong>billetage</strong> (section 3).</p>
                </div>

                <div class="mt-3" x-show="diff !== null && diff !== 0" x-cloak>
                    <label class="{{ $lbl }}">Motif de l'écart <span class="text-amber-600">(requis pour valider)</span></label>
                    <textarea name="difference_reason" rows="2" maxlength="1000" placeholder="Ex. : rendu de monnaie, erreur de saisie…"
                              class="w-full px-2 py-1.5 border border-amber-400 rounded-[3px] text-[14px] focus:outline-none focus:ring-1 focus:ring-amber-400 resize-none">{{ old('difference_reason') }}</textarea>
                </div>
                <p class="mt-2 text-[12px] text-gray-500">À la validation, l'écart est comptabilisé automatiquement (759 excédent / 659 manquant).</p>
            </section>
        </div>

        {{-- ═══ 3. Billetage ═══ --}}
        <section x-ref="sec_billetage" class="bg-white border border-gray-200 rounded-[4px]" x-show="!manual" x-cloak>
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }} mb-0"><span class="text-gray-400 font-normal">3.</span> Billetage</h2>
                <span class="text-[12px] text-gray-400">Quantités par coupure — total calculé automatiquement</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[14px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="px-3 py-1.5 text-left">Coupure</th>
                            <th class="px-3 py-1.5 text-right w-40">Quantité</th>
                            <th class="px-3 py-1.5 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($denoms as $d)
                        <tr class="odd:bg-white even:bg-gray-50/40">
                            <td class="px-3 py-1 font-mono tabular-nums text-gray-800">{{ number_format($d, 0, ',', ' ') }} F <span class="text-[11px] text-gray-400">{{ $d >= 500 ? 'billet' : 'pièce' }}</span></td>
                            <td class="px-2 py-1 text-right">
                                <input type="number" name="denominations[{{ $d }}]" x-model.number="denoms[{{ $d }}]" min="0" step="1"
                                       class="w-28 h-7 border border-gray-300 rounded-[3px] px-1.5 text-[13px] text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-400">
                            </td>
                            <td class="px-3 py-1 text-right font-mono tabular-nums text-gray-700" x-text="fmt({{ $d }} * (Number(denoms[{{ $d }}]) || 0))"></td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-300 bg-[#f7faf8] text-[13px] font-bold">
                        <tr>
                            <td colspan="2" class="px-3 py-2 text-right text-gray-700">Total billetage</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums" style="color:var(--erp-primary, #00843d)" x-text="fmt(billetage) + ' F'"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        {{-- ═══ Rangée 3 : 4. Validation | 5. Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_validation" class="{{ $panel }} xl:col-span-7">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">4.</span> Validation</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">Demandeur <span class="text-red-500">*</span></label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Validateur</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date demande</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date validation</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-12"><p class="text-[12px] text-gray-500">La clôture est créée en <strong>brouillon</strong> ; la validation (fiche de la clôture) comptabilise l'écart et fige le comptage.</p></div>
                </div>
            </section>

            <section x-ref="sec_pieces" class="{{ $panel }} xl:col-span-5">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">5.</span> Traçabilité</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Dernier statut</label><input type="text" value="Brouillon" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">N° version</label><input type="text" value="1" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-12">
                        <label class="{{ $lbl }}">Pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-gray-400 rounded-[3px] px-2 py-1 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                        <p class="text-[12px] text-gray-500 mt-0.5">Feuille de comptage signée, justificatifs d'écart…</p>
                    </div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection
