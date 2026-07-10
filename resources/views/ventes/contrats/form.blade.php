@extends('layouts.erp')
@section('title', $contract ? 'Contrat ' . $contract->number : 'Nouveau contrat')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.contrats.index') }}" class="hover:text-gray-700">Contrats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $contract ? 'Modification' : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-2 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $tdIn = 'w-full h-7 px-1.5 border border-[#c3d3c9] rounded-[3px] text-[12.5px] bg-white focus:outline-none focus:border-emerald-600';
    $c    = $contract;
    $initLines = old('items', $c?->items->map(fn ($i) => [
        'product_id' => $i->product_id, 'designation' => $i->designation, 'unit' => $i->unit,
        'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price,
        'discount_percent' => (float) $i->discount_percent,
        'starts_at' => $i->starts_at?->toDateString(), 'ends_at' => $i->ends_at?->toDateString(),
    ])->values()->all() ?? [['product_id' => '', 'designation' => '', 'unit' => '', 'quantity' => '', 'unit_price' => '', 'discount_percent' => 0, 'starts_at' => '', 'ends_at' => '']]);
@endphp
<div class="space-y-4"
     x-data="{
        products: @js($products->map(fn ($p) => ['id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'price' => (float) $p->sale_price])->values()),
        clients: @js($clients->mapWithKeys(fn ($cl) => [$cl->id => ['name' => $cl->name, 'ifu' => $cl->ifu, 'phone' => $cl->phone, 'address' => trim(($cl->address ?? '') . ' ' . ($cl->city ?? ''))]])->all()),
        clientId: @js((string) old('client_id', $c->client_id ?? '')),
        lines: @js(array_values($initLines)),
        addLine() { this.lines.push({ product_id: '', designation: '', unit: '', quantity: '', unit_price: '', discount_percent: 0, starts_at: '', ends_at: '' }); },
        removeLine(i) { this.lines.splice(i, 1); if (!this.lines.length) this.addLine(); },
        pickProduct(line) {
            const p = this.products.find(p => p.id == line.product_id);
            if (p) { if (!line.designation) line.designation = p.name; if (!line.unit_price) line.unit_price = p.price; }
        },
        lineAmount(l) { return (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0) * (1 - (parseFloat(l.discount_percent) || 0) / 100); },
        get totalHT() { return this.lines.reduce((s, l) => s + this.lineAmount(l), 0); },
        get clientCard() { return this.clients[this.clientId] ?? null; },
        fmt(v) { return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     }">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">{{ $c ? 'Contrat : Modification' : 'Nouveau contrat' }}</h1>
            <p class="text-[11.5px] text-gray-500 font-mono">{{ $nextNumber }}{{ $c ? ' — ' . $c->description : '' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-ct" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('ventes.contrats.index') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3 py-2.5 rounded-[4px] text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form id="form-ct" method="POST" enctype="multipart/form-data" data-turbo="false"
          action="{{ $c ? route('ventes.contrats.update', $c) : route('ventes.contrats.store') }}" class="space-y-4">
        @csrf
        @if($c) @method('PUT') @endif

        {{-- ═══ Informations principales ═══ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Informations principales</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Contrat <span class="text-red-500">*</span></label>
                    <input type="text" value="{{ $nextNumber }}" disabled class="{{ $inp }} !bg-gray-50 font-mono text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Description <span class="text-red-500">*</span></label>
                    <input type="text" name="description" required maxlength="255" value="{{ old('description', $c->description ?? '') }}"
                           placeholder="Contrat de fourniture de tôles bac" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Date contrat <span class="text-red-500">*</span></label>
                    <input type="date" name="contract_date" required value="{{ old('contract_date', $c?->contract_date?->toDateString() ?? now()->toDateString()) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Statut <span class="text-red-500">*</span></label>
                    <select name="status" class="{{ $inp }}">
                        @foreach(['brouillon' => 'Brouillon', 'actif' => 'Actif', 'suspendu' => 'Suspendu', 'termine' => 'Terminé', 'annule' => 'Annulé'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('status', $c->status ?? 'brouillon') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $lbl }}">Type de contrat <span class="text-red-500">*</span></label>
                    <select name="contract_type" class="{{ $inp }}">
                        <option value="vente" @selected(old('contract_type', $c->contract_type ?? 'vente') === 'vente')>Vente</option>
                        <option value="achat" @selected(old('contract_type', $c->contract_type ?? '') === 'achat')>Achat</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                    <input type="text" name="currency_code" maxlength="10" value="{{ old('currency_code', $c->currency_code ?? 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                </div>
                <div>
                    <label class="{{ $lbl }}">Date début <span class="text-red-500">*</span></label>
                    <input type="date" name="starts_at" required value="{{ old('starts_at', $c?->starts_at?->toDateString() ?? now()->toDateString()) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Priorité</label>
                    <select name="priority" class="{{ $inp }}">
                        @foreach(['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('priority', $c->priority ?? 'normale') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $lbl }}">Client <span class="text-red-500">*</span></label>
                    <select name="client_id" x-model="clientId" class="{{ $inp }}">
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $cl)
                        <option value="{{ $cl->id }}" @selected(old('client_id', $c->client_id ?? '') == $cl->id)>{{ $cl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Représentant</label>
                    <select name="sales_rep_id" class="{{ $inp }}">
                        <option value="">— Sélectionner —</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(old('sales_rep_id', $c->sales_rep_id ?? '') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Date fin</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at', $c?->ends_at?->toDateString()) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Projet</label>
                    <input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference', $c->project_reference ?? '') }}" placeholder="PRJ-2026-0020" class="{{ $inp }} font-mono">
                </div>

                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="hidden" name="is_framework" value="0">
                        <input type="checkbox" name="is_framework" value="1" {{ old('is_framework', $c->is_framework ?? false) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Contrat cadre</span>
                    </label>
                </div>
                <div></div><div></div>
                <div>
                    <label class="{{ $lbl }}">Catégorie contrat</label>
                    <select name="category" class="{{ $inp }}">
                        <option value="">— Sélectionner —</option>
                        @foreach(['Fourniture industrielle', 'Prestation de services', 'Maintenance', 'Sous-traitance', 'Distribution', 'Autre'] as $cat)
                        <option value="{{ $cat }}" @selected(old('category', $c->category ?? '') === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ═══ Parties du contrat ═══ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Parties du contrat</div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                {{-- Carte client (dynamique) --}}
                <div class="border border-gray-200 rounded-[4px] bg-gray-50/60 p-3 text-[12.5px]">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Client (Acheteur)</p>
                    <template x-if="clientCard">
                        <div class="space-y-0.5 text-gray-700">
                            <p class="font-bold" x-text="clientCard.name"></p>
                            <p x-text="clientCard.address || '—'"></p>
                            <p x-show="clientCard.ifu">N° IFU : <span class="font-mono" x-text="clientCard.ifu"></span></p>
                            <p x-show="clientCard.phone">Téléphone : <span x-text="clientCard.phone"></span></p>
                        </div>
                    </template>
                    <p x-show="!clientCard" class="text-gray-400">Sélectionnez un client.</p>
                </div>
                {{-- Carte vendeur (société) --}}
                <div class="border border-gray-200 rounded-[4px] bg-gray-50/60 p-3 text-[12.5px]">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Fournisseur (Vendeur)</p>
                    <div class="space-y-0.5 text-gray-700">
                        <p class="font-bold">{{ $company->name }}</p>
                        <p>{{ trim(($company->address ?? '') . ' ' . ($company->city ?? '')) ?: '—' }}</p>
                        @if($company->ifu)<p>N° IFU : <span class="font-mono">{{ $company->ifu }}</span></p>@endif
                        @if($company->phone)<p>Téléphone : {{ $company->phone }}</p>@endif
                    </div>
                </div>
                {{-- Conditions --}}
                <div class="space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Conditions de paiement</label>
                        <select name="payment_terms" class="{{ $inp }}">
                            <option value="">— Sélectionner —</option>
                            @foreach(['Comptant', 'Virement 30 jours', 'Virement 30 jours fin de mois', 'Virement 60 jours', 'Traite 90 jours'] as $pt)
                            <option value="{{ $pt }}" @selected(old('payment_terms', $c->payment_terms ?? '') === $pt)>{{ $pt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Incoterm</label>
                        <select name="incoterm" class="{{ $inp }}">
                            <option value="">— Sélectionner —</option>
                            @foreach(['EXW – Usine (Ex Works)', 'FCA – Franco transporteur', 'CPT – Port payé jusqu\'à', 'DAP – Rendu au lieu', 'DDP – Rendu droits acquittés'] as $ic)
                            <option value="{{ $ic }}" @selected(old('incoterm', $c->incoterm ?? '') === $ic)>{{ $ic }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Entrepôt par défaut</label>
                        <select name="warehouse_id" class="{{ $inp }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" @selected(old('warehouse_id', $c->warehouse_id ?? '') == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Devise facturation</label>
                        <input type="text" name="billing_currency" maxlength="10" value="{{ old('billing_currency', $c->billing_currency ?? 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Contact client</label>
                        <input type="text" name="client_contact" maxlength="100" value="{{ old('client_contact', $c->client_contact ?? '') }}" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Contact fournisseur</label>
                        <input type="text" name="supplier_contact" maxlength="100" value="{{ old('supplier_contact', $c->supplier_contact ?? 'Service commercial') }}" class="{{ $inp }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ Éléments contractuels (lignes) ═══ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between {{ $secH }}">
                <span>Éléments contractuels (lignes)</span>
                <button type="button" @click="addLine()" class="text-[12px] font-semibold text-emerald-700 hover:underline normal-case">+ Ajouter une ligne</button>
            </div>
            <table class="w-full text-[12.5px]">
                <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                    <th class="{{ $th }} text-center w-10">Ligne</th>
                    <th class="{{ $th }} text-left w-56">Article</th>
                    <th class="{{ $th }} text-left">Désignation</th>
                    <th class="{{ $th }} text-left w-16">Unité</th>
                    <th class="{{ $th }} text-right w-28">Qté contractuelle</th>
                    <th class="{{ $th }} text-right w-24">Prix unitaire</th>
                    <th class="{{ $th }} text-right w-20">Remise (%)</th>
                    <th class="{{ $th }} text-right w-32">Montant HT</th>
                    <th class="{{ $th }} text-left w-32">Date début</th>
                    <th class="{{ $th }} text-left w-32">Date fin</th>
                    <th class="{{ $th }} w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(line, i) in lines" :key="i">
                        <tr class="border-b border-gray-100">
                            <td class="px-2 py-1 text-center text-gray-400 tabular-nums" x-text="i + 1"></td>
                            <td class="px-2 py-1">
                                <select :name="'items[' + i + '][product_id]'" x-model="line.product_id" @change="pickProduct(line)" class="{{ $tdIn }}">
                                    <option value="">— Libre —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="(p.reference ? p.reference + ' — ' : '') + p.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-2 py-1"><input type="text" :name="'items[' + i + '][designation]'" x-model="line.designation" maxlength="255" class="{{ $tdIn }}"></td>
                            <td class="px-2 py-1"><input type="text" :name="'items[' + i + '][unit]'" x-model="line.unit" maxlength="20" placeholder="ML" class="{{ $tdIn }} font-mono uppercase"></td>
                            <td class="px-2 py-1"><input type="number" step="0.001" min="0" :name="'items[' + i + '][quantity]'" x-model="line.quantity" class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1"><input type="number" step="0.01" min="0" :name="'items[' + i + '][unit_price]'" x-model="line.unit_price" class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1"><input type="number" step="0.01" min="0" max="100" :name="'items[' + i + '][discount_percent]'" x-model="line.discount_percent" class="{{ $tdIn }} text-right font-mono"></td>
                            <td class="px-2 py-1 text-right font-mono tabular-nums font-semibold text-gray-800" x-text="fmt(lineAmount(line))"></td>
                            <td class="px-2 py-1"><input type="date" :name="'items[' + i + '][starts_at]'" x-model="line.starts_at" class="{{ $tdIn }}"></td>
                            <td class="px-2 py-1"><input type="date" :name="'items[' + i + '][ends_at]'" x-model="line.ends_at" class="{{ $tdIn }}"></td>
                            <td class="px-2 py-1 text-center">
                                <button type="button" @click="removeLine(i)" class="text-gray-300 hover:text-red-500 text-[15px] leading-none">×</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="flex items-center justify-end gap-3 px-3 py-1.5 border-t border-gray-100">
                <span class="text-[12px] text-gray-500">Total lignes HT</span>
                <span class="text-[16px] font-bold font-mono tabular-nums text-emerald-800" x-text="fmt(totalHT)"></span>
                <span class="text-[12px] text-gray-500">{{ old('currency_code', $c->currency_code ?? 'XOF') }}</span>
            </div>
        </div>

        {{-- ═══ Informations complémentaires | Pièces jointes | Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="{{ $secH }}">Informations complémentaires</div>
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $lbl }}">Mode de transport</label>
                            <select name="transport_mode" class="{{ $inp }}">
                                <option value="">— Sélectionner —</option>
                                @foreach(['route' => 'Route', 'air' => 'Air', 'mer' => 'Mer', 'rail' => 'Rail', 'multimodal' => 'Multimodal'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('transport_mode', $c->transport_mode ?? 'route') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Durée de validité (jours)</label>
                            <input type="number" name="validity_days" min="1" max="3650" value="{{ old('validity_days', $c->validity_days ?? 30) }}" class="{{ $inp }} text-right font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Observations</label>
                        <textarea name="observations" rows="3" maxlength="1000" placeholder="Livraison par lots mensuels selon planning convenu."
                                  class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">{{ old('observations', $c->observations ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="{{ $secH }}">Pièces jointes</div>
                <div class="p-4 space-y-2">
                    <label class="inline-flex items-center gap-2 text-[12.5px] font-semibold text-emerald-700 border border-dashed border-emerald-400 rounded-[4px] px-3 py-1.5 cursor-pointer hover:bg-emerald-50 transition-colors">
                        + Ajouter un fichier
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="hidden">
                    </label>
                    @if($c && $c->attachments->isNotEmpty())
                    <ul class="divide-y divide-gray-100 text-[12.5px]">
                        @foreach($c->attachments as $att)
                        <li class="flex items-center justify-between py-1.5">
                            <span class="truncate text-gray-700">📄 {{ $att->filename }}</span>
                            <span class="text-[11px] text-gray-400 flex-shrink-0 ml-2">{{ number_format($att->size / 1024) }} KB</span>
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-[12px] text-gray-400">Conditions générales, annexes techniques… (PDF, images, Office — 5 Mo max.)</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="{{ $secH }}">Traçabilité</div>
                <div class="p-4 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Créé le</p>
                        <p class="text-[13px] font-mono tabular-nums text-gray-700">{{ $c?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Par</p>
                        <p class="text-[13px] text-gray-700 truncate">{{ $c?->creator?->name ?? auth()->user()->name }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Modifié le</p>
                        <p class="text-[13px] font-mono tabular-nums text-gray-700">{{ $c?->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Statut</p>
                        @php $st = old('status', $c->status ?? 'brouillon'); @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ ['brouillon' => 'bg-gray-100 text-gray-600', 'actif' => 'bg-emerald-100 text-emerald-800', 'suspendu' => 'bg-amber-100 text-amber-700', 'termine' => 'bg-blue-100 text-blue-700', 'annule' => 'bg-red-100 text-red-700'][$st] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($st) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection
