@extends('layouts.erp')
@section('title', 'Nouvelle prévision de trésorerie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.previsions.index') }}" class="hover:text-gray-700">Prévisions</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<div x-data="previsionForm()" class="space-y-3 max-w-5xl">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle prévision de trésorerie</h1>
        <a href="{{ route('tresorerie.previsions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('tresorerie.previsions.store') }}" class="space-y-3">
        @csrf

        {{-- Header --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Informations</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
                    <input type="text" name="label" value="{{ old('label') }}" required placeholder="ex: Janvier 2026"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de période</label>
                    <select name="period_type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="mensuel">Mensuel</option>
                        <option value="trimestriel">Trimestriel</option>
                        <option value="annuel">Annuel</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Solde ouverture (FCFA)</label>
                    <input type="number" name="opening_balance" value="{{ old('opening_balance', $openingBalance) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-0.5">Auto-calculé depuis les comptes actifs</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Du <span class="text-red-500">*</span></label>
                    <input type="date" name="period_start" value="{{ old('period_start') }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Au <span class="text-red-500">*</span></label>
                    <input type="date" name="period_end" value="{{ old('period_end') }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        {{-- Inflows --}}
        <div class="bg-white rounded-[4px] border border-green-200 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-green-700 uppercase tracking-wide">Encaissements prévus</h2>
                <button type="button" @click="addLine('encaissements_clients', true)"
                        class="text-xs bg-green-50 hover:bg-green-100 text-green-700 font-medium px-3 py-1.5 rounded-[4px]">+ Ligne</button>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-200">
                    <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Catégorie</th>
                    <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                    <th class="pb-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-36">Montant prévu</th>
                    <th class="pb-2 w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(line, idx) in inflowLines" :key="'in-'+idx">
                    <tr class="border-b border-gray-100">
                        <td class="py-1.5 pr-2 w-52">
                            <input type="hidden" :name="`lines[${line.globalIdx}][category]`" :value="line.category">
                            <select x-model="line.category" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                                @foreach(\App\Models\CashFlowForecastLine::inflowCategories() as $cat)
                                <option value="{{ $cat }}">{{ \App\Models\CashFlowForecastLine::categoryLabel($cat) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1.5 pr-2">
                            <input type="text" :name="`lines[${line.globalIdx}][label]`" x-model="line.label" placeholder="Description..."
                                   class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                        </td>
                        <td class="py-1.5 pr-2">
                            <input type="number" :name="`lines[${line.globalIdx}][forecast_amount]`" x-model.number="line.amount" min="0"
                                   class="w-full border border-gray-300 rounded px-2 py-1 text-xs text-right focus:ring-1 focus:ring-emerald-500">
                        </td>
                        <td class="py-1.5">
                            <button type="button" @click="removeLine(line.globalIdx)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </td>
                    </tr>
                    </template>
                </tbody>
                <tfoot class="border-t-2 border-green-200 bg-green-50">
                    <tr>
                        <td colspan="2" class="px-2 py-2 text-xs font-semibold text-green-700 uppercase text-right">Total encaissements</td>
                        <td class="px-2 py-2 text-right tabular-nums font-bold text-green-700" x-text="fmt(totalInflows)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Outflows --}}
        <div class="bg-white rounded-[4px] border border-red-200 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-red-700 uppercase tracking-wide">Décaissements prévus</h2>
                <button type="button" @click="addLine('achats_fournisseurs', false)"
                        class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-medium px-3 py-1.5 rounded-[4px]">+ Ligne</button>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-200">
                    <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Catégorie</th>
                    <th class="pb-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                    <th class="pb-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-36">Montant prévu</th>
                    <th class="pb-2 w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(line, idx) in outflowLines" :key="'out-'+idx">
                    <tr class="border-b border-gray-100">
                        <td class="py-1.5 pr-2 w-52">
                            <input type="hidden" :name="`lines[${line.globalIdx}][category]`" :value="line.category">
                            <select x-model="line.category" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                                @foreach(\App\Models\CashFlowForecastLine::outflowCategories() as $cat)
                                <option value="{{ $cat }}">{{ \App\Models\CashFlowForecastLine::categoryLabel($cat) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1.5 pr-2">
                            <input type="text" :name="`lines[${line.globalIdx}][label]`" x-model="line.label" placeholder="Description..."
                                   class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500">
                        </td>
                        <td class="py-1.5 pr-2">
                            <input type="number" :name="`lines[${line.globalIdx}][forecast_amount]`" x-model.number="line.amount" min="0"
                                   class="w-full border border-gray-300 rounded px-2 py-1 text-xs text-right focus:ring-1 focus:ring-emerald-500">
                        </td>
                        <td class="py-1.5">
                            <button type="button" @click="removeLine(line.globalIdx)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </td>
                    </tr>
                    </template>
                </tbody>
                <tfoot class="border-t-2 border-red-200 bg-red-50">
                    <tr>
                        <td colspan="2" class="px-2 py-2 text-xs font-semibold text-red-700 uppercase text-right">Total décaissements</td>
                        <td class="px-2 py-2 text-right tabular-nums font-bold text-red-700" x-text="fmt(totalOutflows)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Summary bar --}}
        <div class="bg-[#eef5f0] border border-emerald-200 rounded-[4px] p-4 flex flex-wrap gap-6 items-center">
            <div class="text-center">
                <p class="text-xs text-emerald-700">Flux net prévu</p>
                <p class="text-[17px] font-bold tabular-nums" :class="netFlow >= 0 ? 'text-green-700' : 'text-red-700'"
                   x-text="(netFlow >= 0 ? '+' : '') + fmt(netFlow)"></p>
            </div>
            <div class="text-center">
                <p class="text-xs text-emerald-700">Solde clôture prévu</p>
                <p class="text-[17px] font-bold tabular-nums text-emerald-900" x-text="fmt(closingBalance)"></p>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 resize-none">{{ old('notes') }}</textarea>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('tresorerie.previsions.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">Créer la prévision</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function previsionForm() {
    const openingBalance = {{ $openingBalance }};
    return {
        lines: [],
        nextIdx: 0,
        get inflowLines() { return this.lines.filter(l => l.isInflow); },
        get outflowLines() { return this.lines.filter(l => !l.isInflow); },
        addLine(category, isInflow) {
            this.lines.push({ globalIdx: this.nextIdx++, category, label: '', amount: 0, isInflow });
        },
        removeLine(idx) { this.lines = this.lines.filter(l => l.globalIdx !== idx); },
        get totalInflows()  { return this.inflowLines.reduce((s, l)  => s + (l.amount || 0), 0); },
        get totalOutflows() { return this.outflowLines.reduce((s, l) => s + (l.amount || 0), 0); },
        get netFlow()        { return this.totalInflows - this.totalOutflows; },
        get closingBalance() { return openingBalance + this.netFlow; },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); },
        init() {
            this.addLine('encaissements_clients', true);
            this.addLine('achats_fournisseurs', false);
            this.addLine('salaires', false);
            this.addLine('charges_fiscales', false);
        },
    };
}
</script>
@endpush
@endsection
