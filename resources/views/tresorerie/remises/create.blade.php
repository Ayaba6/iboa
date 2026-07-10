@extends('layouts.erp')
@section('title', 'Nouvelle remise en banque')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.remises.index') }}" class="hover:text-gray-700">Remises</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<div x-data="remiseForm()" class="space-y-3 max-w-4xl">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle remise en banque</h1>
        <a href="{{ route('tresorerie.remises.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('tresorerie.remises.store') }}" class="space-y-3">
        @csrf

        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Informations</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte bancaire destinataire <span class="text-red-500">*</span></label>
                    <select name="cash_account_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">Sélectionner...</option>
                        @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->id }}" {{ old('cash_account_id') == $ba->id ? 'selected' : '' }}>{{ $ba->name }} ({{ $ba->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte source (caisse)</label>
                    <select name="source_cash_account_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucun —</option>
                        @foreach($caisseAccounts as $ca)
                        <option value="{{ $ca->id }}" {{ old('source_cash_account_id') == $ca->id ? 'selected' : '' }}>{{ $ca->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de remise <span class="text-red-500">*</span></label>
                    <input type="date" name="deposit_date" value="{{ old('deposit_date', date('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Référence bordereau</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" maxlength="100"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Valeurs remises</h2>
                <button type="button" @click="addItem()"
                        class="text-sm bg-[#eef5f0] hover:bg-emerald-100 text-emerald-800 font-medium px-3 py-1.5 rounded-[4px]">+ Ajouter</button>
            </div>

            {{-- Quick-add from effects --}}
            @if($availableEffects->isNotEmpty())
            <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-3">
                <p class="text-xs font-semibold text-amber-700 mb-2">Effets disponibles à remettre</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($availableEffects as $eff)
                    <button type="button"
                            @click="addEffect({{ $eff->id }}, '{{ addslashes($eff->number) }}', {{ $eff->amount }}, '{{ $eff->drawer }}', '{{ $eff->due_date?->format('Y-m-d') }}')"
                            class="text-xs bg-white border border-amber-300 hover:border-amber-500 text-amber-800 px-2 py-1 rounded-[4px]">
                        {{ $eff->number }} · {{ number_format($eff->amount, 0, ',', ' ') }} FCFA
                        @if($eff->due_date)· ech. {{ $eff->due_date->format('d/m') }}@endif
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200">
                        <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Type</th>
                        <th class="pb-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Montant</th>
                        <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Tireur</th>
                        <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Échéance</th>
                        <th class="pb-2 w-8"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(item, idx) in items" :key="idx">
                        <tr class="border-b border-gray-100">
                            <td class="py-1.5 pr-2">
                                <select :name="`items[${idx}][type]`" x-model="item.type"
                                        class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                                    <option value="especes">Espèces</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="effet">Effet</option>
                                    <option value="virement">Virement</option>
                                </select>
                                <input type="hidden" :name="`items[${idx}][commercial_effect_id]`" :value="item.effectId">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="number" :name="`items[${idx}][amount]`" x-model.number="item.amount" min="1"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs text-right focus:ring-1 focus:ring-emerald-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" :name="`items[${idx}][reference]`" x-model="item.reference" placeholder="N° chèque..."
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" :name="`items[${idx}][drawer]`" x-model="item.drawer" placeholder="Nom..."
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="date" :name="`items[${idx}][due_date]`" x-model="item.dueDate"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                            </td>
                            <td class="py-1.5">
                                <button type="button" @click="items.splice(idx, 1)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-2 py-2 text-xs font-semibold text-gray-600 uppercase text-right">Total remise</td>
                            <td class="px-2 py-2 text-right tabular-nums font-bold text-emerald-800" x-text="fmt(total)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('tresorerie.remises.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">Créer la remise</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function remiseForm() {
    return {
        items: [{ type: 'especes', amount: 0, reference: '', drawer: '', dueDate: '', effectId: null }],
        addItem() {
            this.items.push({ type: 'especes', amount: 0, reference: '', drawer: '', dueDate: '', effectId: null });
        },
        addEffect(id, number, amount, drawer, dueDate) {
            this.items.push({ type: 'effet', amount, reference: number, drawer, dueDate, effectId: id });
        },
        get total() { return this.items.reduce((s, i) => s + (i.amount || 0), 0); },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); },
    };
}
</script>
@endpush
@endsection
