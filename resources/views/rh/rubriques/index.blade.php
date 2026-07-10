@extends('layouts.erp')
@section('title', 'Rubriques de paie')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Rubriques de paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Rubriques de paie</h1>
        <p class="text-sm text-gray-500 mt-1">Codes de paie paramétrables — gains, retenues, cotisations.</p>
    </div>
    <a href="{{ route('rh.rubriques.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouvelle rubrique
    </a>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-[4px] text-emerald-700 text-sm">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-[4px] text-red-700 text-sm">
        {{ session('error') }}
    </div>
@endif

{{-- Filtres --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Code ou libellé…"
           class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm w-56">

    <select name="type" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les types</option>
        <option value="gain"           @selected(request('type') === 'gain')>Gains</option>
        <option value="retenue"        @selected(request('type') === 'retenue')>Retenues</option>
        <option value="cotisation_pat" @selected(request('type') === 'cotisation_pat')>Cotisations patronales</option>
        <option value="information"    @selected(request('type') === 'information')>Informations</option>
    </select>

    <select name="active" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Toutes</option>
        <option value="1" @selected(request('active') === '1')>Actives</option>
        <option value="0" @selected(request('active') === '0')>Inactives</option>
    </select>

    <button type="submit" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-[4px] text-sm hover:bg-gray-200">
        Filtrer
    </button>
    @if(request()->hasAny(['search','type','active']))
        <a href="{{ route('rh.rubriques.index') }}" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">
            Réinitialiser
        </a>
    @endif
</form>

{{-- Tableau --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Libellé</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Calcul</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Taux / Base</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase">Imposable</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase">CNSS</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase">Brut</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase">Actif</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rubrics as $rubric)
                    @php
                        $typeColors = [
                            'gain'           => 'bg-emerald-100 text-emerald-700',
                            'retenue'        => 'bg-red-100 text-red-700',
                            'cotisation_pat' => 'bg-blue-100 text-blue-700',
                            'information'    => 'bg-gray-100 text-gray-600',
                        ];
                        $typeColor = $typeColors[$rubric->type] ?? 'bg-gray-100 text-gray-600';
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $rubric->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5">
                            <code class="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">
                                {{ $rubric->code }}
                            </code>
                        </td>
                        <td class="px-3 py-1.5 font-medium text-gray-800">{{ $rubric->libelle }}</td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $typeColor }}">
                                {{ $rubric->type_label }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $rubric->calc_type_label }}</td>
                        <td class="px-3 py-1.5 text-gray-600">
                            @if($rubric->calc_type === 'taux' && $rubric->rate)
                                {{ number_format($rubric->rate, 2) }} %
                                @if($rubric->base_ref)
                                    <span class="text-xs text-gray-400">/ {{ $rubric->base_ref }}</span>
                                @endif
                            @elseif($rubric->calc_type === 'fixe' && $rubric->fixed_amount)
                                {{ number_format($rubric->fixed_amount) }} FCFA
                            @elseif($rubric->calc_type === 'formule')
                                <span class="text-xs text-purple-600 font-mono">formule</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @if($rubric->is_taxable)
                                <svg class="w-4 h-4 text-emerald-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @if($rubric->is_cnss_base)
                                <svg class="w-4 h-4 text-emerald-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @if($rubric->is_in_brut)
                                <svg class="w-4 h-4 text-emerald-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @if($rubric->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">Oui</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">Non</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('rh.rubriques.edit', $rubric) }}"
                                   class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">
                                    Modifier
                                </a>
                                @php $systemCodes = ['CNSS_SAL','CNSS_PAT','IUTS','BRUT','NET_PAYE','SAL_BASE']; @endphp
                                @unless(in_array($rubric->code, $systemCodes))
                                <form method="POST" action="{{ route('rh.rubriques.destroy', $rubric) }}"
                                      onsubmit="return confirm('Supprimer la rubrique {{ $rubric->code }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">
                                        Supprimer
                                    </button>
                                </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            Aucune rubrique trouvée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rubrics->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $rubrics->links() }}
        </div>
    @endif
</div>
@endsection
