@extends('layouts.erp')
@section('title', 'Nouveau rapprochement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.rapprochement.index') }}" class="hover:text-gray-700">Rapprochement</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $tdIn  = 'no-spin w-full h-8 border border-gray-300 rounded-[3px] px-2 py-0 text-[13px] tabular-nums bg-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500';
@endphp

<div x-data="rapprochementForm()" class="space-y-3">

<form method="POST" action="{{ route('comptabilite.rapprochement.store') }}">
    @csrf
    <div class="bg-white border border-gray-300 rounded-[4px]">

        {{-- ═══ Bandeau + actions [X3] ═══ --}}
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Rapprochement bancaire : Création</h2>
            <div class="flex items-center gap-1.5">
                <button type="submit"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Enregistrer</button>
                <a href="{{ route('comptabilite.rapprochement.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
            </div>
        </div>

        @if($errors->any())
        <div class="m-4 mb-0 bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- ═══ 1. Informations générales ═══ --}}
        <div class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Informations générales</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-3">
                        <label class="{{ $lbl }}">Société <span class="text-red-600">*</span></label>
                        <input type="text" value="{{ currentCompany()?->name }}" readonly class="{{ $inp }} bg-gray-50 text-gray-600">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="{{ $lbl }}">Site <span class="text-red-600">*</span></label>
                        <input type="text" value="01" readonly class="{{ $inp }} bg-gray-50 text-gray-600 font-mono">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="{{ $lbl }}">Compte bancaire <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="cash_account_id" required class="{{ $lk }}">
                            <option value="">Sélectionner…</option>
                            @foreach($cashAccounts as $ca)
                            <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>{{ $ca->name }} ({{ $ca->code }})</option>
                            @endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Date du relevé <span class="text-red-600">*</span></label>
                        <input type="date" name="statement_date" value="{{ old('statement_date', date('Y-m-d')) }}" required class="{{ $inp }}">
                    </div>
                    <div class="sm:col-span-2"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Période du <span class="text-red-600">*</span></label>
                        <input type="date" name="period_start" value="{{ old('period_start') }}" required class="{{ $inp }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">au <span class="text-red-600">*</span></label>
                        <input type="date" name="period_end" value="{{ old('period_end') }}" required class="{{ $inp }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Solde d'ouverture (XOF) <span class="text-red-600">*</span></label>
                        <input type="number" name="opening_balance" value="{{ old('opening_balance', 0) }}" required class="{{ $inp }} no-spin text-right tabular-nums">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Solde comptable (XOF) <span class="text-red-600">*</span></label>
                        <input type="number" name="book_balance" value="{{ old('book_balance', 0) }}" required class="{{ $inp }} no-spin text-right tabular-nums">
                        <p class="text-[10.5px] text-gray-400 mt-0.5">Solde du compte en comptabilité à la date du relevé</p>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="{{ $lbl }}">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" class="{{ $inp }}">
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══ 2. Lignes du relevé bancaire ═══ --}}
        <div class="p-4 pt-0">
            <section class="border border-gray-200 rounded-[4px] overflow-hidden">
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>Lignes du relevé bancaire</span>
                    <button type="button" @click="addLine()"
                            class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une ligne</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#3b4248]">
                            <tr>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-8">N°</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-36">Date valeur</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide">Libellé</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-32">Référence</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-28">Débit (XOF)</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-28">Crédit (XOF)</th>
                                <th class="px-2 py-1.5 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(line, idx) in lines" :key="idx">
                            <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                                <td class="px-2 py-1 text-center text-gray-400 tabular-nums text-[12px]" x-text="idx + 1"></td>
                                <td class="px-2 py-1"><input type="date" :name="`lines[${idx}][value_date]`" x-model="line.value_date" class="{{ $tdIn }}"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${idx}][label]`" x-model="line.label" placeholder="Libellé…" class="{{ $tdIn }} min-w-[160px]"></td>
                                <td class="px-2 py-1"><input type="text" :name="`lines[${idx}][reference]`" x-model="line.reference" placeholder="Réf." class="{{ $tdIn }} min-w-[72px]"></td>
                                <td class="px-2 py-1"><input type="number" :name="`lines[${idx}][debit]`" x-model.number="line.debit" min="0" class="{{ $tdIn }} min-w-[72px] text-right"></td>
                                <td class="px-2 py-1"><input type="number" :name="`lines[${idx}][credit]`" x-model.number="line.credit" min="0" class="{{ $tdIn }} min-w-[72px] text-right"></td>
                                <td class="px-2 py-1 text-center">
                                    <button type="button" @click="removeLine(idx)" x-show="lines.length > 1" title="Supprimer la ligne" class="text-gray-300 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="bg-[#edf0f2] border-t-2 border-gray-300 font-bold text-gray-900">
                                <td colspan="4" class="px-2 py-1.5 text-right text-[11px] uppercase text-gray-500">Totaux</td>
                                <td class="px-2 py-1.5 text-right font-mono tabular-nums text-blue-700" x-text="fmt(totalDebit)"></td>
                                <td class="px-2 py-1.5 text-right font-mono tabular-nums text-emerald-700" x-text="fmt(totalCredit)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </div>
</form>

    {{-- ═══ Barre de contexte pied de page [X3] ═══ --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Document : <span class="text-white font-semibold">Rapprochement bancaire (brouillon)</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>

@push('scripts')
<script>
function rapprochementForm() {
    return {
        lines: [{ value_date: '', label: '', reference: '', debit: 0, credit: 0 }],
        addLine() {
            this.lines.push({ value_date: '', label: '', reference: '', debit: 0, credit: 0 });
        },
        removeLine(i) {
            if (this.lines.length > 1) this.lines.splice(i, 1);
        },
        get totalDebit()  { return this.lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0); },
        get totalCredit() { return this.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0); },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); },
    };
}
</script>
@endpush
@endsection
