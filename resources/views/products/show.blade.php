@extends('layouts.erp')
@section('title', $product->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('products.index') }}" class="hover:text-gray-700">Articles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $product->name }}</span>
@endsection

@section('content')
@php
    // ── Classes SAGE X3 ──────────────────────────────────────────────────────
    $card = 'bg-white border border-gray-300 rounded-[4px]';
    $secH = 'px-4 py-1.5 border-b border-t border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide';
    $lbl  = 'block text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-0.5';
    $val  = 'text-[13px] text-gray-800 font-medium';

    // Rendu compact « libellé / valeur » (valeur échappée).
    $row = function ($label, $value) use ($lbl, $val) {
        $v = ($value === null || $value === '') ? '—' : e($value);
        return '<div class="min-w-0"><span class="'.$lbl.'">'.e($label).'</span><span class="'.$val.' block truncate">'.$v.'</span></div>';
    };
    $oui = fn ($b) => $b ? 'Oui' : 'Non';
    $f   = fn ($n) => $n !== null ? number_format((float) $n, 0, ',', ' ') : null;

    $totalStock = $product->productStocks->sum('quantity');
    $stockMin   = (float) ($product->stock_min ?? 0);
    $margin     = $product->purchase_price > 0
        ? round((($product->sale_price - $product->purchase_price) / $product->purchase_price) * 100, 1)
        : null;
    $stockAlert = $stockMin > 0 && $totalStock <= $stockMin;

    $tabs = [
        'general'    => 'Général',
        'unites'     => 'Unités',
        'stock'      => 'Stock',
        'achat'      => 'Achat',
        'vente'      => 'Vente',
        'production' => 'Production',
        'qualite'    => 'Qualité',
        'compta'     => 'Compta',
        'documents'  => 'Documents',
    ];
@endphp

<div x-data="{ tab: 'general' }" class="space-y-3">

    {{-- ═══ Bandeau titre (fiche SAGE) ═══════════════════════════════════════ --}}
    <div class="{{ $card }} px-3 py-2.5 flex items-center gap-3">
        <div class="flex-shrink-0">
            @if($product->image)
                <img src="{{ url(Storage::url($product->image)) }}" alt="" class="w-11 h-11 rounded-[4px] object-cover border border-gray-200">
            @else
                <div class="w-11 h-11 bg-gray-100 rounded-[4px] flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            @endif
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="text-[22px] font-bold text-gray-900 leading-tight truncate">{{ $product->name }}</h1>
                @if($product->is_active)
                    <span class="bg-green-50 text-green-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Actif</span>
                @else
                    <span class="bg-red-50 text-red-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Inactif</span>
                @endif
            </div>
            <div class="flex items-center flex-wrap gap-x-3 gap-y-0.5 mt-0.5 text-[11.5px] text-gray-400">
                <span class="font-mono">Réf : {{ $product->reference }}</span>
                @if($product->barcode)<span class="font-mono">CB : {{ $product->barcode }}</span>@endif
                @if($product->family)<span class="text-blue-600">{{ $product->family->name }}</span>@endif
                <span class="capitalize text-purple-600">{{ $product->type }}</span>
            </div>
        </div>
        <a href="{{ route('products.edit', $product) }}"
           class="flex-shrink-0 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-4 py-1.5 rounded-[4px] transition-colors">
            Modifier
        </a>
    </div>

    {{-- ═══ Bandeau KPI ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Prix d'achat</p>
            <p class="text-[15px] font-bold text-gray-700 mt-0.5">{{ $f($product->purchase_price) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
            @if($margin !== null)<p class="text-[10.5px] mt-0.5 {{ $margin >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-semibold">Marge {{ $margin >= 0 ? '+' : '' }}{{ $margin }}%</p>@endif
        </div>
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Prix de vente HT</p>
            <p class="text-[15px] font-bold text-blue-700 mt-0.5">{{ $f($product->sale_price) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">TVA</p>
            <p class="text-[15px] font-bold text-gray-700 mt-0.5">{{ $product->taxRate ? $product->taxRate->rate.' %' : '—' }}</p>
        </div>
        <div class="{{ $card }} px-3 py-2.5 {{ $stockAlert ? 'ring-1 ring-red-200' : '' }}">
            <p class="text-[10px] font-bold {{ $stockAlert ? 'text-red-500' : 'text-gray-400' }} uppercase tracking-wide">Stock disponible {!! $stockAlert ? '⚠️' : '' !!}</p>
            <p class="text-[15px] font-bold {{ $stockAlert ? 'text-red-700' : 'text-gray-700' }} mt-0.5">{{ number_format($totalStock, 2) }} <span class="text-[11px] font-normal text-gray-400">{{ $product->unit?->abbreviation ?? 'u' }}</span></p>
        </div>
    </div>

    {{-- ═══ Fiche à onglets (lecture) ════════════════════════════════════════ --}}
    <div class="{{ $card }} overflow-hidden">
        {{-- Barre d'onglets --}}
        <div class="flex items-stretch border-b border-gray-200 overflow-x-auto bg-gray-50/70">
            @foreach($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1.5 text-[12.5px] font-semibold border-b-2 whitespace-nowrap transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ── Général ── --}}
        <div x-show="tab === 'general'" class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Référence', $product->reference) !!}
            {!! $row('Code article', $product->code_article) !!}
            {!! $row('Désignation courte', $product->designation_courte ?? $product->designation_2) !!}
            {!! $row('Type', ucfirst($product->type ?? '')) !!}
            {!! $row('Type article', $product->type_article) !!}
            {!! $row('Statut', $product->statut) !!}
            {!! $row('Famille', $product->family?->name) !!}
            {!! $row('Marque', $product->brand?->name) !!}
            {!! $row('Canal client', $product->client_type_canal) !!}
            {!! $row('Fournisseur par défaut', $product->defaultSupplier?->name) !!}
            {!! $row('Réf. fournisseur', $product->supplier_reference) !!}
            {!! $row('Délai livraison (j)', $product->delivery_delay_days) !!}
            <div class="col-span-2 md:col-span-3"><span class="{{ $lbl }}">Description</span><span class="{{ $val }}">{{ $product->description ?: '—' }}</span></div>
        </div>

        {{-- ── Unités ── --}}
        <div x-show="tab === 'unites'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Unité de stock', $product->unit?->name) !!}
            {!! $row("Unité d'achat", $product->purchaseUnit?->name) !!}
            {!! $row('Coef UA → US', $product->ua_to_us_coef ? number_format((float) $product->ua_to_us_coef, 6, ',', ' ') : null) !!}
            {!! $row('Unité de vente', $product->saleUnit?->name) !!}
            {!! $row('Coef UV → US', $product->uv_to_us_coef ? number_format((float) $product->uv_to_us_coef, 6, ',', ' ') : null) !!}
            {!! $row('Unité de poids', $product->weightUnit?->name) !!}
            {!! $row('Poids brut / US', $product->gross_weight_per_us) !!}
            {!! $row('Poids net / US', $product->net_weight_per_us) !!}
            {!! $row('Densité', $product->density) !!}
            {!! $row('Épaisseur / diamètre (mm)', $product->thickness) !!}
            {!! $row('Métrage (m)', $product->linear_meters) !!}
        </div>

        {{-- ── Stock ── --}}
        <div x-show="tab === 'stock'" x-cloak>
            <div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                {!! $row('Méthode de valorisation', strtoupper($product->valuation_method ?? '')) !!}
                {!! $row('Dépôt principal', $product->mainWarehouse?->name) !!}
                {!! $row('Stock mini', $product->stock_min) !!}
                {!! $row('Stock maxi', $product->stock_max) !!}
                {!! $row('Point de réappro.', $product->reorder_point) !!}
                {!! $row('Stock de sécurité', $product->stock_securite) !!}
                {!! $row("Seuil d'alerte", $product->seuil_alerte) !!}
                {!! $row('Stock négatif autorisé', $oui($product->allow_negative_stock)) !!}
                {!! $row('Stockable', $oui($product->is_stockable)) !!}
            </div>

            @if($product->productStocks->isNotEmpty())
                <div class="{{ $secH }}">Stock par dépôt</div>
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Dépôt</th>
                        <th class="px-4 py-1.5 text-right">Disponible</th>
                        <th class="px-4 py-1.5 text-right">Réservé</th>
                        <th class="px-4 py-1.5 text-right">Coût moyen</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($product->productStocks as $stock)
                        <tr>
                            <td class="px-4 py-1.5 text-gray-900">{{ $stock->warehouse?->name ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-right font-medium tabular-nums">{{ number_format($stock->quantity, 2) }}</td>
                            <td class="px-4 py-1.5 text-right text-gray-500 tabular-nums">{{ number_format($stock->reserved_quantity, 2) }}</td>
                            <td class="px-4 py-1.5 text-right text-gray-500 tabular-nums">{{ number_format($stock->avg_cost, 0, ',', ' ') }} FCFA</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($recentMovements->isNotEmpty())
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>Derniers mouvements</span>
                    <a href="{{ route('stocks.movements', ['product_id' => $product->id]) }}" class="text-emerald-700 hover:text-emerald-800 normal-case tracking-normal text-[11px]">Voir tout →</a>
                </div>
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Date</th>
                        <th class="px-4 py-1.5 text-left">Type</th>
                        <th class="px-4 py-1.5 text-left">Dépôt</th>
                        <th class="px-4 py-1.5 text-right">Quantité</th>
                        <th class="px-4 py-1.5 text-right hidden md:table-cell">Coût unit.</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($recentMovements as $mvt)
                        @php
                            $isIn = in_array($mvt->type, ['entree', 'retour_client']) || ($mvt->type === 'ajustement' && $mvt->quantity > 0);
                            $typeLabels = ['entree' => 'Entrée', 'sortie' => 'Sortie', 'ajustement' => 'Ajustement', 'transfert' => 'Transfert', 'retour_client' => 'Retour client', 'retour_fournisseur' => 'Retour fourn.'];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-1.5 text-gray-500">{{ \Carbon\Carbon::parse($mvt->occurred_at)->format('d/m/Y') }}</td>
                            <td class="px-4 py-1.5"><span class="text-[10.5px] font-semibold px-1.5 py-0.5 rounded-full {{ $isIn ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ $typeLabels[$mvt->type] ?? $mvt->type }}</span></td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $mvt->warehouse?->name ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-right font-semibold tabular-nums {{ $isIn ? 'text-green-700' : 'text-red-700' }}">{{ $isIn ? '+' : '' }}{{ number_format($mvt->quantity, 2) }}</td>
                            <td class="px-4 py-1.5 text-right text-gray-500 tabular-nums hidden md:table-cell">{{ $mvt->unit_cost ? number_format($mvt->unit_cost, 0, ',', ' ').' FCFA' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── Achat ── --}}
        <div x-show="tab === 'achat'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row("Prix d'achat", $f($product->purchase_price).' FCFA') !!}
            {!! $row('Dernier prix achat', $product->last_purchase_price ? $f($product->last_purchase_price).' FCFA' : null) !!}
            {!! $row('Coût moyen pondéré', $product->weighted_avg_cost ? $f($product->weighted_avg_cost).' FCFA' : null) !!}
            {!! $row('Coût standard', $product->cout_standard ? $f($product->cout_standard).' FCFA' : null) !!}
            {!! $row('TVA achat', $product->taxRateAchat ? $product->taxRateAchat->rate.' %' : null) !!}
            {!! $row('Achetable', $oui($product->is_purchasable)) !!}
        </div>

        {{-- ── Vente ── --}}
        <div x-show="tab === 'vente'" x-cloak>
            <div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                {!! $row('Prix de vente HT', $f($product->sale_price).' FCFA') !!}
                {!! $row('Prix de vente mini', $product->min_sale_price ? $f($product->min_sale_price).' FCFA' : null) !!}
                {!! $row('Taux de marge cible', $product->margin_rate_target ? $product->margin_rate_target.' %' : null) !!}
                {!! $row('TVA', $product->taxRate ? $product->taxRate->rate.' %' : null) !!}
                {!! $row('Vendable', $oui($product->is_sellable)) !!}
            </div>

            <div class="{{ $secH }}">Tarifs spéciaux</div>
            @if($product->productPriceTiers->isEmpty())
                <div class="px-4 py-4 text-center text-gray-400 text-[12.5px]">Aucun tarif spécial</div>
            @else
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Libellé</th>
                        <th class="px-4 py-1.5 text-left">Catégorie</th>
                        <th class="px-4 py-1.5 text-right">Qté min.</th>
                        <th class="px-4 py-1.5 text-right">Prix</th>
                        <th class="px-4 py-1.5 text-right">Remise</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($product->productPriceTiers as $tier)
                        <tr>
                            <td class="px-4 py-1.5 text-gray-900">{{ $tier->label ?: 'Tarif #'.$tier->id }}</td>
                            <td class="px-4 py-1.5 text-gray-500 capitalize">{{ $tier->client_category ?: '—' }}</td>
                            <td class="px-4 py-1.5 text-right tabular-nums">{{ $tier->min_quantity ?? 1 }}</td>
                            <td class="px-4 py-1.5 text-right font-semibold text-blue-700 tabular-nums">{{ number_format($tier->price, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-1.5 text-right text-green-600 tabular-nums">{{ $tier->discount_percent ? '-'.$tier->discount_percent.'%' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="{{ $secH }}">Promotions</div>
            @if(isset($promotions) && $promotions->isNotEmpty())
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Nom</th>
                        <th class="px-4 py-1.5 text-left">Période</th>
                        <th class="px-4 py-1.5 text-right">Valeur</th>
                        <th class="px-4 py-1.5 text-left">État</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($promotions as $promo)
                        @php $today = \Carbon\Carbon::today(); $active = $promo->is_active && $promo->starts_at <= $today && $promo->ends_at >= $today; @endphp
                        <tr>
                            <td class="px-4 py-1.5 text-gray-900">{{ $promo->name }}</td>
                            <td class="px-4 py-1.5 text-gray-500">{{ $promo->starts_at?->format('d/m/Y') ?? '—' }} → {{ $promo->ends_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-right font-semibold tabular-nums">{{ $promo->type === 'pourcentage' ? '-'.number_format($promo->value, 0).'%' : '-'.number_format($promo->value, 0, ',', ' ').' FCFA' }}</td>
                            <td class="px-4 py-1.5">@if($active)<span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-[10.5px] px-1.5 py-0.5 rounded-full"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Active</span>@else<span class="text-gray-400 text-[11px]">Inactive</span>@endif</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-4 py-4 text-center text-gray-400 text-[12.5px]">Aucune promotion</div>
            @endif
        </div>

        {{-- ── Production ── --}}
        <div x-show="tab === 'production'" x-cloak>
            <div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                {!! $row('Fabricable', $oui($product->is_manufacturable)) !!}
                {!! $row('Mode de production', $product->production_mode) !!}
                {!! $row('Semi-fini', $oui($product->is_semi_finished)) !!}
                {!! $row('Profil', $product->profil) !!}
                {!! $row('Couleur', $product->couleur) !!}
                {!! $row('Largeur utile', $product->largeur_utile) !!}
                {!! $row('Longueur standard', $product->longueur_standard) !!}
                {!! $row('Rendement standard', $product->rendement_standard ? $product->rendement_standard.' %' : null) !!}
                {!! $row('Taux de perte', $product->taux_perte ? $product->taux_perte.' %' : null) !!}
                {!! $row('Machine par défaut', $product->machineDefaut?->name) !!}
                {!! $row('Réf. nomenclature', $product->nomenclature_ref) !!}
                {!! $row('Dépôt de production', $product->productionWarehouse?->name) !!}
                {!! $row('Article avarié', $product->articleAvarie?->name) !!}
                {!! $row('Article chute', $product->articleChute?->name) !!}
            </div>

            @if($product->type === 'compose' && $product->components->isNotEmpty())
                <div class="{{ $secH }}">Composants</div>
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Composant</th>
                        <th class="px-4 py-1.5 text-left">Référence</th>
                        <th class="px-4 py-1.5 text-right">Quantité</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($product->components as $comp)
                        <tr>
                            <td class="px-4 py-1.5"><a href="{{ route('products.show', $comp->component) }}" class="text-gray-900 hover:text-emerald-700">{{ $comp->component->name }}</a></td>
                            <td class="px-4 py-1.5 font-mono text-gray-500 text-[11px]">{{ $comp->component->reference }}</td>
                            <td class="px-4 py-1.5 text-right font-medium tabular-nums">{{ number_format($comp->quantity, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── Qualité ── --}}
        <div x-show="tab === 'qualite'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Contrôle qualité', $oui($product->controle_qualite)) !!}
            {!! $row('Dépôt qualité', $product->qualityWarehouse?->name) !!}
            {!! $row('N° de série', $oui($product->has_serial_number)) !!}
            {!! $row('Gestion par lot', $oui($product->has_lot_number)) !!}
            {!! $row("Date d'expiration", $oui($product->has_expiry_date)) !!}
        </div>

        {{-- ── Compta ── --}}
        <div x-show="tab === 'compta'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Section analytique', $product->sectionAnalytique?->name ?? $product->sectionAnalytique?->code) !!}
            {!! $row('Centre de coût', $product->costCenter?->name ?? $product->costCenter?->code) !!}
            {!! $row('Compte de vente', $product->saleAccount?->code) !!}
            {!! $row("Compte d'achat", $product->purchaseAccount?->code) !!}
            {!! $row('Compte de stock', $product->stockAccount?->code) !!}
            {!! $row('Compte variation stock', $product->variationStockAccount?->code) !!}
        </div>

        {{-- ── Documents ── --}}
        <div x-show="tab === 'documents'" x-cloak class="p-6 text-center text-gray-400 text-[12.5px]">
            Aucun document attaché.
        </div>
    </div>

    {{-- ── Barre de contexte X3 ─────────────────────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Article : <span class="text-white font-semibold font-mono">{{ $product->code_article ?? $product->reference }}</span></span>
        <span class="border-l border-white/10 pl-6">Statut : <span class="text-white font-semibold">{{ $product->is_active ? 'Actif' : 'Inactif' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
