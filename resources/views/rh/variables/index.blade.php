@extends('layouts.erp')
@section('title', 'Variables mensuelles — RH')

@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Variables mensuelles</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ── Header ─────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Variables mensuelles</h1>
            <p class="text-sm text-gray-500 mt-0.5">Primes, heures sup., absences et retenues saisies par bulletin</p>
        </div>
        <a href="{{ route('rh.paie.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau bulletin
        </a>
    </div>

    @if($runs->isEmpty())
    {{-- ── État vide ─────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-16 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="text-gray-500 font-medium">Aucun bulletin de paie disponible.</p>
        <a href="{{ route('rh.paie.create') }}"
           class="mt-4 inline-flex items-center gap-1.5 text-emerald-700 hover:text-emerald-900 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Créer le premier bulletin
        </a>
    </div>

    @else
    {{-- ── Liste des bulletins avec accordéons ────────────────────────────────── --}}
    <div class="space-y-3">
        @foreach($runs as $run)
        @php
            $periodDate  = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
            $periodLabel = ucfirst($periodDate->translatedFormat('F Y'));
            $statusClass = match($run->status) {
                'brouillon' => 'bg-gray-100 text-gray-600',
                'calcule'   => 'bg-blue-100 text-blue-700',
                'valide'    => 'bg-green-100 text-green-700',
                'paye'      => 'bg-emerald-100 text-emerald-700',
                default     => 'bg-gray-100 text-gray-600',
            };
            $statusLabel = match($run->status) {
                'brouillon' => 'Brouillon',
                'calcule'   => 'Calculé',
                'valide'    => 'Validé',
                'paye'      => 'Payé',
                default     => ucfirst($run->status),
            };
            $varUrl = route('rh.paie.variables', $run);
        @endphp

        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden"
             x-data="{
                 open: false,
                 loading: false,
                 loaded: false,
                 vars: [],
                 async toggle() {
                     if (this.open) { this.open = false; return; }
                     if (!this.loaded) {
                         this.loading = true;
                         try {
                             const r = await fetch('{{ $varUrl }}', {
                                 headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                             });
                             this.vars = await r.json();
                             this.loaded = true;
                         } catch(e) {
                             this.vars = [];
                             this.loaded = true;
                             window.toast('Erreur lors du chargement des variables.', 'error');
                         }
                         this.loading = false;
                     }
                     this.open = true;
                 }
             }">

            {{-- En-tête accordéon --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div>
                        <span class="font-semibold text-gray-900">{{ $periodLabel }}</span>
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                        <span class="ml-2 text-sm text-gray-400">{{ $run->items_count ?? 0 }} employé(s)</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('rh.paie.show', $run) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-emerald-700 hover:text-emerald-900 font-medium rounded-[4px] hover:bg-emerald-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ouvrir
                    </a>

                    <button @click="toggle()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-[4px] border transition-colors"
                            :class="open ? 'bg-[#eef5f0] border-emerald-200 text-emerald-800' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'">
                        <svg x-show="loading" class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg x-show="!loading" class="w-4 h-4 transition-transform duration-200"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                        <span x-text="open ? 'Masquer' : 'Variables'"></span>
                    </button>
                </div>
            </div>

            {{-- Panneau variables --}}
            <div x-show="open"
                 x-transition:enter="transition-all ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition-all ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="border-t border-gray-100"
                 style="display:none;">

                {{-- État vide --}}
                <div x-show="loaded && vars.length === 0" class="flex flex-col items-center py-10 text-center px-5">
                    <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm text-gray-400 italic">Aucune variable saisie pour ce bulletin.</p>
                    @if($run->isEditable())
                    <a href="{{ route('rh.paie.show', $run) }}"
                       class="mt-2 text-xs text-emerald-700 hover:text-emerald-900 font-medium">
                        + Ajouter des variables sur le bulletin
                    </a>
                    @endif
                </div>

                {{-- Spinner chargement --}}
                <div x-show="loading" class="flex justify-center py-8">
                    <svg class="w-6 h-6 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>

                {{-- Tableau variables --}}
                <div x-show="loaded && vars.length > 0" class="tbl-scroll">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th class="text-left">Employé</th>
                                <th class="text-left">Type</th>
                                <th class="text-left">Libellé</th>
                                <th class="text-center">Qté</th>
                                <th class="text-right">Montant (F)</th>
                                <th class="text-center">Imposable</th>
                                <th class="text-center">CNSS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="v in vars" :key="v.id">
                                <tr>
                                    <td class="font-medium text-gray-800" x-text="v.employee?.full_name ?? '—'"></td>
                                    <td>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700"
                                              x-text="v.type ?? '—'"></span>
                                    </td>
                                    <td class="text-gray-700" x-text="v.label || v.type || '—'"></td>
                                    <td class="text-center text-gray-600">
                                        <span x-text="v.qty ? v.qty + ' ' + (v.unit ?? '') : '—'"></span>
                                    </td>
                                    <td class="text-right font-mono font-semibold tabular-nums"
                                        x-text="Number(v.amount).toLocaleString('fr-FR') + ' F'"></td>
                                    <td class="text-center">
                                        <span x-show="v.is_taxable" class="text-xs text-emerald-600 font-bold">✓</span>
                                        <span x-show="!v.is_taxable" class="text-xs text-gray-300">—</span>
                                    </td>
                                    <td class="text-center">
                                        <span x-show="v.is_social_charged" class="text-xs text-emerald-600 font-bold">✓</span>
                                        <span x-show="!v.is_social_charged" class="text-xs text-gray-300">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold bg-gray-50">
                                <td colspan="4" class="text-sm text-gray-700">Total variables</td>
                                <td class="text-right font-mono tabular-nums"
                                    x-text="vars.reduce((s,v) => s + Number(v.amount), 0).toLocaleString('fr-FR') + ' F'"></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($runs->hasPages())
    <div>{{ $runs->links() }}</div>
    @endif
    @endif

</div>
@endsection
