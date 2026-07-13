@extends('layouts.erp')
@section('title', $report->exists ? 'Modifier la note de frais' : 'Nouvelle note de frais')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.frais.index') }}" class="hover:text-gray-700">Notes de frais</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $report->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0';
    $cell = 'w-full h-7 px-1.5 border border-gray-300 rounded-[3px] text-[12px]';
    $cats = \App\Models\ExpenseReport::CATEGORIES;
    $existing = old('lines', $report->exists ? $report->lines->map(fn ($l) => [
        'expense_date' => optional($l->expense_date)->format('Y-m-d'), 'category' => $l->category,
        'description' => $l->description, 'amount' => $l->amount, 'tax_amount' => $l->tax_amount, 'has_receipt' => (bool) $l->has_receipt,
    ])->values()->all() : []);
@endphp
<div class="max-w-4xl space-y-3" x-data="{ rows: {{ Illuminate\Support\Js::from($existing) }},
    add(){ this.rows.push({expense_date:'',category:'transport',description:'',amount:'',tax_amount:'',has_receipt:false}); },
    remove(i){ this.rows.splice(i,1); },
    get total(){ return this.rows.reduce((s,r)=>s+(parseFloat(r.amount)||0),0); } }"
    x-init="if(rows.length===0) add()">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $report->exists ? 'Modifier la note de frais' : 'Nouvelle note de frais' }}</h1>

    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $report->exists ? route('rh.frais.update', $report) : route('rh.frais.store') }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf @if($report->exists)@method('PUT')@endif
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Objet *</label>
                <input name="title" value="{{ old('title', $report->title) }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Date</label>
                <input type="date" name="report_date" value="{{ old('report_date', optional($report->report_date)->format('Y-m-d')) }}" class="{{ $inp }}">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Salarié *</label>
                <select name="employee_id" class="{{ $sel }}" required>
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id', $report->employee_id)==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="border border-gray-200 rounded-[4px] overflow-hidden">
            <div class="bg-[#eef5f0] text-emerald-900 px-4 py-2 text-[13px] font-semibold flex items-center justify-between">
                <span>Lignes de dépense</span>
                <button type="button" @click="add()" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1 rounded-[3px]">+ Ligne</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead class="bg-gray-100 text-gray-600"><tr>
                        <th class="px-2 py-1.5 text-left font-bold w-32">Date</th>
                        <th class="px-2 py-1.5 text-left font-bold">Catégorie</th>
                        <th class="px-2 py-1.5 text-left font-bold">Description</th>
                        <th class="px-2 py-1.5 text-right font-bold w-28">Montant</th>
                        <th class="px-2 py-1.5 text-right font-bold w-24">Dont TVA</th>
                        <th class="px-2 py-1.5 text-center font-bold w-14">Justif.</th>
                        <th class="px-2 py-1.5 w-8"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(r,i) in rows" :key="i">
                            <tr class="border-b border-gray-100">
                                <td class="px-2 py-1"><input type="date" :name="`lines[${i}][expense_date]`" x-model="r.expense_date" class="{{ $cell }}"></td>
                                <td class="px-2 py-1">
                                    <select :name="`lines[${i}][category]`" x-model="r.category" class="{{ $cell }}">
                                        @foreach($cats as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1"><input :name="`lines[${i}][description]`" x-model="r.description" class="{{ $cell }}"></td>
                                <td class="px-2 py-1"><input type="number" step="0.01" min="0" :name="`lines[${i}][amount]`" x-model="r.amount" class="{{ $cell }} text-right"></td>
                                <td class="px-2 py-1"><input type="number" step="0.01" min="0" :name="`lines[${i}][tax_amount]`" x-model="r.tax_amount" class="{{ $cell }} text-right"></td>
                                <td class="px-2 py-1 text-center"><input type="checkbox" :name="`lines[${i}][has_receipt]`" value="1" x-model="r.has_receipt" class="rounded border-gray-400"></td>
                                <td class="px-2 py-1 text-center"><button type="button" @click="remove(i)" class="text-red-500 hover:text-red-700">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot><tr class="bg-gray-50 font-semibold"><td colspan="3" class="px-2 py-1.5 text-right text-gray-500">Total</td><td class="px-2 py-1.5 text-right font-mono" x-text="total.toLocaleString('fr-FR') + ' F'"></td><td colspan="3"></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('notes', $report->notes) }}</textarea>
        </div>

        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">{{ $report->exists ? 'Enregistrer' : 'Créer la note' }}</button>
            <a href="{{ route('rh.frais.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
