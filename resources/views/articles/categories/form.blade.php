@extends('layouts.erp')
@section('title', ($category->exists ? 'Modifier ' . $category->code : 'Nouvelle catégorie'))

@section('breadcrumb')
    <a href="{{ route('articles.categories.index') }}" class="hover:text-gray-700">Catégories</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $category->exists ? $category->code : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $c    = $category;
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-gray-300 rounded-[3px] text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400';
    $chk  = 'rounded border-gray-300 text-emerald-600 focus:ring-emerald-500';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase';
    $flag = fn ($name, $label) => '<label class="flex items-center gap-1.5 text-[12.5px] text-gray-700"><input type="hidden" name="' . $name . '" value="0"><input type="checkbox" name="' . $name . '" value="1" class="' . $chk . '" ' . (old($name, $c->$name) ? 'checked' : '') . '>' . $label . '</label>';
@endphp

<form method="POST" action="{{ $c->exists ? route('articles.categories.update', $c) : route('articles.categories.store') }}" class="space-y-3 max-w-5xl">
    @csrf
    @if($c->exists)@method('PUT')@endif

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">{{ $c->exists ? 'Modifier la catégorie ' . $c->code : 'Nouvelle catégorie d\'article' }}</h1>
        <div class="flex gap-2">
            <button class="text-[12.5px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px]">Enregistrer</button>
            <a href="{{ route('articles.categories.index') }}" class="text-[12.5px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px]">Abandon</a>
        </div>
    </div>

    <x-validation-errors />

    @if($c->exists)
    <div class="rounded-[4px] bg-blue-50 border border-blue-200 px-3 py-2 text-[12px] text-blue-800">La modification ne s'applique qu'aux <strong>futurs</strong> articles. Pour les articles existants, utilisez l'onglet « Propagation » de la fiche.</div>
    @endif

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">1. Général</div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div><label class="{{ $lbl }}">Code *</label><input name="code" value="{{ old('code', $c->code) }}" required maxlength="30" class="{{ $inp }} font-mono uppercase"></div>
            <div class="md:col-span-2"><label class="{{ $lbl }}">Intitulé *</label><input name="name" value="{{ old('name', $c->name) }}" required maxlength="120" class="{{ $inp }}"></div>
            <div><label class="{{ $lbl }}">Ordre</label><input type="number" name="sort_order" value="{{ old('sort_order', $c->sort_order ?? 0) }}" min="0" class="{{ $inp }}"></div>
            <div class="md:col-span-2"><label class="{{ $lbl }}">Description</label><input name="description" value="{{ old('description', $c->description) }}" maxlength="500" class="{{ $inp }}"></div>
            <div><label class="{{ $lbl }}">Nature *</label>
                <select name="nature" required class="{{ $lk }}">
                    @foreach(['matiere_premiere'=>'Matière première','semi_fini'=>'Semi-fini','produit_fini'=>'Produit fini','marchandise'=>'Marchandise','consommable'=>'Consommable','service'=>'Service','sous_produit'=>'Sous-produit','chute'=>'Chute réutilisable','rebut'=>'Rebut'] as $v=>$l)
                    <option value="{{ $v }}" @selected(old('nature', $c->nature)===$v)>{{ $l }}</option>
                    @endforeach
                </select></div>
            <div><label class="{{ $lbl }}">Stratégie</label>
                <select name="strategy" class="{{ $lk }}">
                    <option value="">—</option>
                    @foreach(['mto'=>'MTO — sur commande','mts'=>'MTS — pour stock','achat_revente'=>'Achat-revente','service'=>'Service non stocké','conso_interne'=>'Consommation interne'] as $v=>$l)
                    <option value="{{ $v }}" @selected(old('strategy', $c->strategy)===$v)>{{ $l }}</option>
                    @endforeach
                </select></div>
            <div class="flex items-end gap-4 pb-1">{!! $flag('is_active','Active') !!}{!! $flag('site_declinable','Déclinable par site') !!}</div>
        </div>
    </section>

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">2. Flux</div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-2">
            {!! $flag('is_purchasable','Achetable') !!}{!! $flag('is_sellable','Vendable') !!}
            {!! $flag('is_stockable','Stockable') !!}{!! $flag('is_manufactured','Fabriqué') !!}
            {!! $flag('is_subcontracted','Sous-traité') !!}{!! $flag('usable_in_bom','Utilisable en nomenclature') !!}
            {!! $flag('usable_as_finished','Utilisable comme PF') !!}
        </div>
    </section>

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">3. Stock</div>
        <div class="p-4 space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                {!! $flag('allow_negative_stock','Stock négatif autorisé') !!}{!! $flag('lot_managed','Gestion par lot') !!}
                {!! $flag('serial_managed','N° de série') !!}{!! $flag('coil_managed','Gestion bobine') !!}
                {!! $flag('expiry_managed','Péremption') !!}{!! $flag('qc_on_receipt','CQ à réception') !!}
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="{{ $lbl }}">Valorisation</label><select name="valuation_method" class="{{ $lk }}"><option value="">—</option><option value="cmp" @selected(old('valuation_method',$c->valuation_method)==='cmp')>CMP</option><option value="fifo" @selected(old('valuation_method',$c->valuation_method)==='fifo')>FIFO</option></select></div>
                <div><label class="{{ $lbl }}">Stock mini défaut</label><input type="number" step="0.01" name="default_stock_min" value="{{ old('default_stock_min', $c->default_stock_min) }}" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">Stock maxi défaut</label><input type="number" step="0.01" name="default_stock_max" value="{{ old('default_stock_max', $c->default_stock_max) }}" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">Stock sécurité défaut</label><input type="number" step="0.01" name="default_stock_securite" value="{{ old('default_stock_securite', $c->default_stock_securite) }}" class="{{ $inp }}"></div>
            </div>
        </div>
    </section>

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">4. Vente</div>
        <div class="p-4 space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="{{ $lbl }}">Unité de vente</label><select name="default_sale_unit_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['units'] as $u)<option value="{{ $u->id }}" @selected(old('default_sale_unit_id',$c->default_sale_unit_id)==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div><label class="{{ $lbl }}">Unité de tarification</label><select name="default_pricing_unit_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['units'] as $u)<option value="{{ $u->id }}" @selected(old('default_pricing_unit_id',$c->default_pricing_unit_id)==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div><label class="{{ $lbl }}">TVA défaut</label><select name="default_tax_rate_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['taxRates'] as $t)<option value="{{ $t->id }}" @selected(old('default_tax_rate_id',$c->default_tax_rate_id)==$t->id)>{{ $t->rate }} %</option>@endforeach</select></div>
                <div><label class="{{ $lbl }}">Remise max (%)</label><input type="number" step="0.01" name="max_discount_percent" value="{{ old('max_discount_percent', $c->max_discount_percent) }}" class="{{ $inp }}"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                {!! $flag('exempt_allowed','Vente exonérée autorisée') !!}{!! $flag('floor_price_required','Prix plancher obligatoire') !!}
                {!! $flag('deposit_required','Acompte requis') !!}{!! $flag('credit_check','Contrôle de crédit') !!}
            </div>
        </div>
    </section>

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">5. Achat</div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div><label class="{{ $lbl }}">Unité d'achat</label><select name="default_purchase_unit_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['units'] as $u)<option value="{{ $u->id }}" @selected(old('default_purchase_unit_id',$c->default_purchase_unit_id)==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="{{ $lbl }}">Tolérance réception (%)</label><input type="number" step="0.01" name="receipt_tolerance_percent" value="{{ old('receipt_tolerance_percent', $c->receipt_tolerance_percent) }}" class="{{ $inp }}"></div>
            <div><label class="{{ $lbl }}">Délai appro (jours)</label><input type="number" name="lead_time_days" value="{{ old('lead_time_days', $c->lead_time_days) }}" min="0" class="{{ $inp }}"></div>
            <div><label class="{{ $lbl }}">Dépôt de réception</label><select name="default_receipt_warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['warehouses'] as $w)<option value="{{ $w->id }}" @selected(old('default_receipt_warehouse_id',$c->default_receipt_warehouse_id)==$w->id)>{{ $w->code }}</option>@endforeach</select></div>
        </div>
    </section>

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">6. Production</div>
        <div class="p-4 space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                {!! $flag('bom_required','Nomenclature obligatoire') !!}{!! $flag('routing_required','Gamme obligatoire') !!}
                {!! $flag('auto_of','OF automatique') !!}{!! $flag('qc_required','CQ obligatoire') !!}
                {!! $flag('offcut_managed','Gestion des chutes') !!}{!! $flag('cutting_optimized','Optimisation découpe') !!}
                {!! $flag('mrp_planned','Planification MRP') !!}
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="{{ $lbl }}">Dépôt MP</label><select name="default_mp_warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['warehouses'] as $w)<option value="{{ $w->id }}" @selected(old('default_mp_warehouse_id',$c->default_mp_warehouse_id)==$w->id)>{{ $w->code }}</option>@endforeach</select></div>
                <div><label class="{{ $lbl }}">Dépôt PF</label><select name="default_pf_warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['warehouses'] as $w)<option value="{{ $w->id }}" @selected(old('default_pf_warehouse_id',$c->default_pf_warehouse_id)==$w->id)>{{ $w->code }}</option>@endforeach</select></div>
                <div><label class="{{ $lbl }}">Ligne de production</label><select name="default_production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($formData['lines'] as $l)<option value="{{ $l->id }}" @selected(old('default_production_line_id',$c->default_production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select></div>
                <div><label class="{{ $lbl }}">Perte de réglage</label><input type="number" step="0.001" name="setup_loss" value="{{ old('setup_loss', $c->setup_loss) }}" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">Taux de rebut prév. (%)</label><input type="number" step="0.01" name="scrap_rate_percent" value="{{ old('scrap_rate_percent', $c->scrap_rate_percent) }}" class="{{ $inp }}"></div>
            </div>
        </div>
    </section>

    <section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="{{ $secH }}">7. Comptabilité</div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach(['stock_account_id'=>'Compte stock','purchase_account_id'=>'Compte achat','sale_account_id'=>'Compte vente','variation_account_id'=>'Variation stock','consumption_account_id'=>'Consommation','scrap_account_id'=>'Rebut','finished_account_id'=>'Produit fini'] as $col => $l)
            <div><label class="{{ $lbl }}">{{ $l }}</label>
                <select name="{{ $col }}" class="{{ $lk }} font-mono"><option value="">—</option>
                    @foreach($formData['accounts'] as $a)<option value="{{ $a->id }}" @selected(old($col, $c->$col)==$a->id)>{{ $a->code }}</option>@endforeach
                </select></div>
            @endforeach
            <div><label class="{{ $lbl }}">Méthode de coût</label><select name="cost_method" class="{{ $lk }}"><option value="">—</option><option value="standard" @selected(old('cost_method',$c->cost_method)==='standard')>Standard</option><option value="moyen" @selected(old('cost_method',$c->cost_method)==='moyen')>Coût moyen</option></select></div>
        </div>
    </section>

    <div class="flex gap-2">
        <button class="text-[12.5px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-2 rounded-[4px]">Enregistrer</button>
        <a href="{{ route('articles.categories.index') }}" class="text-[12.5px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-2 rounded-[4px]">Abandon</a>
    </div>
</form>
@endsection
