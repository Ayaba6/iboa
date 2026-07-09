@extends('layouts.erp')
@section('title', $coil->exists ? 'Modifier bobine' : 'Bobine : Création')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.coils.index') }}" class="hover:text-gray-700">Bobines</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $coil->exists ? 'Modifier' : 'Création' }}</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = $inp . ' text-right font-mono';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'rounded border-[#c3d3c9] text-emerald-600 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $grpH  = 'text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1 mb-3';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="max-w-6xl" x-data="{ tab: 'general' }">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $coil->exists ? route('production.coils.update', $coil) : route('production.coils.store') }}">
        @csrf
        @if($coil->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">Bobine : {{ $coil->exists ? 'Modification' : 'Création' }}</h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.coils.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            {{-- Onglets ancres (SAGE X3 : sections toutes visibles) --}}
            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['general'=>'Informations générales','caracteristiques'=>'Caractéristiques','couts'=>'Coûts','gestion'=>'Propriétés de gestion','notes'=>'Notes'] as $tk => $tl)
                <button type="button"
                        @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES ═══════════ --}}
            <div id="sec-general" class="p-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Code bobine <span class="text-red-600">*</span></label>
                            <input type="text" name="reference" value="{{ old('reference', $coil->reference) }}" required maxlength="60" class="{{ $inp }} font-mono" placeholder="BOB-2026-00001">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Référence fournisseur</label>
                            <input type="text" name="supplier_reference" value="{{ old('supplier_reference', $coil->supplier_reference) }}" maxlength="60" class="{{ $inp }} font-mono" placeholder="REF-BOB-00145">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Dépôt</label>
                            <div class="relative"><select name="warehouse_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id', $coil->warehouse_id)==$w->id)>{{ $w->code ? $w->code.' — ' : '' }}{{ $w->name }}</option>@endforeach
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Fournisseur <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="supplier_id" required class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id',$coil->supplier_id)==$s->id)>{{ $s->name }}</option>@endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Date de réception <span class="text-red-600">*</span></label>
                            <input type="date" name="received_at" value="{{ old('received_at', optional($coil->received_at)->format('Y-m-d') ?? date('Y-m-d')) }}" class="{{ $inp }}">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Origine</label>
                            <div class="relative"><select name="origine" class="{{ $lk }}">
                                @php $og = old('origine', $coil->origine); @endphp
                                <option value="">—</option>
                                <option value="importation" @selected($og==='importation')>Importation</option>
                                <option value="local" @selected($og==='local')>Local</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Site</label>
                            <input type="text" name="site" value="{{ old('site', $coil->site) }}" maxlength="20" class="{{ $inp }} font-mono uppercase" placeholder="SITE01">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Numéro BL</label>
                            <input type="text" name="bl_number" value="{{ old('bl_number', $coil->bl_number) }}" maxlength="60" class="{{ $inp }} font-mono" placeholder="BL-2026-0145">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Devise</label>
                            <input type="text" name="devise" value="{{ old('devise', $coil->devise ?? 'XOF') }}" maxlength="10" class="{{ $inp }} font-mono uppercase">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Statut</label>
                            <input type="text" value="{{ $coil->exists ? $coil->statusLabel() : 'Disponible' }}" disabled class="{{ $inp }} bg-gray-50 text-gray-500">
                        </div>

                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">N° de lot <span class="text-red-600">*</span></label>
                            <input type="text" name="lot_number" value="{{ old('lot_number', $coil->lot_number) }}" required maxlength="60" class="{{ $inp }} font-mono">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Article matière (stock)</label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}">
                                <option value="">— Aucun —</option>
                                @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id',$coil->product_id)==$p->id)>{{ $p->reference ? $p->reference.' — ' : '' }}{{ $p->name }}</option>@endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ CARACTÉRISTIQUES ═══════════ --}}
            <div id="sec-caracteristiques" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques</div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-4 gap-6">
                        {{-- Caractéristiques produit --}}
                        <div>
                            <p class="{{ $grpH }}">Caractéristiques produit</p>
                            <div class="space-y-3">
                                <div><label class="{{ $lbl }}">Nuance</label><input type="text" name="nuance" value="{{ old('nuance', $coil->nuance) }}" maxlength="30" class="{{ $inp }} font-mono uppercase" placeholder="DX51D"></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label class="{{ $lbl }}">Épaisseur (mm) <span class="text-red-600">*</span></label><input type="number" name="thickness" value="{{ old('thickness', $coil->thickness) }}" step="0.01" min="0.01" required class="{{ $inpR }}"></div>
                                    <div><label class="{{ $lbl }}">Largeur (mm) <span class="text-red-600">*</span></label><input type="number" name="width" value="{{ old('width', $coil->width) }}" step="0.1" min="0.01" required class="{{ $inpR }}"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label class="{{ $lbl }}">Poids net (kg) <span class="text-red-600">*</span></label><input type="number" name="initial_weight" value="{{ old('initial_weight', $coil->initial_weight) }}" step="0.01" min="0.01" required class="{{ $inpR }}"></div>
                                    <div><label class="{{ $lbl }}">Poids brut (kg)</label><input type="number" name="gross_weight" value="{{ old('gross_weight', $coil->gross_weight) }}" step="0.001" min="0" class="{{ $inpR }}"></div>
                                </div>
                            </div>
                        </div>
                        {{-- Dimensions & métrage --}}
                        <div>
                            <p class="{{ $grpH }}">Dimensions et métrage</p>
                            <div class="space-y-3">
                                <div><label class="{{ $lbl }}">Diamètre intérieur (mm)</label><input type="number" name="inner_diameter" value="{{ old('inner_diameter', $coil->inner_diameter) }}" step="0.01" min="0" class="{{ $inpR }}" placeholder="508"></div>
                                <div><label class="{{ $lbl }}">Diamètre extérieur (mm)</label><input type="number" name="outer_diameter" value="{{ old('outer_diameter', $coil->outer_diameter) }}" step="0.01" min="0" class="{{ $inpR }}"></div>
                                <div><label class="{{ $lbl }}">Longueur (m)</label><input type="number" name="estimated_length" value="{{ old('estimated_length', $coil->estimated_length) }}" step="0.01" min="0" class="{{ $inpR }}"></div>
                                @if($coil->exists && $coil->estimated_length && $coil->width)
                                <div><label class="{{ $lbl }}">Métrage total (m²)</label><input type="text" value="{{ number_format((float) $coil->estimated_length * (float) $coil->width / 1000, 3, ',', ' ') }}" disabled class="{{ $inpR }} bg-gray-50 text-gray-500"></div>
                                @endif
                            </div>
                        </div>
                        {{-- Informations techniques --}}
                        <div>
                            <p class="{{ $grpH }}">Informations techniques</p>
                            <div class="space-y-3">
                                <div><label class="{{ $lbl }}">Revêtement</label><input type="text" name="coating" value="{{ old('coating', $coil->coating) }}" maxlength="30" class="{{ $inp }} font-mono uppercase" placeholder="Z275"></div>
                                <div>
                                    <label class="{{ $lbl }}">Finition surface</label>
                                    <div class="relative"><select name="surface_finish" class="{{ $lk }}">
                                        @php $sf = old('surface_finish', $coil->surface_finish); @endphp
                                        <option value="">—</option>
                                        @foreach(['brillante'=>'Brillante','mate'=>'Mate','satinée'=>'Satinée'] as $k=>$v)
                                        <option value="{{ $k }}" @selected($sf===$k)>{{ $v }}</option>
                                        @endforeach
                                    </select>{!! $caret !!}</div>
                                </div>
                                <div><label class="{{ $lbl }}">Couleur <span class="text-red-600">*</span></label><input type="text" name="color" value="{{ old('color', $coil->color) }}" required maxlength="60" class="{{ $inp }}" placeholder="Gris"></div>
                                <div><label class="{{ $lbl }}">Tolérance épaisseur (mm)</label><input type="number" name="tolerance_thickness" value="{{ old('tolerance_thickness', $coil->tolerance_thickness) }}" step="0.001" min="0" class="{{ $inpR }}" placeholder="0,03"></div>
                            </div>
                        </div>
                        {{-- Identification --}}
                        <div>
                            <p class="{{ $grpH }}">Identification</p>
                            <div class="space-y-3">
                                <div><label class="{{ $lbl }}">Code à barres / QR Code</label><input type="text" name="barcode" value="{{ old('barcode', $coil->barcode) }}" maxlength="60" class="{{ $inp }} font-mono"></div>
                                <div><label class="{{ $lbl }}">Marque bobine</label><input type="text" name="brand" value="{{ old('brand', $coil->brand) }}" maxlength="60" class="{{ $inp }}"></div>
                                <div><label class="{{ $lbl }}">N° de série</label><input type="text" name="serial_number" value="{{ old('serial_number', $coil->serial_number) }}" maxlength="60" class="{{ $inp }} font-mono" placeholder="SN-000145"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ COÛTS ═══════════ --}}
            <div id="sec-couts" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Coûts</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Prix d'achat total ({{ old('devise', $coil->devise ?? 'XOF') }}) <span class="text-red-600">*</span></label>
                            <input type="number" name="purchase_price" value="{{ old('purchase_price', $coil->purchase_price) }}" step="1" min="0" required class="{{ $inpR }}">
                        </div>
                        @if($coil->exists)
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Coût au kg (calculé)</label>
                            <input type="text" value="{{ number_format((float) $coil->cost_per_kg, 2, ',', ' ') }}" disabled class="{{ $inpR }} bg-gray-50 text-gray-500">
                        </div>
                        @endif
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Méthode de valorisation</label>
                            <div class="relative"><select name="valuation_method" class="{{ $lk }}">
                                @php $vm = old('valuation_method', $coil->valuation_method ?? 'cump'); @endphp
                                <option value="cump" @selected($vm==='cump')>Coût moyen pondéré</option>
                                <option value="fifo" @selected($vm==='fifo')>FIFO</option>
                                <option value="pmp"  @selected($vm==='pmp')>PMP</option>
                            </select>{!! $caret !!}</div>
                        </div>
                    </div>
                    <p class="px-4 pb-3 text-[11.5px] text-gray-400">Le coût au kg est calculé automatiquement (prix d'achat ÷ poids net).</p>
                </section>
            </div>

            {{-- ═══════════ PROPRIÉTÉS DE GESTION ═══════════ --}}
            <div id="sec-gestion" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Propriétés de gestion</div>
                    <div class="p-4 flex flex-wrap items-center gap-x-8 gap-y-3">
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700">
                            <input type="checkbox" name="is_stock_managed" value="1" @checked(old('is_stock_managed', $coil->is_stock_managed ?? true)) class="{{ $chk }}">
                            Gérée en stock
                        </label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700">
                            <input type="checkbox" name="lot_tracking" value="1" @checked(old('lot_tracking', $coil->lot_tracking ?? true)) class="{{ $chk }}">
                            Lot / Traçabilité
                        </label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700">
                            <input type="checkbox" name="allow_negative_stock" value="1" @checked(old('allow_negative_stock', $coil->allow_negative_stock ?? false)) class="{{ $chk }}">
                            Stock négatif autorisé
                        </label>
                    </div>
                </section>
            </div>

            {{-- ═══════════ NOTES ═══════════ --}}
            <div id="sec-notes" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Notes / commentaire</div>
                    <div class="p-4">
                        <textarea name="notes" rows="3" maxlength="2000"
                                  class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white resize-none focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">{{ old('notes', $coil->notes) }}</textarea>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
