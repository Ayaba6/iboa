@extends('layouts.erp')
@section('title', 'Mouvement manuel de stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.movements') }}" class="hover:text-gray-700">Mouvements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-2 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $tdIn = 'w-full h-7 px-1.5 border border-[#c3d3c9] rounded-[3px] text-[12.5px] bg-white focus:outline-none focus:border-emerald-600';
@endphp
<div class="space-y-4"
     x-data="{
        products: @js($products->map(fn ($p) => ['id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'cost' => (float) $p->weighted_avg_cost])->values()),
        stock: @js($stockMap),
        originId: '{{ old('warehouse_from_id') }}',
        destId: '{{ old('warehouse_to_id') }}',
        lines: [{ product_id: '', quantity: '', unit_cost: '', lot_number: '' }],
        addLine() { this.lines.push({ product_id: '', quantity: '', unit_cost: '', lot_number: '' }); },
        removeLine(i) { this.lines.splice(i, 1); if (!this.lines.length) this.addLine(); },
        productName(id) { return this.products.find(p => p.id == id)?.name ?? ''; },
        pickCost(line) {
            const p = this.products.find(p => p.id == line.product_id);
            if (p && (line.unit_cost === '' || line.unit_cost === null)) line.unit_cost = p.cost;
        },
        sourceWid() { return this.originId || this.destId || ''; },
        available(line) {
            const wid = this.sourceWid();
            if (!line.product_id || !wid) return null;
            return this.stock?.[line.product_id]?.[wid] ?? 0;
        },
        shortfall(line) {
            const av = this.available(line);
            const q = parseFloat(line.quantity) || 0;
            return (av !== null && q < 0 && Math.abs(q) > av);
        },
        lineValue(l) { return (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0); },
        get total() { return this.lines.reduce((s, l) => s + this.lineValue(l), 0); },
        fmt(v) { return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     }">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Enregistrer un mouvement manuel de stock</h1>
            <p class="text-[11.5px] text-gray-500 font-mono">{{ $nextNumber }} — quantité positive = entrée, négative = sortie</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-mvt" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('stocks.movements') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</a>
        </div>
    </div>

    @if(session('error'))
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form id="form-mvt" method="POST" action="{{ route('stocks.movement.store') }}" class="space-y-4">
        @csrf

        {{-- ═══ Informations générales ═══ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Informations générales</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Type de mouvement <span class="text-red-500">*</span></label>
                    <input type="text" value="MAN — Mouvement manuel" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Date mouvement <span class="text-red-500">*</span></label>
                    <input type="date" name="occurred_at" required value="{{ old('occurred_at', now()->toDateString()) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Heure</label>
                    <input type="time" name="occurred_time" value="{{ old('occurred_time', now()->format('H:i')) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Statut</label>
                    <input type="text" value="Saisi" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Référence</label>
                    <input type="text" value="{{ $nextNumber }}" disabled class="{{ $inp }} !bg-gray-50 font-mono text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Devise</label>
                    <input type="text" value="XOF — Franc CFA" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
                </div>
            </div>
        </div>

        {{-- ═══ Origine / Destination ═══ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Origine / Destination</div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Dépôt origine <span class="font-normal text-gray-400">(sorties)</span></label>
                    <select name="warehouse_from_id" x-model="originId" class="{{ $inp }}">
                        <option value="">— Identique à destination —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('warehouse_from_id') == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Dépôt destination <span class="text-red-500">*</span> <span class="font-normal text-gray-400">(entrées)</span></label>
                    <select name="warehouse_to_id" x-model="destId" required class="{{ $inp }}">
                        <option value="">— Sélectionner —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('warehouse_to_id') == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Motif mouvement <span class="text-red-500">*</span></label>
                    <select name="reason" required class="{{ $inp }}">
                        @foreach(['ajustement' => 'ADJ — Ajustement de stock', 'correction' => 'COR — Correction de saisie', 'don' => 'DON — Don / échantillon', 'perte' => 'PER — Perte', 'casse' => 'CAS — Casse', 'autre' => 'AUT — Autre'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('reason', 'ajustement') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Emplacement origine</label>
                    <input type="text" name="location_from" maxlength="50" value="{{ old('location_from') }}" placeholder="R01" class="{{ $inp }} font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Emplacement destination</label>
                    <input type="text" name="location_to" maxlength="50" value="{{ old('location_to') }}" placeholder="R02" class="{{ $inp }} font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Commentaire</label>
                    <input type="text" name="comment" maxlength="500" value="{{ old('comment') }}" placeholder="Ajustement suite inventaire physique" class="{{ $inp }}">
                </div>
            </div>
        </div>

        {{-- ═══ Articles ═══ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Articles</div>
            <table class="w-full text-[12.5px]">
                <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                    <th class="{{ $th }} text-center w-10">Ligne</th>
                    <th class="{{ $th }} text-left w-64">Article</th>
                    <th class="{{ $th }} text-left">Désignation</th>
                    <th class="{{ $th }} text-left w-36">Lot / N° série</th>
                    <th class="{{ $th }} text-right w-28">Qté (±)</th>
                    <th class="{{ $th }} text-right w-28">Prix unitaire</th>
                    <th class="{{ $th }} text-right w-32">Valeur</th>
                    <th class="{{ $th }} w-10"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(line, i) in lines" :key="i">
                        <tr class="border-b border-gray-100">
                            <td class="px-2 py-1 text-center text-gray-400 tabular-nums" x-text="i + 1"></td>
                            <td class="px-2 py-1">
                                <select :name="'items[' + i + '][product_id]'" x-model="line.product_id" @change="pickCost(line)" required class="{{ $tdIn }}">
                                    <option value="">— Article —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="(p.reference ? p.reference + ' — ' : '') + p.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-2 py-1 text-gray-600 truncate max-w-[220px]" x-text="productName(line.product_id)"></td>
                            <td class="px-2 py-1">
                                <input type="text" :name="'items[' + i + '][lot_number]'" x-model="line.lot_number" maxlength="100" class="{{ $tdIn }} font-mono" placeholder="—">
                            </td>
                            <td class="px-2 py-1 align-top">
                                <input type="number" step="0.001" :name="'items[' + i + '][quantity]'" x-model="line.quantity" required
                                       class="{{ $tdIn }} text-right font-mono"
                                       :class="shortfall(line) ? '!border-red-500 !text-red-600 font-semibold bg-red-50' : (parseFloat(line.quantity) < 0 ? '!text-red-600 font-semibold' : (parseFloat(line.quantity) > 0 ? '!text-emerald-700 font-semibold' : ''))">
                                <template x-if="available(line) !== null">
                                    <p class="text-[10px] text-right mt-0.5 font-mono tabular-nums"
                                       :class="shortfall(line) ? 'text-red-600 font-semibold' : 'text-gray-400'"
                                       x-text="(shortfall(line) ? '⚠ ' : '') + 'dispo : ' + fmt(available(line))"></p>
                                </template>
                            </td>
                            <td class="px-2 py-1">
                                <input type="number" step="0.01" min="0" :name="'items[' + i + '][unit_cost]'" x-model="line.unit_cost" class="{{ $tdIn }} text-right font-mono">
                            </td>
                            <td class="px-2 py-1 text-right font-mono tabular-nums" :class="lineValue(line) < 0 ? 'text-red-600' : 'text-gray-700'" x-text="fmt(lineValue(line))"></td>
                            <td class="px-2 py-1 text-center">
                                <button type="button" @click="removeLine(i)" class="text-gray-300 hover:text-red-500 text-[15px] leading-none" title="Supprimer la ligne">×</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="flex items-center justify-between px-3 py-1.5 border-t border-gray-100">
                <button type="button" @click="addLine()" class="text-[12.5px] font-semibold text-emerald-700 hover:underline">+ Ajouter</button>
                <p class="text-[13px]"><span class="text-gray-500 mr-2">Total</span><span class="font-bold font-mono tabular-nums text-emerald-800" x-text="fmt(total)"></span></p>
            </div>
        </div>

        {{-- ═══ Informations complémentaires | Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-2">
                <div class="{{ $secH }}">Informations complémentaires</div>
                <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                    <div>
                        <label class="{{ $lbl }}">Projet</label>
                        <input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference') }}" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Transaction</label>
                        <input type="text" value="STO — Stock" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Section analytique</label>
                        <input type="text" name="analytic_section" maxlength="60" value="{{ old('analytic_section') }}" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Date comptable</label>
                        <input type="date" name="accounting_date" value="{{ old('accounting_date', now()->toDateString()) }}" class="{{ $inp }}">
                    </div>
                    <div class="col-span-2">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer mt-1">
                            <input type="hidden" name="is_blocked" value="0">
                            <input type="checkbox" name="is_blocked" value="1" {{ old('is_blocked') ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-[13px] font-medium text-gray-700">Mouvement bloqué</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="{{ $secH }}">Traçabilité</div>
                <div class="p-4 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Créé le</p>
                        <p class="text-[13px] font-mono tabular-nums text-gray-700">{{ now()->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Par</p>
                        <p class="text-[13px] text-gray-700 truncate">{{ auth()->user()->name }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[11px] text-gray-400">Chaque ligne génère un mouvement journalisé (sync_logs) — visible dans Mouvements et /admin/synchronisations.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <p class="text-[11.5px] text-gray-400 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Quantité positive = ajustement d'entrée (dépôt destination). Quantité négative = ajustement de sortie (dépôt origine, sinon destination) — bloqué si le stock disponible est insuffisant.
    </p>

</div>
@endsection
