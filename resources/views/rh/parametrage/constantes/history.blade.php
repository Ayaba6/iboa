@extends('layouts.erp')
@section('title', 'Historique · ' . $code)
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.constantes.index') }}" class="hover:text-gray-700">Constantes</a>
    <span class="mx-1">/</span><span>{{ $code }} — Historique</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">
            Historique de <code class="text-emerald-700 font-mono">{{ $code }}</code>
        </h1>
        <p class="text-sm text-gray-500 mt-1">Toutes les versions enregistrées pour cette constante</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('rh.constantes.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 rounded-[4px] text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            ← Liste
        </a>
        <a href="{{ route('rh.constantes.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle version
        </a>
    </div>
</div>

@if($history->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-200 p-16 text-center">
    <p class="text-gray-400">Aucune version trouvée pour le code <code>{{ $code }}</code>.</p>
</div>
@else
<div class="bg-white rounded-[4px] border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50">
                <tr class="text-left text-xs text-gray-400 font-semibold uppercase tracking-wide">
                    <th class="px-5 py-3">Version</th>
                    <th class="px-5 py-3">Libellé</th>
                    <th class="px-5 py-3 text-right">Valeur</th>
                    <th class="px-5 py-3">Unité</th>
                    <th class="px-5 py-3">Période de validité</th>
                    <th class="px-5 py-3 text-center">Statut</th>
                    <th class="px-5 py-3">Créé par</th>
                    <th class="px-5 py-3">Créé le</th>
                    <th class="px-5 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($history as $i => $item)
                <tr class="hover:bg-gray-50 transition-colors {{ $i === 0 ? 'bg-[#eef5f0]/40' : '' }}">
                    <td class="px-5 py-3">
                        @if($i === 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-emerald-100 text-emerald-800">Actuelle</span>
                        @else
                            <span class="text-xs text-gray-400">v{{ $history->count() - $i }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-gray-700">{{ $item->libelle }}</td>
                    <td class="px-5 py-3 text-right font-mono font-semibold text-gray-900">
                        {{ $item->value_formatted }}
                    </td>
                    <td class="px-5 py-3 text-gray-500 text-xs">{{ $item->unit ?: '—' }}</td>
                    <td class="px-5 py-3 text-xs text-gray-500">
                        @if($item->valid_from)
                            Du {{ $item->valid_from->format('d/m/Y') }}
                            @if($item->valid_until)
                                → {{ $item->valid_until->format('d/m/Y') }}
                            @else
                                <span class="text-gray-400">→ illimitée</span>
                            @endif
                        @else
                            <span class="text-gray-400 italic">Toujours valide</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($item->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-500">
                        {{ $item->createdBy?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-400">
                        {{ $item->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-5 py-3 text-center">
                        <a href="{{ route('rh.constantes.edit', $item) }}"
                           class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Modifier</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
