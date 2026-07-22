@extends('layouts.erp')
@section('title', 'Catégorie ' . $category->code)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('articles.categories.index') }}" class="hover:text-gray-700">Catégories</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $category->code }}</span>
@endsection

@section('content')
@php
    $oui = fn ($v) => $v ? '<span class="text-emerald-600 font-bold">Oui</span>' : '<span class="text-gray-400">Non</span>';
    $row = fn ($l, $v) => '<div><dt class="text-[10.5px] font-bold text-gray-500 uppercase">' . $l . '</dt><dd class="text-[13px] text-gray-900">' . ($v !== null && $v !== '' ? $v : '—') . '</dd></div>';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase';
@endphp
<div class="space-y-3" x-data="{ tab: 'gestion' }">

    {{-- ═══ Bandeau SAGE X3 (même squelette que le module familles) ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">
                    Catégories : Fiche
                    <span class="font-mono text-emerald-700 text-[18px] ml-1">{{ $category->code }}</span>
                    @unless($category->is_active)<span class="text-[13px] font-semibold text-gray-400 italic ml-2">(inactive)</span>@endunless
                </h2>
                <p class="text-[11.5px] text-gray-400">{{ $category->name }}{{ $category->description ? ' — ' . $category->description : '' }}</p>
            </div>
            <div class="flex items-center gap-1.5">
                @can('categories.update')
                <a href="{{ route('articles.categories.edit', $category) }}"
                   class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Modifier</a>
                @endcan
                @can('categories.disable')
                <form method="POST" action="{{ route('articles.categories.disable', $category) }}">@csrf
                    <button class="text-[14px] font-semibold border px-5 py-2 rounded-[4px] transition-colors {{ $category->is_active ? 'text-red-700 border-red-300 bg-white hover:bg-red-50' : 'text-emerald-700 border-emerald-300 bg-white hover:bg-emerald-50' }}">{{ $category->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                </form>
                @endcan
                <a href="{{ route('articles.categories.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Fermer</a>
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="flex border-b border-gray-200 bg-gray-50 overflow-x-auto">
            @foreach(['gestion'=>'Gestion','stock'=>'Stock','vente'=>'Vente','achat'=>'Achat','production'=>'Production','compta'=>'Comptabilité','sites'=>'Sites ('.$category->sites->count().')','articles'=>'Articles ('.$category->products_count.')','propagation'=>'Propagation'] as $key => $label)
            <button type="button" @click="tab='{{ $key }}'"
                    class="px-4 py-2 text-[12.5px] font-semibold border-b-2 whitespace-nowrap"
                    :class="tab==='{{ $key }}' ? 'border-emerald-600 text-emerald-800 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab==='gestion'" class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            {!! $row('Nature', str_replace('_',' ',$category->nature)) !!}
            {!! $row('Stratégie', $category->strategy ? strtoupper($category->strategy) : null) !!}
            {!! $row('Acheté', $oui($category->is_purchasable)) !!}
            {!! $row('Vendu', $oui($category->is_sellable)) !!}
            {!! $row('Stocké', $oui($category->is_stockable)) !!}
            {!! $row('Fabriqué', $oui($category->is_manufactured)) !!}
            {!! $row('Sous-traité', $oui($category->is_subcontracted)) !!}
            {!! $row('Utilisable en nomenclature', $oui($category->usable_in_bom)) !!}
            {!! $row('Statut', $category->is_active ? 'Active' : 'Inactive') !!}
            {!! $row('Déclinable par site', $oui($category->site_declinable)) !!}
        </div>

        <div x-show="tab==='stock'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            {!! $row('Stock négatif', $oui($category->allow_negative_stock)) !!}
            {!! $row('Gestion par lot', $oui($category->lot_managed)) !!}
            {!! $row('Numéro de série', $oui($category->serial_managed)) !!}
            {!! $row('Gestion bobine', $oui($category->coil_managed)) !!}
            {!! $row('Péremption', $oui($category->expiry_managed)) !!}
            {!! $row('CQ à réception', $oui($category->qc_on_receipt)) !!}
            {!! $row('Valorisation', $category->valuation_method ? strtoupper($category->valuation_method) : null) !!}
            {!! $row('Stock mini défaut', $category->default_stock_min) !!}
            {!! $row('Stock maxi défaut', $category->default_stock_max) !!}
            {!! $row('Stock sécurité défaut', $category->default_stock_securite) !!}
        </div>

        <div x-show="tab==='vente'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            {!! $row('Unité de vente', $category->defaultSaleUnit?->name) !!}
            {!! $row('Unité de tarification', $category->defaultPricingUnit?->name) !!}
            {!! $row('TVA par défaut', $category->defaultTaxRate ? $category->defaultTaxRate->rate.' %' : null) !!}
            {!! $row('Exonération autorisée', $oui($category->exempt_allowed)) !!}
            {!! $row('Prix plancher obligatoire', $oui($category->floor_price_required)) !!}
            {!! $row('Remise max', $category->max_discount_percent !== null ? $category->max_discount_percent.' %' : null) !!}
            {!! $row('Acompte requis', $oui($category->deposit_required)) !!}
            {!! $row('Contrôle de crédit', $oui($category->credit_check)) !!}
        </div>

        <div x-show="tab==='achat'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            {!! $row('Unité d\'achat', $category->defaultPurchaseUnit?->name) !!}
            {!! $row('Tolérance réception', $category->receipt_tolerance_percent !== null ? $category->receipt_tolerance_percent.' %' : null) !!}
            {!! $row('Délai d\'appro (j)', $category->lead_time_days) !!}
        </div>

        <div x-show="tab==='production'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            {!! $row('Nomenclature obligatoire', $oui($category->bom_required)) !!}
            {!! $row('Gamme obligatoire', $oui($category->routing_required)) !!}
            {!! $row('OF automatique', $oui($category->auto_of)) !!}
            {!! $row('CQ obligatoire', $oui($category->qc_required)) !!}
            {!! $row('Perte de réglage', $category->setup_loss) !!}
            {!! $row('Taux de rebut prévisionnel', $category->scrap_rate_percent !== null ? $category->scrap_rate_percent.' %' : null) !!}
            {!! $row('Gestion des chutes', $oui($category->offcut_managed)) !!}
            {!! $row('Optimisation découpe', $oui($category->cutting_optimized)) !!}
            {!! $row('Planification MRP', $oui($category->mrp_planned)) !!}
        </div>

        <div x-show="tab==='compta'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            @foreach(['stock_account_id'=>'Compte stock','purchase_account_id'=>'Compte achat','sale_account_id'=>'Compte vente','variation_account_id'=>'Variation de stock','consumption_account_id'=>'Consommation','scrap_account_id'=>'Rebut','finished_account_id'=>'Produit fini'] as $col => $lbl)
                @php $acc = $category->$col ? \App\Models\Account::find($category->$col) : null; @endphp
                {!! $row($lbl, $acc ? $acc->code.' — '.$acc->name : null) !!}
            @endforeach
            {!! $row('Méthode de coût', $category->cost_method) !!}
        </div>

        <div x-show="tab==='sites'" x-cloak class="p-4">
            @forelse($category->sites as $s)
            <div class="border border-gray-200 rounded-[4px] p-3 mb-2 text-[12.5px]">
                <span class="font-bold text-gray-900">{{ $s->site?->code }} — {{ $s->site?->name }}</span> :
                délai {{ $s->lead_time_days ?? '—' }} j · min {{ $s->stock_min ?? '—' }} · max {{ $s->stock_max ?? '—' }}
            </div>
            @empty
            <p class="text-gray-400 text-[12.5px]">Aucune déclinaison par site — les valeurs globales s'appliquent partout.</p>
            @endforelse
        </div>

        <div x-show="tab==='articles'" x-cloak class="p-4">
            @if($category->products->isEmpty())
            <p class="text-gray-400 text-[12.5px]">Aucun article dans cette catégorie.</p>
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
                @foreach($category->products as $p)
                <a href="{{ route('products.show', $p) }}" class="text-[12.5px] text-emerald-800 hover:underline">{{ $p->reference }} — {{ $p->name }}</a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- [X3 §8] Propagation contrôlée --}}
        <div x-show="tab==='propagation'" x-cloak class="p-4">
            @can('categories.propagate')
            <p class="text-[12.5px] text-gray-600 mb-3">Sélectionnez les champs à propager vers les <strong>{{ $category->products_count }}</strong> article(s) de la catégorie. Un aperçu est présenté avant toute modification. Les prix, seuils de stock et unités ne sont <strong>jamais</strong> propagés.</p>
            <form method="GET" action="{{ route('articles.categories.propagate.preview', $category) }}" class="space-y-2">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-1.5">
                    @foreach($propagatable as $f)
                    <label class="flex items-center gap-1.5 text-[12px] text-gray-700">
                        <input type="checkbox" name="fields[]" value="{{ $f }}" class="rounded border-gray-300 text-emerald-600">{{ $f }}
                    </label>
                    @endforeach
                </div>
                <button class="text-[12.5px] font-semibold text-white bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded-[4px]">Aperçu de la propagation</button>
            </form>
            @else
            <p class="text-gray-400 text-[12.5px]">Permission « categories.propagate » requise.</p>
            @endcan
        </div>
    </div>
</div>
@endsection
