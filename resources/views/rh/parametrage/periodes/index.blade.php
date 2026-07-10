@extends('layouts.erp')
@section('title', 'Périodes de paie')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Périodes de paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Périodes de paie</h1>
        <p class="text-sm text-gray-500 mt-1">Gérez le cycle de vie de chaque mois de paie : ouverture, clôture, verrouillage.</p>
    </div>
    {{-- Formulaire de création rapide --}}
    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Créer une période
        </button>
        <div x-show="open" x-cloak @click.outside="open = false"
             class="absolute right-0 mt-2 w-64 bg-white rounded-[4px] border border-gray-300 shadow-lg p-4 z-10">
            <form method="POST" action="{{ route('rh.periodes.store') }}">
                @csrf
                <label class="block text-sm font-medium text-gray-700 mb-1">Mois / Année</label>
                <input type="month" name="month"
                       value="{{ now()->format('Y-m') }}"
                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 mb-3"
                       required>
                <button type="submit"
                        class="w-full px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                    Créer
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif
@if(session('info'))
<div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('info') }}</div>
@endif

{{-- Légende des statuts --}}
<div class="mb-6 bg-white border border-gray-300 rounded-[4px] p-4">
    <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide mb-3">Cycle de vie d'une période</p>
    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600">
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <strong>Ouverte</strong> — Saisie et modification libres
        </div>
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <strong>Clôturée</strong> — En cours de validation comptable
        </div>
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
            <strong>Verrouillée</strong> — Aucune modification possible
        </div>
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
            <strong>Archivée</strong> — État terminal
        </div>
    </div>
</div>

@if($byYear->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <div class="w-16 h-16 bg-[#eef5f0] rounded-[4px] flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucune période créée</h3>
    <p class="text-gray-500 text-sm mb-6">
        Les périodes peuvent être créées manuellement ou automatiquement lors du calcul de paie.
    </p>
</div>
@else

@foreach($byYear as $year => $periods)
<div class="mb-8">
    <div class="flex items-center gap-3 mb-4">
        <h2 class="text-[16px] font-bold text-gray-800">{{ $year }}</h2>
        <div class="flex-1 h-px bg-gray-200"></div>
        <span class="text-xs text-gray-400">{{ $periods->count() }} période(s)</span>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full divide-y divide-gray-100">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Période</th>
                    <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Dates</th>
                    <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Bulletins</th>
                    <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Traçabilité</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($periods as $period)
                @php
                    $statusColors = [
                        'open'     => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700'],
                        'closed'   => ['dot' => 'bg-amber-500',   'badge' => 'bg-amber-100 text-amber-700'],
                        'locked'   => ['dot' => 'bg-red-500',     'badge' => 'bg-red-100 text-red-700'],
                        'archived' => ['dot' => 'bg-gray-400',    'badge' => 'bg-gray-100 text-gray-500'],
                    ];
                    $sc = $statusColors[$period->status] ?? $statusColors['open'];
                @endphp
                <tr class="hover:bg-gray-50 transition-colors" x-data="{ unlockOpen: false }">
                    <td class="px-3 py-1.5">
                        <div class="font-semibold text-gray-900">{{ $period->libelle }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $period->code }}</div>
                    </td>
                    <td class="px-3 py-1.5 text-sm text-gray-600">
                        {{ $period->period_start->format('d/m/Y') }} → {{ $period->period_end->format('d/m/Y') }}
                    </td>
                    <td class="px-3 py-1.5">
                        <span class="text-sm font-medium text-gray-900">{{ $period->items_count }}</span>
                        <span class="text-xs text-gray-400"> bulletin(s)</span>
                    </td>
                    <td class="px-3 py-1.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[3px] text-[11px] font-medium {{ $sc['badge'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                            {{ $period->status_label }}
                        </span>
                        @if($period->isLocked() && $period->unlock_reason)
                        <div class="text-xs text-amber-600 mt-1 max-w-xs truncate" title="{{ $period->unlock_reason }}">
                            ⚠ Déjà déverrouillée : {{ Str::limit($period->unlock_reason, 40) }}
                        </div>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-xs text-gray-400 space-y-0.5">
                        @if($period->closed_at)
                        <div>Clôturée le {{ $period->closed_at->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($period->locked_at)
                        <div>Verrouillée le {{ $period->locked_at->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($period->unlocked_at)
                        <div class="text-amber-500">Déverr. le {{ $period->unlocked_at->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($period->archived_at)
                        <div>Archivée le {{ $period->archived_at->format('d/m/Y H:i') }}</div>
                        @endif
                        @if(!$period->closed_at && !$period->locked_at)
                        <div class="text-gray-300">—</div>
                        @endif
                    </td>
                    <td class="px-3 py-1.5">
                        <div class="flex items-center gap-1 justify-end flex-wrap">

                            {{-- CLÔTURER (open → closed) --}}
                            @if($period->isOpen())
                            <form method="POST" action="{{ route('rh.periodes.close', $period) }}"
                                  onsubmit="return confirm('Clôturer la période « {{ $period->libelle }} » ?\nLes bulletins restent modifiables jusqu\'au verrouillage.')">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 rounded-[4px] hover:bg-amber-100 transition-colors">
                                    Clôturer
                                </button>
                            </form>
                            @endif

                            {{-- ROUVRIR (closed → open) --}}
                            @if($period->isClosed())
                            <form method="POST" action="{{ route('rh.periodes.reopen', $period) }}"
                                  onsubmit="return confirm('Réouvrir la période « {{ $period->libelle }} » ?')">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-[4px] hover:bg-emerald-100 transition-colors">
                                    Réouvrir
                                </button>
                            </form>
                            @endif

                            {{-- VERROUILLER (open ou closed → locked) --}}
                            @if($period->isOpen() || $period->isClosed())
                            <form method="POST" action="{{ route('rh.periodes.lock', $period) }}"
                                  onsubmit="return confirm('⚠ VERROUILLER définitivement la période « {{ $period->libelle }} » ?\n\nAucune modification ne sera plus possible sur les bulletins de ce mois.\n\nCette action est irréversible sans justification.')">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-700 border border-red-200 rounded-[4px] hover:bg-red-100 transition-colors">
                                    <svg class="w-3 h-3 inline-block mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Verrouiller
                                </button>
                            </form>
                            @endif

                            {{-- DÉVERROUILLER (locked → open) — formulaire inline avec justification --}}
                            @if($period->isLocked())
                            <button @click="unlockOpen = !unlockOpen"
                                    class="px-3 py-1.5 text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200 rounded-[4px] hover:bg-orange-100 transition-colors">
                                Déverrouiller
                            </button>
                            <div x-show="unlockOpen" x-cloak
                                 class="absolute right-4 mt-2 w-80 bg-white rounded-[4px] border border-orange-200 shadow-xl p-4 z-20">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                    <p class="text-sm font-semibold text-gray-900">Action dangereuse</p>
                                </div>
                                <p class="text-xs text-gray-600 mb-3">
                                    Le déverrouillage permet de modifier des bulletins déjà clôturés.
                                    Cette action est enregistrée avec votre identifiant.
                                </p>
                                <form method="POST" action="{{ route('rh.periodes.unlock', $period) }}">
                                    @csrf
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Justification <span class="text-red-500">*</span> (min. 10 caractères)
                                    </label>
                                    <textarea name="unlock_reason" rows="3" required minlength="10"
                                              class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-xs focus:ring-1 focus:ring-orange-500 focus:border-orange-500 mb-3"
                                              placeholder="Erreur sur le bulletin de M. X — correction nécessaire…"></textarea>
                                    <div class="flex gap-2">
                                        <button type="button" @click="unlockOpen = false"
                                                class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded-[4px] hover:bg-gray-50">
                                            Annuler
                                        </button>
                                        <button type="submit"
                                                class="flex-1 px-3 py-1.5 text-xs font-medium bg-orange-600 text-white rounded-[4px] hover:bg-orange-700"
                                                onclick="return confirm('Confirmer le déverrouillage ?')">
                                            Confirmer
                                        </button>
                                    </div>
                                </form>
                            </div>

                            {{-- ARCHIVER (locked → archived) --}}
                            <form method="POST" action="{{ route('rh.periodes.archive', $period) }}"
                                  onsubmit="return confirm('Archiver définitivement « {{ $period->libelle }} » ?\n\nÉtat terminal — aucune action possible ensuite.')">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200 rounded-[4px] hover:bg-gray-200 transition-colors">
                                    Archiver
                                </button>
                            </form>
                            @endif

                            {{-- SUPPRIMER (open, sans bulletins) --}}
                            @if($period->isOpen() && $period->items_count === 0)
                            <form method="POST" action="{{ route('rh.periodes.destroy', $period) }}"
                                  onsubmit="return confirm('Supprimer la période « {{ $period->libelle }} » ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="p-1.5 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-[4px] transition-colors" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endif

                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach
@endif

{{-- Résumé en pied de page --}}
@if(!$byYear->isEmpty())
<div class="mt-4 flex items-center gap-6 text-sm text-gray-500">
    <span>
        <strong class="text-emerald-600">{{ $openCount }}</strong> période(s) ouverte(s)
    </span>
    <span>
        <strong class="text-red-600">{{ $lockedCount }}</strong> période(s) verrouillée(s)
    </span>
</div>
@endif
@endsection
