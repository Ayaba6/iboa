@extends('layouts.erp')
@section('title', 'Contrats — RH')

@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Contrats</span>
@endsection

@section('content')
<div class="space-y-3" x-data="{ modalOpen: false }">

    {{-- ── Header ─────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Contrats de travail</h1>
            <p class="text-sm text-gray-500 mt-0.5">Liste de tous les contrats de l'entreprise</p>
        </div>
        <div class="flex gap-2">
            {{-- Export CSV --}}
            <a href="{{ route('rh.contrats.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 bg-white text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exporter CSV
            </a>
            {{-- Nouveau contrat --}}
            <button @click="modalOpen = true"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau contrat
            </button>
        </div>
    </div>

    {{-- ── Stats ───────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Total',    'value' => $stats['total'],    'color' => 'text-gray-900'],
            ['label' => 'Actifs',   'value' => $stats['actifs'],   'color' => 'text-emerald-600'],
            ['label' => 'Terminés', 'value' => $stats['termines'], 'color' => 'text-amber-600'],
            ['label' => 'Résiliés', 'value' => $stats['resilies'], 'color' => 'text-red-600'],
        ] as $s)
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <div class="text-xs text-gray-500 mb-1">{{ $s['label'] }}</div>
            <div class="text-[16px] font-bold {{ $s['color'] }}">{{ $s['value'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── Filtres ─────────────────────────────────────────────────────────────── --}}
    <form method="GET" id="filter-form" class="flex flex-wrap gap-3 bg-white border border-gray-300 rounded-[4px] p-4">
        {{-- Recherche --}}
        <div class="relative flex-1 min-w-[200px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Nom, prénom ou matricule…"
                   class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-[4px] text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>

        {{-- Statut --}}
        <select name="status" onchange="document.getElementById('filter-form').submit()"
                class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm bg-white focus:ring-emerald-500 focus:border-emerald-500">
            <option value="">Tous statuts</option>
            @foreach($statusOptions as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>

        {{-- Type --}}
        <select name="type" onchange="document.getElementById('filter-form').submit()"
                class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm bg-white focus:ring-emerald-500 focus:border-emerald-500">
            <option value="">Tous types</option>
            @foreach($typeOptions as $v => $l)
                <option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>
            @endforeach
        </select>

        <button type="submit"
                class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700 transition-colors">
            Filtrer
        </button>

        @if(request()->hasAny(['search','status','type']))
        <a href="{{ route('rh.contrats.index') }}"
           class="px-3 py-1.5 text-gray-500 rounded-[4px] text-sm hover:bg-gray-100 flex items-center gap-1 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Réinitialiser
        </a>
        @endif
    </form>

    {{-- ── Tableau ─────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky">
                <thead>
                    <tr>
                        <th class="text-left">Matricule</th>
                        <th class="text-left">Employé</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Début</th>
                        <th class="text-left">Fin</th>
                        <th class="text-right">Salaire base</th>
                        <th class="text-center">Statut</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                    @php
                        $badgeClass = match($contract->status) {
                            'actif'   => 'bg-emerald-100 text-emerald-800',
                            'termine' => 'bg-amber-100 text-amber-800',
                            'resilie' => 'bg-red-100 text-red-800',
                            default   => 'bg-gray-100 text-gray-800',
                        };
                        $badgeLabel = match($contract->status) {
                            'actif'   => 'Actif',
                            'termine' => 'Terminé',
                            'resilie' => 'Résilié',
                            default   => ucfirst($contract->status),
                        };
                    @endphp
                    <tr>
                        <td class="font-mono text-xs text-gray-500">
                            {{ $contract->employee?->matricule ?? '—' }}
                        </td>
                        <td>
                            <a href="{{ route('rh.employes.show', $contract->employee_id) }}"
                               class="font-medium text-gray-900 hover:text-emerald-700 transition-colors">
                                {{ $contract->employee?->full_name ?? '—' }}
                            </a>
                            @if($contract->employee?->department?->name)
                            <div class="text-xs text-gray-400">{{ $contract->employee->department->name }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                {{ $contract->type }}
                            </span>
                        </td>
                        <td class="tabular-nums text-gray-600">
                            {{ $contract->start_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="tabular-nums text-gray-600">
                            @if($contract->end_date)
                                {{ $contract->end_date->format('d/m/Y') }}
                                @if($contract->status === 'actif' && $contract->end_date->isPast())
                                    <span class="ml-1 text-xs text-red-600 font-medium">(dépassé)</span>
                                @elseif($contract->status === 'actif' && $contract->end_date->diffInDays(now()) <= 30)
                                    <span class="ml-1 text-xs text-orange-500 font-medium">(bientôt)</span>
                                @endif
                            @else
                                <span class="text-gray-400">Indéterminée</span>
                            @endif
                        </td>
                        <td class="text-right font-mono font-medium tabular-nums">
                            {{ number_format($contract->base_salary, 0, ',', ' ') }} F
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $badgeClass }}">
                                {{ $badgeLabel }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                {{-- Voir employé --}}
                                <a href="{{ route('rh.employes.show', $contract->employee_id) }}"
                                   class="p-1.5 rounded-[4px] text-emerald-600 hover:bg-emerald-50 transition-colors"
                                   title="Voir l'employé">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- PDF contrat --}}
                                <a href="{{ route('rh.contrats.pdf', $contract) }}"
                                   class="p-1.5 rounded-[4px] text-red-500 hover:bg-red-50 transition-colors"
                                   title="Télécharger PDF"
                                   data-loading data-loading-text="Génération PDF...">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                @if($contract->status === 'actif')
                                {{-- Terminer --}}
                                <form method="POST" action="{{ route('rh.contrats.terminate', $contract) }}"
                                      data-confirm="Terminer ce contrat ?"
                                      data-confirm-title="Terminer le contrat"
                                      data-confirm-label="Terminer"
                                      data-confirm-danger="false"
                                      class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="p-1.5 rounded-[4px] text-amber-500 hover:bg-amber-50 transition-colors"
                                            title="Terminer le contrat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                {{-- Résilier --}}
                                <form method="POST" action="{{ route('rh.contrats.resilier', $contract) }}"
                                      data-confirm="Résilier ce contrat ? Cette action est irréversible."
                                      data-confirm-title="Résilier le contrat"
                                      data-confirm-label="Résilier"
                                      class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="p-1.5 rounded-[4px] text-red-500 hover:bg-red-50 transition-colors"
                                            title="Résilier le contrat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                                @else
                                {{-- Supprimer (terminé / résilié uniquement) --}}
                                <form method="POST" action="{{ route('rh.contrats.destroy', $contract) }}"
                                      data-confirm="Supprimer définitivement ce contrat ?"
                                      data-confirm-title="Supprimer le contrat"
                                      class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 rounded-[4px] text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Supprimer ce contrat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm italic">Aucun contrat trouvé avec ces critères.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contracts->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $contracts->links() }}
        </div>
        @endif
    </div>

    {{-- ── Modal Nouveau contrat ────────────────────────────────────────────────── --}}
    <div x-show="modalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @click.self="modalOpen = false"
         style="display:none">
        <div class="bg-white rounded-[4px] shadow-xl w-full max-w-lg"
             @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Nouveau contrat</h2>
                <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('rh.contrats.store') }}" class="px-6 py-5 space-y-4">
                @csrf

                {{-- Employé --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Employé <span class="text-red-500">*</span>
                    </label>
                    <select name="employee_id" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">— Sélectionner un employé —</option>
                        @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">
                            {{ $emp->last_name }} {{ $emp->first_name }} ({{ $emp->matricule }})
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Type + Salaire --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="stage">Stage</option>
                            <option value="consultant">Consultant</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Salaire base (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="base_salary" min="0" required placeholder="Ex. 150 000"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Date de début <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="start_date" required value="{{ date('Y-m-d') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Date de fin <span class="text-gray-400 font-normal text-xs">(CDD uniquement)</span>
                        </label>
                        <input type="date" name="end_date"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Remarques, conditions particulières…"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 resize-none"></textarea>
                </div>

                <p class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-2">
                    ⚠️ Si l'employé a déjà un contrat actif, il sera automatiquement clôturé (statut : Terminé).
                </p>

                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" @click="modalOpen = false"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
