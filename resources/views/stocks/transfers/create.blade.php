@extends('layouts.erp')
@section('title', 'Transfert inter-dépôts : Création')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.transfers.index') }}" class="hover:text-gray-700">Transferts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-2 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $tdIn = 'w-full h-7 px-1.5 border border-[#c3d3c9] rounded-[3px] text-[12.5px] bg-white focus:outline-none focus:border-emerald-600';
@endphp
<div class="space-y-4"
     x-data="{
        products: @js($products->map(fn ($p) => ['id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'weight' => (float) ($p->weight ?? 0)])->values()),
        lines: [{ product_id: '', requested_quantity: '', quantity: '', weight: '', volume: '', lot_number: '' }],
        addLine() { this.lines.push({ product_id: '', requested_quantity: '', quantity: '', weight: '', volume: '', lot_number: '' }); },
        removeLine(i) { this.lines.splice(i, 1); if (!this.lines.length) this.addLine(); },
        pName(id) { return this.products.find(p => p.id == id)?.name ?? ''; },
        pickWeight(l) { const p = this.products.find(p => p.id == l.product_id); if (p && !l.weight) l.weight = p.weight; },
        get totalQty() { return this.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0); },
        get totalWeight() { return this.lines.reduce((s, l) => s + (parseFloat(l.weight) || 0), 0); },
        get totalVolume() { return this.lines.reduce((s, l) => s + (parseFloat(l.volume) || 0), 0); },
        fmt(v) { return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 3 }); }
     }">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Transfert inter-dépôts : Création</h1>
            <p class="text-[11.5px] text-gray-500 font-mono">{{ $nextNumber }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-trf" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('stocks.transfers.index') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</a>
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

    <form id="form-trf" method="POST" action="{{ route('stocks.transfers.store') }}" class="space-y-4">
        @csrf

        {{-- Informations générales --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Informations générales</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Transfert n°</label>
                    <input type="text" value="{{ $nextNumber }}" disabled class="{{ $inp }} !bg-gray-50 font-mono text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Statut</label>
                    <input type="text" value="Brouillon" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Date transfert <span class="text-red-500">*</span></label>
                    <input type="date" name="transfer_date" required value="{{ old('transfer_date', now()->toDateString()) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Heure</label>
                    <input type="time" name="transfer_time" value="{{ old('transfer_time', now()->format('H:i')) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Entrepôt expéditeur <span class="text-red-500">*</span></label>
                    <select name="from_warehouse_id" required class="{{ $inp }}">
                        <option value="">— Sélectionner —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('from_warehouse_id') == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Entrepôt destinataire <span class="text-red-500">*</span></label>
                    <select name="to_warehouse_id" required class="{{ $inp }}">
                        <option value="">— Sélectionner —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('to_warehouse_id') == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Type de transfert</label>
                    <select name="type" class="{{ $inp }}">
                        @foreach(['standard' => 'Standard', 'urgent' => 'Urgent', 'retour' => 'Retour', 'regularisation' => 'Régularisation'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', 'standard') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Priorité</label>
                    <select name="priority" class="{{ $inp }}">
                        @foreach(['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('priority', 'normale') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Motif du transfert <span class="text-red-500">*</span></label>
                    <input type="text" name="reason" required maxlength="255" value="{{ old('reason') }}" placeholder="Approvisionnement production" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Référence / Document source</label>
                    <input type="text" name="reference" maxlength="80" value="{{ old('reference') }}" placeholder="APPRO-OF-…" class="{{ $inp }} font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Date document source</label>
                    <input type="date" name="source_document_date" value="{{ old('source_document_date') }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Responsable magasin</label>
                    <select name="responsible_id" class="{{ $inp }}">
                        <option value="">—</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(old('responsible_id') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 md:col-span-4">
                    <label class="{{ $lbl }}">Commentaire</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="{{ $inp }}" placeholder="Transfert pour approvisionnement ligne de production…">
                </div>
            </div>
        </div>

        {{-- Informations complémentaires (transport) --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Informations complémentaires</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-x-5 gap-y-3">
                <div><label class="{{ $lbl }}">Transporteur</label><input type="text" name="carrier" maxlength="60" value="{{ old('carrier') }}" class="{{ $inp }}"></div>
                <div>
                    <label class="{{ $lbl }}">Mode de transport</label>
                    <select name="transport_mode" class="{{ $inp }}">
                        <option value="">—</option>
                        @foreach(['interne' => 'Interne', 'externe' => 'Externe', 'messagerie' => 'Messagerie'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('transport_mode', 'interne') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="{{ $lbl }}">Véhicule / Matériel</label><input type="text" name="vehicle" maxlength="40" value="{{ old('vehicle') }}" class="{{ $inp }} font-mono"></div>
                <div><label class="{{ $lbl }}">Chauffeur</label><input type="text" name="driver" maxlength="60" value="{{ old('driver') }}" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">Date prévue</label><input type="date" name="planned_date" value="{{ old('planned_date') }}" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">Heure prévue</label><input type="time" name="planned_time" value="{{ old('planned_time') }}" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">Coût de transport</label><input type="number" step="0.01" min="0" name="transport_cost" value="{{ old('transport_cost', 0) }}" class="{{ $inp }} text-right font-mono"></div>
                <div>
                    <label class="{{ $lbl }}">Groupage</label>
                    <select name="grouping" class="{{ $inp }}">
                        <option value="0" @selected(!old('grouping'))>Non</option>
                        <option value="1" @selected(old('grouping'))>Oui</option>
                    </select>
                </div>
                <div><label class="{{ $lbl }}">Nombre de colis</label><input type="number" min="0" name="packages_count" value="{{ old('packages_count') }}" class="{{ $inp }} text-right font-mono"></div>
                <div><label class="{{ $lbl }}">Poids total (kg)</label><input type="number" step="0.001" min="0" name="total_weight" x-bind:value="totalWeight ? totalWeight.toFixed(3) : ''" class="{{ $inp }} text-right font-mono"></div>
                <div><label class="{{ $lbl }}">Volume total (m³)</label><input type="number" step="0.001" min="0" name="total_volume" x-bind:value="totalVolume ? totalVolume.toFixed(3) : ''" class="{{ $inp }} text-right font-mono"></div>
            </div>
        </div>

        {{-- Lignes du transfert --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between {{ $secH }}">
                <span>Lignes du transfert</span>
                <button type="button" @click="addLine()" class="text-[12px] font-semibold text-emerald-700 hover:underline normal-case">+ Ajouter une ligne</button>
            </div>
            <table class="w-full text-[12.5px]">
                <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                    <th class="{{ $th }} text-center w-10">Ligne</th>
                    <th class="{{ $th }} text-left w-56">Article</th>
                    <th class="{{ $th }} text-left">Désignation</th>
                    <th class="{{ $th }} text-left w-32">Lot / N° série</th>
                    <th class="{{ $th }} text-right w-28">Qté demandée</th>
                    <th class="{{ $th }} text-right w-28">Qté à transférer</th>
                    <th class="{{ $th }} text-right w-24">Poids (kg)</th>
                    <th class="{{ $th }} text-right w-24">Volume (m³)</th>
                    <th class="{{ $th }} w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(line, i) in lines" :key="i">
                        <tr class="border-b border-gray-100">
                            <td class="px-2 py-1 text-center text-gray-400 tabular-nums" x-text="i + 1"></td>
                            <td class="px-2 py-1">
                                <select :name="'items[' + i + '][product_id]'" x-model="line.product_id" @change="pickWeight(line)" required class="{{ $tdIn }}">
                                    <option value="">— Article —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="(p.reference ? p.reference + ' — ' : '') + p.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-2 py-1 text-gray-600 truncate max-w-[220px]" x-text="pName(line.product_id)"></td>
                            <td class="px-2 py-1"><input type="text" :name="'items[' + i + '][lot_number]'" x-model="line.lot_number" maxlength="100" class="{{ $tdIn }} font-mono" placeholder="—"></td>
                            <td class="px-2 py-1"><input type="number" step="0.001" min="0" :name="'items[' + i + '][requested_quantity]'" x-model="line.requested_quantity" class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1"><input type="number" step="0.001" min="0.001" :name="'items[' + i + '][quantity]'" x-model="line.quantity" required class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1"><input type="number" step="0.001" min="0" :name="'items[' + i + '][weight]'" x-model="line.weight" class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1"><input type="number" step="0.001" min="0" :name="'items[' + i + '][volume]'" x-model="line.volume" class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1 text-center"><button type="button" @click="removeLine(i)" class="text-gray-300 hover:text-red-500 text-[15px] leading-none">×</button></td>
                        </tr>
                    </template>
                </tbody>
                <tfoot><tr class="border-t border-gray-300 bg-[#f7faf8]">
                    <td colspan="5" class="px-2 py-2 text-right text-[12px] font-semibold text-gray-600">Total</td>
                    <td class="px-2 py-2 text-right font-mono tabular-nums font-bold text-emerald-800" x-text="fmt(totalQty)"></td>
                    <td class="px-2 py-2 text-right font-mono tabular-nums font-bold text-emerald-800" x-text="fmt(totalWeight)"></td>
                    <td class="px-2 py-2 text-right font-mono tabular-nums font-bold text-emerald-800" x-text="fmt(totalVolume)"></td>
                    <td></td>
                </tr></tfoot>
            </table>
        </div>

    </form>

    <p class="text-[11.5px] text-gray-400 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Le transfert est créé en brouillon. La sortie du dépôt expéditeur et l'entrée au dépôt destinataire sont générées à l'expédition / réception.
    </p>

</div>
@endsection
