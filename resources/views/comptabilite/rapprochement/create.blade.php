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
<div x-data="rapprochementForm()" class="space-y-3 max-w-5xl">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Nouveau rapprochement bancaire</h1>
        <a href="{{ route('comptabilite.rapprochement.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('comptabilite.rapprochement.store') }}" class="space-y-3">
        @csrf

        {{-- Header --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Informations générales</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte bancaire <span class="text-red-500">*</span></label>
                    <select name="cash_account_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                        <option value="">Sélectionner...</option>
                        @foreach($cashAccounts as $ca)
                        <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>
                            {{ $ca->name }} ({{ $ca->code }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date du relevé <span class="text-red-500">*</span></label>
                    <input type="date" name="statement_date" value="{{ old('statement_date', date('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période du <span class="text-red-500">*</span></label>
                    <input type="date" name="period_start" value="{{ old('period_start') }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période au <span class="text-red-500">*</span></label>
                    <input type="date" name="period_end" value="{{ old('period_end') }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Solde d'ouverture (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="opening_balance" value="{{ old('opening_balance', 0) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Solde comptable (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="book_balance" value="{{ old('book_balance', 0) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                </div>
            </div>
        </div>

        {{-- Bank statement lines --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Lignes du relevé bancaire</h2>
                <button type="button" @click="addLine()"
                        class="text-sm bg-violet-50 hover:bg-violet-100 text-violet-700 font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    + Ajouter une ligne
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-32">Date valeur</th>
                            <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase">Libellé</th>
                            <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-28">Référence</th>
                            <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-28">Débit</th>
                            <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-28">Crédit</th>
                            <th class="pb-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, idx) in lines" :key="idx">
                        <tr class="border-b border-gray-100">
                            <td class="py-1.5 pr-2">
                                <input type="date" :name="`lines[${idx}][value_date]`" x-model="line.value_date"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-violet-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" :name="`lines[${idx}][label]`" x-model="line.label" placeholder="Libellé..."
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-violet-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" :name="`lines[${idx}][reference]`" x-model="line.reference" placeholder="Réf."
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-violet-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="number" :name="`lines[${idx}][debit]`" x-model.number="line.debit" min="0"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs text-right focus:ring-1 focus:ring-violet-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="number" :name="`lines[${idx}][credit]`" x-model.number="line.credit" min="0"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs text-right focus:ring-1 focus:ring-violet-500">
                            </td>
                            <td class="py-1.5">
                                <button type="button" @click="removeLine(idx)"
                                        class="text-red-400 hover:text-red-600 text-xs">✕</button>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-2 py-2 text-xs text-gray-500 font-semibold uppercase text-right">Totaux</td>
                            <td class="px-2 py-2 text-right tabular-nums font-bold text-blue-700" x-text="fmt(totalDebit)"></td>
                            <td class="px-2 py-2 text-right tabular-nums font-bold text-green-700" x-text="fmt(totalCredit)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('comptabilite.rapprochement.index') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">
                Annuler
            </a>
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                Créer le rapprochement
            </button>
        </div>
    </form>
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
