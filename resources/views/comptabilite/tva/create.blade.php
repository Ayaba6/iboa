@extends('layouts.erp')
@section('title', 'Nouvelle déclaration TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.tva.index') }}" class="hover:text-gray-700">Déclarations TVA</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-violet-600 focus:ring-1 focus:ring-violet-400';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-violet-600 focus:ring-1 focus:ring-violet-400';
    $secH = 'px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12.5px] font-bold text-emerald-900';
@endphp

<div x-data="tvaForm()" class="space-y-3 max-w-4xl">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif

    {{-- Header bar --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-[15px] font-bold text-gray-900">Déclaration TVA — Nouvelle</h2>
            <p class="text-[11.5px] text-gray-400">Calcul automatique depuis les écritures ou saisie manuelle des montants.</p>
        </div>
        <div class="flex items-center gap-1.5">
            <button type="submit" form="tvaCreateForm" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('comptabilite.tva.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

    {{-- Period calculator --}}
    <section class="bg-white border border-violet-200 rounded-[4px]">
        <div class="px-3 py-1.5 border-b border-violet-100 bg-violet-50 text-[12.5px] font-bold text-violet-800 uppercase tracking-wide">Calcul automatique depuis les écritures</div>
        <div class="p-3">
            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="{{ $lbl }} text-violet-700">Du</label>
                    <input type="date" x-model="calcFrom" class="h-8 px-2 border border-violet-300 rounded-[3px] text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-violet-400">
                </div>
                <div>
                    <label class="{{ $lbl }} text-violet-700">Au</label>
                    <input type="date" x-model="calcTo" class="h-8 px-2 border border-violet-300 rounded-[3px] text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-violet-400">
                </div>
                <button type="button" @click="calculate()" :disabled="loading || !calcFrom || !calcTo"
                        class="h-8 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                    <span x-show="!loading">Calculer</span>
                    <span x-show="loading">Calcul…</span>
                </button>
            </div>
            <template x-if="result">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 mt-3">
                <div class="bg-gray-50 rounded-[4px] px-3 py-2 text-center border border-violet-100">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">TVA Collectée</p>
                    <p class="text-[15px] font-bold text-gray-800 tabular-nums leading-none mt-0.5" x-text="fmt(result.tvaCollectee)"></p>
                </div>
                <div class="bg-gray-50 rounded-[4px] px-3 py-2 text-center border border-violet-100">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">TVA Déductible</p>
                    <p class="text-[15px] font-bold text-gray-800 tabular-nums leading-none mt-0.5" x-text="fmt(result.tvaDeductible)"></p>
                </div>
                <div class="bg-gray-50 rounded-[4px] px-3 py-2 text-center border border-green-100">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">TVA Due</p>
                    <p class="text-[15px] font-bold tabular-nums leading-none mt-0.5" :class="result.tvaDue > 0 ? 'text-red-600' : 'text-gray-400'" x-text="fmt(result.tvaDue)"></p>
                </div>
                <div class="bg-gray-50 rounded-[4px] px-3 py-2 text-center border border-blue-100">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Crédit TVA</p>
                    <p class="text-[15px] font-bold tabular-nums leading-none mt-0.5" :class="result.creditTva > 0 ? 'text-blue-700' : 'text-gray-400'" x-text="fmt(result.creditTva)"></p>
                </div>
            </div>
            </template>
        </div>
    </section>

    <form id="tvaCreateForm" method="POST" action="{{ route('comptabilite.tva.store') }}" class="space-y-3">
        @csrf

        {{-- Déclaration --}}
        <section class="bg-white border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Déclaration</div>
            <div class="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-3 gap-y-2.5">
                <div>
                    <label class="{{ $lbl }}">Libellé période <span class="text-red-500">*</span></label>
                    <input type="text" name="period_label" value="{{ old('period_label') }}" required placeholder="ex : Janvier 2026" class="{{ $inp }}">
                </div>
                <div class="relative">
                    <label class="{{ $lbl }}">Type <span class="text-red-500">*</span></label>
                    <select name="period_type" required class="{{ $lk }}">
                        <option value="mensuel"     {{ old('period_type') === 'mensuel'     ? 'selected' : '' }}>Mensuel</option>
                        <option value="trimestriel" {{ old('period_type') === 'trimestriel' ? 'selected' : '' }}>Trimestriel</option>
                    </select>
                    <span class="absolute right-2 top-[27px] text-gray-500 pointer-events-none text-[11px]">&#9662;</span>
                </div>
                <div>
                    <label class="{{ $lbl }}">Date de déclaration <span class="text-red-500">*</span></label>
                    <input type="date" name="declaration_date" value="{{ old('declaration_date', date('Y-m-d')) }}" required class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Période du <span class="text-red-500">*</span></label>
                    <input type="date" name="period_start" :value="calcFrom || '{{ old('period_start') }}'" required class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Période au <span class="text-red-500">*</span></label>
                    <input type="date" name="period_end" :value="calcTo || '{{ old('period_end') }}'" required class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Date limite</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="{{ $inp }}">
                </div>
            </div>
        </section>

        {{-- Montants --}}
        <section class="bg-white border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Montants TVA (FCFA)</div>
            <div class="p-3">
                <p class="text-[11px] text-gray-500 mb-2">Laissez vide pour calculer automatiquement depuis les écritures comptables de la période.</p>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-x-3 gap-y-2.5">
                    <div>
                        <label class="{{ $lbl }}">TVA Collectée</label>
                        <input type="number" name="tva_collectee" :value="result ? result.tvaCollectee : '{{ old('tva_collectee') }}'" min="0" class="{{ $inp }} text-right tabular-nums">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">TVA Déductible</label>
                        <input type="number" name="tva_deductible" :value="result ? result.tvaDeductible : '{{ old('tva_deductible') }}'" min="0" class="{{ $inp }} text-right tabular-nums">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">TVA Due</label>
                        <input type="number" name="tva_due" :value="result ? result.tvaDue : '{{ old('tva_due') }}'" min="0" class="{{ $inp }} text-right tabular-nums">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Crédit TVA</label>
                        <input type="number" name="credit_tva" :value="result ? result.creditTva : '{{ old('credit_tva') }}'" min="0" class="{{ $inp }} text-right tabular-nums">
                    </div>
                </div>
            </div>
        </section>

        {{-- Notes --}}
        <section class="bg-white border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Notes</div>
            <div class="p-3">
                <textarea name="notes" rows="3" maxlength="2000" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-violet-600 focus:ring-1 focus:ring-violet-400 resize-none">{{ old('notes') }}</textarea>
            </div>
        </section>
    </form>
</div>

@push('scripts')
<script>
function tvaForm() {
    return {
        calcFrom: '{{ $dateFrom ?? '' }}',
        calcTo:   '{{ $dateTo   ?? '' }}',
        result: @json($calc),
        loading: false,
        async calculate() {
            if (!this.calcFrom || !this.calcTo) return;
            this.loading = true;
            try {
                const resp = await fetch('{{ route('comptabilite.tva.calculate') }}?' + new URLSearchParams({ date_from: this.calcFrom, date_to: this.calcTo }));
                const data = await resp.json();
                this.result = data.calc;
            } catch(e) { alert('Erreur de calcul.'); }
            this.loading = false;
        },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n ?? 0); },
    };
}
</script>
@endpush
@endsection
