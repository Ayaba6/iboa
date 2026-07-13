@extends('layouts.erp')
@section('title', 'Déclarer une perte')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.pertes.index') }}" class="hover:text-gray-700">Pertes & casses</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0'; @endphp
<div class="max-w-3xl space-y-3">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Déclarer une perte / casse</h1>

    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('stocks.pertes.store') }}" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Article *</label>
                <select name="product_id" class="{{ $sel }}" required>
                    <option value="">—</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id')==$p->id)>{{ $p->code ? $p->code.' — ' : '' }}{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type *</label>
                <select name="type" class="{{ $sel }}" required>
                    @foreach(\App\Models\StockLoss::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(old('type','casse')===$k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Dépôt *</label>
                <select name="warehouse_id" class="{{ $sel }}" required>
                    <option value="">—</option>
                    @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id')==$w->id)>{{ $w->code ? $w->code.' — ' : '' }}{{ $w->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Quantité *</label>
                <input type="number" step="0.001" min="0" name="quantity" value="{{ old('quantity') }}" class="{{ $inp }} text-right" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">N° lot / bobine</label>
                <input name="lot_number" value="{{ old('lot_number') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Responsable</label>
                <select name="responsible_id" class="{{ $sel }}">
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('responsible_id')==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Cause</label>
            <textarea name="cause" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('cause') }}</textarea>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Photo (jpg, png, pdf — max 5 Mo)</label>
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,.pdf" class="text-[13px]">
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('notes') }}</textarea>
        </div>
        <p class="text-[11px] text-gray-400">La sortie de stock (au PMP) n'est générée qu'à la validation de la perte par un responsable.</p>

        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Déclarer</button>
            <a href="{{ route('stocks.pertes.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
