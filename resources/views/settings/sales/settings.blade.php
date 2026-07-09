@extends('layouts.erp')
@section('title', 'Paramètres généraux vente')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('settings.sales.hub') }}" class="hover:text-gray-700">Paramétrage Vente</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Paramètres généraux</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $sw   = "relative w-9 h-5 flex-shrink-0 bg-gray-200 peer-checked:bg-emerald-600 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:bg-white after:rounded-full after:shadow after:transition-transform peer-checked:after:translate-x-4";
    $s    = $settings;
@endphp
<div class="max-w-4xl space-y-4">

    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Paramètres généraux vente</h1>
            <p class="text-[11.5px] text-gray-500">Règles globales appliquées à tout le cycle commercial</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-ss" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('settings.sales.hub') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Retour</a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3 py-2.5 rounded-[4px] text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form id="form-ss" method="POST" action="{{ route('settings.sales.settings.update') }}" class="space-y-4">
        @csrf @method('PUT')

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Comportements du cycle commercial</div>
            <div class="divide-y divide-gray-100">
                @foreach([
                    'reserve_stock_on_quote'     => ['Réservation de stock au devis', 'Le devis pose une réservation temporaire (levée à expiration).'],
                    'allow_direct_invoicing'     => ['Facturation directe autorisée', 'Une facture sans BL préalable décrémente le stock elle-même.'],
                    'enforce_price_floor'        => ['Prix plancher strict', 'Toute vente sous le prix plancher exige une validation DG/DAF.'],
                    'block_sales_on_overdue'     => ['Bloquer les clients en retard de paiement', 'Un client avec factures échues impayées ne peut plus commander.'],
                    'require_order_for_delivery' => ['Commande obligatoire avant livraison', 'Aucun BL ne peut être créé sans commande validée.'],
                ] as $key => [$label, $desc])
                <label class="flex items-start justify-between gap-4 cursor-pointer px-3 py-2.5 hover:bg-gray-50">
                    <span>
                        <span class="block text-[13px] font-medium text-gray-700">{{ $label }}</span>
                        <span class="block text-[11.5px] text-gray-400 mt-0.5">{{ $desc }}</span>
                    </span>
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" {{ old($key, $s->{$key}) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Seuils & valeurs par défaut</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Seuil remise → validation (%)</label>
                    <input type="number" name="discount_validation_threshold" step="0.01" min="0" max="100" required
                           value="{{ old('discount_validation_threshold', $s->discount_validation_threshold) }}" class="{{ $inp }} text-right font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Marge minimale défaut (%)</label>
                    <input type="number" name="default_margin_min" step="0.01" min="0" max="100"
                           value="{{ old('default_margin_min', $s->default_margin_min) }}" class="{{ $inp }} text-right font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Validité des devis (jours)</label>
                    <input type="number" name="quote_validity_days" min="1" max="365" required
                           value="{{ old('quote_validity_days', $s->quote_validity_days) }}" class="{{ $inp }} text-right font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Dépôt de vente par défaut</label>
                    <select name="default_sales_warehouse_id" class="{{ $inp }}">
                        <option value="">— Aucun —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('default_sales_warehouse_id', $s->default_sales_warehouse_id) == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Mentions de bas de page</div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $lbl }}">Pied de page devis</label>
                    <textarea name="quote_footer_note" rows="3" maxlength="1000"
                              class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600">{{ old('quote_footer_note', $s->quote_footer_note) }}</textarea>
                </div>
                <div>
                    <label class="{{ $lbl }}">Pied de page facture</label>
                    <textarea name="invoice_footer_note" rows="3" maxlength="1000"
                              class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600">{{ old('invoice_footer_note', $s->invoice_footer_note) }}</textarea>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection
