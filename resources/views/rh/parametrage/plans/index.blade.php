@extends('layouts.erp')
@section('title', 'Plans de paie')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Plans de paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Plans de paie</h1>
        <p class="text-sm text-gray-500 mt-1">Ensembles de rubriques organisés par pays ou type d'employé</p>
    </div>
    <a href="{{ route('rh.plans.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouveau plan
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

@if($plans->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <div class="w-16 h-16 bg-[#eef5f0] rounded-[4px] flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
    </div>
    <p class="text-gray-500 font-medium">Aucun plan de paie défini</p>
    <p class="text-gray-400 text-sm mt-1">Créez votre premier plan pour organiser vos rubriques de paie</p>
    <a href="{{ route('rh.plans.create') }}" class="mt-4 inline-block text-emerald-700 text-sm font-medium">+ Créer le premier plan</a>
</div>
@else
<div class="grid grid-cols-1 gap-4">
    @foreach($plans as $plan)
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between px-3 py-1.5">
            <div class="flex items-center gap-4">
                {{-- Badge default --}}
                @if($plan->is_default)
                <div class="w-10 h-10 bg-emerald-100 rounded-[4px] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                @else
                <div class="w-10 h-10 bg-gray-100 rounded-[4px] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                @endif

                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-900">{{ $plan->libelle }}</h3>
                        <code class="text-xs font-mono bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $plan->code }}</code>
                        @if($plan->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">Par défaut</span>
                        @endif
                        @if(!$plan->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 mt-1">
                        @if($plan->pays)
                        <span class="text-xs text-gray-400">{{ $plan->pays }} {{ $plan->country_code ? '('.$plan->country_code.')' : '' }}</span>
                        @endif
                        @if($plan->devise)
                        <span class="text-xs text-gray-400">{{ $plan->devise }}</span>
                        @endif
                        @if($plan->description)
                        <span class="text-xs text-gray-400 truncate max-w-xs">{{ $plan->description }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('rh.plans.show', $plan) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-[4px] text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Voir
                </a>
                <a href="{{ route('rh.plans.edit', $plan) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#eef5f0] border border-emerald-200 rounded-[4px] text-xs font-medium text-emerald-800 hover:bg-emerald-100 transition-colors">
                    Modifier
                </a>
                <div x-data="{ open: false, code: '{{ $plan->code }}-COPY', libelle: 'Copie de {{ addslashes($plan->libelle) }}' }">
                    <button @click="open = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-[4px] text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Dupliquer
                    </button>
                    {{-- Modal duplication --}}
                    <div x-show="open" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                         @keydown.escape.window="open = false">
                        <div class="bg-white rounded-[4px] shadow-xl w-full max-w-sm mx-4 p-6" @click.stop>
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Dupliquer le plan</h3>
                            <form method="POST" action="{{ route('rh.plans.duplicate', $plan) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="text-xs font-medium text-gray-600 block mb-1">Nouveau code</label>
                                    <input type="text" name="code" x-model="code" required
                                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-emerald-300">
                                </div>
                                <div class="mb-5">
                                    <label class="text-xs font-medium text-gray-600 block mb-1">Nouveau libellé</label>
                                    <input type="text" name="libelle" x-model="libelle" required
                                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit"
                                            class="flex-1 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                                        Dupliquer
                                    </button>
                                    <button type="button" @click="open = false"
                                            class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-[4px] text-sm font-medium hover:bg-gray-50">
                                        Annuler
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('rh.plans.destroy', $plan) }}"
                      onsubmit="return confirm('Supprimer ce plan ? Cette action est irréversible.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">Suppr.</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
