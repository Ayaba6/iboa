{{--
  [X3 §4] Formulaire FAMILLE = classement commercial et statistique UNIQUEMENT.
  La gestion (flux, stock, MTO/MTS, comptes, unités, qualité) relève des
  CATÉGORIES D'ARTICLE (articles/categories) — ne pas réintroduire ces champs ici.
  L'ancien formulaire fusionné (~500 lignes, onglets Stock/Achat/Vente/Production/
  Comptabilité) a été retiré après la séparation Catégorie/Famille ; les colonnes
  de gestion restent en base (dépréciées, non exposées).
--}}
@php
    $f    = $family ?? null;
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-gray-300 rounded-[3px] text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $parentOptions = \App\Models\ProductFamily::whereNull('parent_id')
        ->where('is_active', true)
        ->when($f?->id, fn ($q) => $q->where('id', '!=', $f->id))
        ->orderBy('name')->get(['id', 'code', 'name']);
@endphp

<x-validation-errors class="mb-3" />

<div class="rounded-[4px] bg-blue-50 border border-blue-200 px-3 py-2 text-[12px] text-blue-800 mb-3">
    La famille sert au <strong>classement commercial et statistique</strong>. Le fonctionnement des articles
    (achat/vente/stock/fabrication, MTO/MTS, comptes, unités, qualité) se paramètre dans
    <a href="{{ route('articles.categories.index') }}" class="underline font-semibold">les catégories d'article</a>.
</div>

<section class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
    <div class="{{ $secH }}">Classement</div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
            <label class="{{ $lbl }}">Code</label>
            <input name="code" value="{{ old('code', $f?->code) }}" maxlength="20" class="{{ $inp }} font-mono uppercase" placeholder="TOLES_BAC">
        </div>
        <div class="md:col-span-2">
            <label class="{{ $lbl }}">Intitulé <span class="text-red-600">*</span></label>
            <input name="name" value="{{ old('name', $f?->name) }}" required maxlength="100" class="{{ $inp }}">
        </div>
        <div>
            <label class="{{ $lbl }}">Ordre d'affichage</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $f?->sort_order ?? 0) }}" min="0" class="{{ $inp }}">
        </div>
        <div class="md:col-span-2">
            <label class="{{ $lbl }}">Famille parente <span class="text-gray-400 font-normal">(vide = racine ; renseignée = sous-famille)</span></label>
            <div class="relative">
                <select name="parent_id" class="{{ $lk }}">
                    <option value="">— Famille racine —</option>
                    @foreach($parentOptions as $po)
                        <option value="{{ $po->id }}" @selected(old('parent_id', $f?->parent_id) == $po->id)>{{ $po->code ? $po->code . ' — ' : '' }}{{ $po->name }}</option>
                    @endforeach
                </select>{!! $caret !!}
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="{{ $lbl }}">Description</label>
            <input name="description" value="{{ old('description', $f?->description) }}" maxlength="255" class="{{ $inp }}">
        </div>
        <div class="flex items-end pb-1">
            <label class="flex items-center gap-1.5 text-[12.5px] text-gray-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-emerald-600" {{ old('is_active', $f?->is_active ?? true) ? 'checked' : '' }}>
                Active
            </label>
        </div>
    </div>
</section>
