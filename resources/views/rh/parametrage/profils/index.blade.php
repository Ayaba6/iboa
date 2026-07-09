@extends('layouts.erp')
@section('title', 'Profils de paie')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Profils de paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Profils de paie</h1>
        <p class="text-sm text-gray-500 mt-1">Ensembles de rubriques configurés par catégorie d'employé — cadre, non-cadre, dirigeant…</p>
    </div>
    <a href="{{ route('rh.profils.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouveau profil
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

@php
$catColors = [
    'cadre' => 'indigo', 'non_cadre' => 'blue', 'dirigeant' => 'violet',
    'interim' => 'amber', 'stagiaire' => 'emerald', 'autre' => 'gray',
];
@endphp

@if($profiles->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <div class="w-16 h-16 bg-[#eef5f0] rounded-[4px] flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
    </div>
    <p class="text-gray-500 font-medium">Aucun profil de paie</p>
    <p class="text-gray-400 text-sm mt-1">Créez un profil par catégorie d'employé pour personnaliser les rubriques appliquées</p>
    <a href="{{ route('rh.profils.create') }}" class="mt-4 inline-block text-emerald-700 text-sm font-medium">+ Créer le premier profil</a>
</div>
@else
<div class="grid grid-cols-1 gap-3">
    @foreach($profiles as $profile)
    @php $color = $catColors[$profile->categorie] ?? 'gray'; @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between px-3 py-1.5">
            <div class="flex items-center gap-4">
                {{-- Icône catégorie --}}
                <div class="w-11 h-11 rounded-[4px] flex items-center justify-center flex-shrink-0
                    {{ $color === 'indigo' ? 'bg-emerald-100' : ($color === 'blue' ? 'bg-blue-100' : ($color === 'violet' ? 'bg-violet-100' : ($color === 'amber' ? 'bg-amber-100' : ($color === 'emerald' ? 'bg-emerald-100' : 'bg-gray-100')))) }}">
                    <svg class="w-5 h-5 {{ $color === 'indigo' ? 'text-emerald-700' : ($color === 'blue' ? 'text-blue-600' : ($color === 'violet' ? 'text-violet-600' : ($color === 'amber' ? 'text-amber-600' : ($color === 'emerald' ? 'text-emerald-600' : 'text-gray-500')))) }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>

                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-gray-900">{{ $profile->libelle }}</h3>
                        <code class="text-xs font-mono bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $profile->code }}</code>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium
                            {{ $color === 'indigo' ? 'bg-emerald-100 text-emerald-800' : ($color === 'blue' ? 'bg-blue-100 text-blue-700' : ($color === 'violet' ? 'bg-violet-100 text-violet-700' : ($color === 'amber' ? 'bg-amber-100 text-amber-700' : ($color === 'emerald' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600')))) }}">
                            {{ $profile->categorie_label }}
                        </span>
                        @if($profile->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">Par défaut</span>
                        @endif
                        @if(!$profile->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 mt-1 text-xs text-gray-400">
                        @if($profile->plan)
                            <span>Plan : {{ $profile->plan->libelle }}</span>
                        @else
                            <span class="italic">Sans plan associé</span>
                        @endif
                        <span>{{ $profile->rubrics_count }} rubrique(s)</span>
                        <span>{{ $profile->contracts_count }} contrat(s)</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('rh.profils.show', $profile) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-[4px] text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                    Rubriques
                </a>
                <a href="{{ route('rh.profils.edit', $profile) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#eef5f0] border border-emerald-200 rounded-[4px] text-xs font-medium text-emerald-800 hover:bg-emerald-100 transition-colors">
                    Modifier
                </a>
                <form method="POST" action="{{ route('rh.profils.destroy', $profile) }}"
                      onsubmit="return confirm('Supprimer ce profil ?')">
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
