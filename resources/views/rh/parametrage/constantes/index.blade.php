@extends('layouts.erp')
@section('title', 'Constantes de paie')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Constantes</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Constantes de paie</h1>
        <p class="text-sm text-gray-500 mt-1">SMIG, taux CNSS, barèmes fiscaux, heures mensuelles — modifiables sans toucher au code</p>
    </div>
    <a href="{{ route('rh.constantes.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouvelle constante
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

{{-- Filtres par groupe --}}
<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('rh.constantes.index') }}"
       class="px-3 py-1.5 rounded-[4px] text-xs font-medium border transition-colors {{ !request('groupe') ? 'bg-emerald-700 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        Toutes
    </a>
    @foreach($groupes as $key => $label)
    <a href="{{ route('rh.constantes.index', ['groupe' => $key]) }}"
       class="px-3 py-1.5 rounded-[4px] text-xs font-medium border transition-colors {{ request('groupe') === $key ? 'bg-emerald-700 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Tableau groupé par catégorie --}}
@forelse($grouped as $groupeKey => $items)
@php $groupeLabel = $groupes[$groupeKey] ?? ucfirst($groupeKey); @endphp
<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden mb-4">
    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="text-sm font-semibold text-gray-700">{{ $groupeLabel }}</h2>
        <span class="text-xs text-gray-400">{{ $items->count() }} constante(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100">
                <tr class="text-left text-xs text-gray-400 font-semibold uppercase tracking-wide">
                    <th class="px-3 py-2.5">Code</th>
                    <th class="px-3 py-2.5">Libellé</th>
                    <th class="px-3 py-2.5">Type</th>
                    <th class="px-3 py-2.5 text-right">Valeur</th>
                    <th class="px-3 py-2.5">Validité</th>
                    <th class="px-3 py-2.5 text-center">Statut</th>
                    <th class="px-3 py-2.5 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($items as $c)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-1.5">
                        <code class="text-xs font-mono bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $c->code }}</code>
                    </td>
                    <td class="px-3 py-1.5 font-medium text-gray-800">{{ $c->libelle }}</td>
                    <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $c->value_type_label }}</td>
                    <td class="px-3 py-1.5 text-right font-mono font-semibold text-gray-900">
                        {{ $c->value_formatted }}
                    </td>
                    <td class="px-3 py-1.5 text-xs text-gray-500">
                        @if($c->valid_from)
                            Du {{ $c->valid_from->format('d/m/Y') }}
                            @if($c->valid_until)→ {{ $c->valid_until->format('d/m/Y') }}@else<span class="text-gray-400">→ illimitée</span>@endif
                        @else
                            <span class="text-gray-400 italic">Toujours valide</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($c->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('rh.constantes.edit', $c) }}"
                               class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Modifier</a>
                            <a href="{{ route('rh.constantes.history', $c->code) }}"
                               class="text-xs text-gray-500 hover:text-gray-700">Historique</a>
                            <form method="POST" action="{{ route('rh.constantes.destroy', $c) }}"
                                  onsubmit="return confirm('Supprimer cette constante ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <p class="text-gray-400">Aucune constante définie.</p>
    <a href="{{ route('rh.constantes.create') }}" class="mt-3 inline-block text-emerald-700 text-sm font-medium">+ Créer la première constante</a>
</div>
@endforelse
@endsection
