@extends('layouts.erp')
@section('title', 'Modifier écriture ' . $entry->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.journaux.index') }}" class="hover:text-gray-700">Journal comptable</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.journaux.show', $entry) }}" class="hover:text-gray-700">{{ $entry->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3"
     x-data="journalEntryForm()"
     x-init="init()">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Modifier l'écriture <span class="font-mono text-violet-700">{{ $entry->number }}</span></h1>
        <a href="{{ route('comptabilite.journaux.show', $entry) }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Retour
        </a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('comptabilite.journaux.update', $entry) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="space-y-3">

            {{-- Header --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">En-tête de l'écriture</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Journal <span class="text-red-500">*</span></label>
                        <select name="journal_type_id" required
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                            <option value="">— Sélectionner —</option>
                            @foreach($journalTypes as $jt)
                            <option value="{{ $jt->id }}" {{ old('journal_type_id', $entry->journal_type_id) == $jt->id ? 'selected' : '' }}>
                                {{ $jt->code }} — {{ $jt->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', $entry->entry_date?->format('Y-m-d')) }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Référence document</label>
                        <input type="text" name="reference" value="{{ old('reference', $entry->reference) }}"
                               placeholder="FA-00001..."
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Libellé général <span class="text-red-500">*</span></label>
                        <input type="text" name="description" value="{{ old('description', $entry->description) }}" required
                               x-model="description"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    </div>
                </div>
            </div>

            {{-- Lines --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-800">Lignes d'imputation</h2>
                    <button type="button" @click="addLine()"
                            class="text-sm text-violet-600 hover:text-violet-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter une ligne
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-5/12">Compte</th>
                                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-3/12">Libellé ligne</th>
                                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-2/12">Débit</th>
                                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-2/12">Crédit</th>
                                <th class="pb-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="line.id">
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 pr-2">
                                        <select :name="`lines[${index}][account_id]`" x-model="line.account_id"
                                                class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm font-mono focus:ring-1 focus:ring-violet-500">
                                            <option value="">— Compte —</option>
                                            @foreach($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`lines[${index}][label]`"
                                               x-model="line.label"
                                               :placeholder="description || 'Libellé...'"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-violet-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`lines[${index}][debit]`"
                                               x-model="line.debit"
                                               @input="onDebitChange(index)"
                                               min="0" step="1"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-violet-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`lines[${index}][credit]`"
                                               x-model="line.credit"
                                               @input="onCreditChange(index)"
                                               min="0" step="1"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-violet-500">
                                    </td>
                                    <td class="py-2">
                                        <button type="button" @click="removeLine(index)"
                                                class="p-1 text-gray-400 hover:text-red-500 rounded">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 font-semibold text-sm">
                                <td class="pt-3 pr-2 text-right text-gray-500" colspan="2">Totaux</td>
                                <td class="pt-3 pr-2 text-right tabular-nums" x-text="formatAmount(totalDebit)"></td>
                                <td class="pt-3 pr-2 text-right tabular-nums"
                                    :class="totalDebit !== totalCredit ? 'text-red-600' : 'text-green-600'"
                                    x-text="formatAmount(totalCredit)"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="pt-2">
                                    <div x-show="totalDebit > 0 && totalDebit !== totalCredit"
                                         class="flex items-center gap-2 text-sm text-red-600 bg-red-50 rounded-[4px] px-3 py-2">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        Écriture déséquilibrée : différence de <span class="font-semibold" x-text="formatAmount(Math.abs(totalDebit - totalCredit))"></span>
                                    </div>
                                    <div x-show="totalDebit > 0 && totalDebit === totalCredit"
                                         class="flex items-center gap-2 text-sm text-green-600 bg-green-50 rounded-[4px] px-3 py-2">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Écriture équilibrée
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('comptabilite.journaux.show', $entry) }}"
                   class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function journalEntryForm() {
    return {
        {{-- @json découpe son expression sur les virgules (extraction options/depth de
             json_encode) : un tableau à 4 champs = 3 virgules casse la compilation
             (« Unclosed [ »). On appelle json_encode directement avec les flags d'échappement. --}}
        lines: {!! json_encode($entry->lines->map(fn($l) => [
            'account_id' => $l->account_id,
            'label'      => $l->label,
            'debit'      => (int) $l->debit,
            'credit'     => (int) $l->credit,
        ]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!},
        nextId: 0,
        description: @json($entry->description),

        init() {
            // Assigner un id stable à chaque ligne pour x-for :key
            this.lines = this.lines.map(l => ({ id: this.nextId++, ...l }));
            while (this.lines.length < 2) this.addLine();
        },

        addLine() {
            this.lines.push({ id: this.nextId++, account_id: '', label: '', debit: 0, credit: 0 });
        },

        removeLine(index) {
            if (this.lines.length > 2) this.lines.splice(index, 1);
        },

        onDebitChange(index) {
            if (parseFloat(this.lines[index].debit) > 0) this.lines[index].credit = 0;
        },

        onCreditChange(index) {
            if (parseFloat(this.lines[index].credit) > 0) this.lines[index].debit = 0;
        },

        get totalDebit()  { return this.lines.reduce((s, l) => s + (parseInt(l.debit)  || 0), 0); },
        get totalCredit() { return this.lines.reduce((s, l) => s + (parseInt(l.credit) || 0), 0); },

        formatAmount(val) {
            return new Intl.NumberFormat('fr-FR').format(val || 0);
        },
    };
}
</script>
@endpush
@endsection
