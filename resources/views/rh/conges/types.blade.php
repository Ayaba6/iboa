@extends('layouts.erp')
@section('title', 'Types de congés')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.conges.index') }}" class="hover:text-gray-700">Congés</a>
    <span class="mx-1">/</span><span>Types</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-[16px] font-bold text-gray-900">Types de congés</h1>
    <button onclick="document.getElementById('modal-type').classList.remove('hidden')"
            class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
        + Nouveau type
    </button>
</div>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-1.5 text-left">Nom</th>
                <th class="px-3 py-1.5 text-left">Code</th>
                <th class="px-3 py-1.5 text-center">Jours/an</th>
                <th class="px-3 py-1.5 text-center">Payé</th>
                <th class="px-3 py-1.5 text-center">Déduction salaire</th>
                <th class="px-3 py-1.5 text-center">Actif</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($types as $t)
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-1.5 font-medium">
                <span class="inline-block w-3 h-3 rounded-full bg-{{ $t->color }}-500 mr-2"></span>
                {{ $t->name }}
            </td>
            <td class="px-3 py-1.5 font-mono text-xs text-gray-500">{{ $t->code }}</td>
            <td class="px-3 py-1.5 text-center">{{ $t->days_per_year > 0 ? $t->days_per_year : '—' }}</td>
            <td class="px-3 py-1.5 text-center">{{ $t->is_paid ? '✓' : '✗' }}</td>
            <td class="px-3 py-1.5 text-center">{{ $t->deduct_from_salary ? '✓' : '—' }}</td>
            <td class="px-3 py-1.5 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $t->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                    {{ $t->is_active ? 'Actif' : 'Inactif' }}
                </span>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Aucun type. Créez-en un.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Modal --}}
<div id="modal-type" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-96 p-6">
        <h3 class="text-base font-semibold mb-4">Nouveau type de congé</h3>
        <form method="POST" action="{{ route('rh.conges.types.store') }}">
            @csrf
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
                        <input type="text" name="name" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code *</label>
                        <input type="text" name="code" required maxlength="20" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jours/an (0 = illimité)</label>
                    <input type="number" name="days_per_year" value="0" min="0" max="365" step="0.5"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_paid" value="1" checked class="rounded"> Congé payé
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="deduct_from_salary" value="1" class="rounded"> Déduire du salaire
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                    <select name="color" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        @foreach(['blue'=>'Bleu','green'=>'Vert','yellow'=>'Jaune','red'=>'Rouge','purple'=>'Violet','orange'=>'Orange','pink'=>'Rose','gray'=>'Gris'] as $c=>$l)
                        <option value="{{ $c }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-type').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium">Créer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
