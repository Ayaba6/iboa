@extends('layouts.erp')
@section('title', 'Nouveau budget de trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.budgets.index') }}" class="hover:text-gray-700">Budgets</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php $mois = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc']; @endphp
<div class="w-full"
     x-data="{
        lines: [{ category: '', direction: 'entree', months: {} }],
        addLine(dir) { this.lines.push({ category: '', direction: dir || 'entree', months: {} }); },
        removeLine(i) { this.lines.splice(i, 1); if (!this.lines.length) this.addLine(); },
        lineTotal(l) { return Object.values(l.months).reduce((a,b)=>a+(parseInt(b)||0),0); },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); }
     }">

    <div class="mb-5">
        <h1 class="text-[17px] font-bold text-gray-900">Nouveau budget de trésorerie</h1>
        <p class="text-sm text-gray-500 mt-0.5">Définissez les entrées et sorties prévues par mois</p>
    </div>

    <form method="POST" action="{{ route('tresorerie.budgets.store') }}" class="space-y-3">
        @csrf

        <div class="bg-white rounded-[4px] border border-gray-200 shadow-sm p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required placeholder="Ex. : Budget {{ $year }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Exercice <span class="text-red-500">*</span></label>
                <input type="number" name="year" value="{{ old('year', $year) }}" min="2020" max="2100" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm text-center focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>

        {{-- Lignes budget --}}
        <div class="bg-white rounded-[4px] border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Lignes prévisionnelles</h2>
                <div class="flex gap-2">
                    <button type="button" @click="addLine('entree')" class="text-xs px-2.5 py-1.5 bg-emerald-50 text-emerald-700 rounded-[4px] hover:bg-emerald-100">+ Entrée</button>
                    <button type="button" @click="addLine('sortie')" class="text-xs px-2.5 py-1.5 bg-red-50 text-red-700 rounded-[4px] hover:bg-red-100">+ Sortie</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="text-sm w-full" style="min-width:1100px">
                    <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left w-48">Catégorie</th>
                            <th class="px-2 py-2 text-left w-24">Sens</th>
                            @foreach($mois as $mLabel)<th class="px-2 py-2 text-right">{{ $mLabel }}</th>@endforeach
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(line, i) in lines" :key="i">
                            <tr>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`lines[${i}][category]`" x-model="line.category" placeholder="Ex. Ventes"
                                           class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-300">
                                </td>
                                <td class="px-2 py-2">
                                    <select :name="`lines[${i}][direction]`" x-model="line.direction" class="border border-gray-200 rounded px-1 py-1 text-xs"
                                            :class="line.direction === 'entree' ? 'text-emerald-700' : 'text-red-600'">
                                        <option value="entree">Entrée</option>
                                        <option value="sortie">Sortie</option>
                                    </select>
                                </td>
                                @foreach(range(1,12) as $m)
                                <td class="px-1 py-2">
                                    <input type="number" :name="`lines[${i}][months][{{ $m }}]`" x-model.number="line.months[{{ $m }}]" min="0" step="1000"
                                           class="w-20 border border-gray-200 rounded px-1 py-1 text-xs text-right font-mono focus:ring-1 focus:ring-emerald-300">
                                </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-mono font-semibold text-gray-700" x-text="fmt(lineTotal(line))"></td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" @click="removeLine(i)" class="text-red-400 hover:text-red-600">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('notes') }}</textarea>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('tresorerie.budgets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuler</a>
            <button type="submit" class="px-6 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">Enregistrer le budget</button>
        </div>
    </form>
</div>
@endsection
