@extends('layouts.erp')
@section('title', 'CRM — Pipeline')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Pipeline</span>
@endsection

@section('content')
@php
    $totalOpps = collect($kanban)->sum(fn ($c) => $c->count());
@endphp
<div class="space-y-3">

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Pipeline commercial</h1>
            <p class="text-xs text-gray-400 mt-0.5">Opportunités par étape — glisser-déposer pour changer d'étape</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('crm.opportunities.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                + Nouvelle opportunité
            </a>
            <a href="{{ route('crm.dashboard') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                ✕ Fermer
            </a>
        </div>
    </div>

    {{-- ══ 1. Critères de sélection ══════════════════════════════════════════ --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">1. Critères de sélection</p>
            @if(array_filter($filters ?? []))
            <a href="{{ route('crm.opportunities.index') }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Réinitialiser</a>
            @endif
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Société</label>
                <input type="text" value="{{ currentCompany()?->name }}" readonly
                       class="w-full h-8 px-2 py-0 border border-gray-300 rounded-[4px] text-sm bg-gray-50 text-gray-600">
            </div>
            <div>
                <label for="f-search" class="block text-xs font-medium text-gray-600 mb-1">Recherche</label>
                <input id="f-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Titre, produit/service…"
                       class="w-full h-8 px-2 py-0 border border-gray-300 rounded-[4px] text-sm focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label for="f-user" class="block text-xs font-medium text-gray-600 mb-1">Responsable</label>
                <select id="f-user" name="user_id"
                        class="w-full h-8 py-0 pl-2 border border-gray-300 rounded-[4px] text-sm focus:ring-1 focus:ring-emerald-500">
                    <option value="">— Tous —</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                    Rechercher
                </button>
            </div>
        </div>
    </form>

    {{-- ══ 2. Pipeline par étape (kanban) ════════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">2. Pipeline par étape</p>
            <p class="text-[11px] text-emerald-600">{{ $totalOpps }} opportunité(s)</p>
        </div>
        <div class="flex gap-3 overflow-x-auto p-3" style="min-height:440px;">
            @foreach(\App\Models\CrmOpportunity::STAGES as $stage => $cfg)
            @php
                $opps = $kanban[$stage];
                $dot  = $stage === 'gagne' ? 'bg-emerald-600' : ($stage === 'perdu' ? 'bg-red-500' : 'bg-' . $cfg['color'] . '-500');
            @endphp
            <div id="stage-{{ $stage }}"
                 class="flex-shrink-0 w-64 flex flex-col"
                 x-data="{ dragOver: false }"
                 @dragover.prevent="dragOver = true"
                 @dragleave="dragOver = false"
                 @drop.prevent="dragOver = false; handleDrop($event, '{{ $stage }}')">

                {{-- Entête colonne (neutre X3 + pastille couleur) --}}
                <div class="flex items-center justify-between px-3 py-2 rounded-[4px] mb-2 bg-[#eef5f0] border border-emerald-100"
                     :class="{ 'ring-2 ring-emerald-400 ring-offset-1': dragOver }">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full {{ $dot }} flex-shrink-0"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wide {{ $stage === 'perdu' ? 'text-red-700' : 'text-emerald-900' }} truncate">{{ $cfg['label'] }}</span>
                        <span class="text-[10.5px] font-bold text-emerald-700 bg-white border border-emerald-100 rounded-[3px] px-1.5" data-stage-count>{{ $opps->count() }}</span>
                    </div>
                    <span class="text-[11px] font-mono tabular-nums {{ $stage === 'perdu' ? 'text-red-600' : 'text-blue-700' }} font-semibold" data-stage-total>
                        {{ number_format($opps->sum('amount'), 0, ',', ' ') }} F
                    </span>
                </div>

                {{-- Cartes --}}
                <div class="flex-1 space-y-2" data-drop-zone>
                    @foreach($opps as $opp)
                    <div class="bg-white rounded-[4px] border border-gray-300 p-3 shadow-sm hover:shadow-md transition-shadow cursor-grab active:cursor-grabbing text-[12.5px]"
                         draggable="true"
                         data-amount="{{ (float) $opp->amount }}"
                         @dragstart="$event.dataTransfer.setData('oppId', {{ $opp->id }}); $event.dataTransfer.setData('fromStage', '{{ $stage }}')"
                         id="opp-{{ $opp->id }}">
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <a href="{{ route('crm.opportunities.show', $opp) }}"
                               class="font-semibold text-gray-900 hover:text-emerald-700 leading-tight flex-1 truncate"
                               title="{{ $opp->title }}">{{ $opp->title }}</a>
                            <a href="{{ route('crm.opportunities.edit', $opp) }}" aria-label="Modifier l'opportunité"
                               class="text-gray-300 hover:text-gray-500 flex-shrink-0 mt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </a>
                        </div>

                        @if($opp->contact)
                        <p class="text-xs text-gray-500 truncate mb-1.5" title="{{ $opp->contact->name }}{{ $opp->contact->company_name ? ' · ' . $opp->contact->company_name : '' }}">
                            {{ $opp->contact->name }}@if($opp->contact->company_name) · {{ $opp->contact->company_name }}@endif
                        </p>
                        @endif

                        <div class="flex items-center justify-between">
                            <span class="font-mono tabular-nums font-bold text-blue-700">{{ number_format($opp->amount, 0, ',', ' ') }} F</span>
                            <span class="text-xs text-gray-400 font-mono tabular-nums">{{ $opp->probability }} %</span>
                        </div>

                        @if($opp->expected_close)
                        @php $days = $opp->daysToClose(); @endphp
                        <div class="mt-1.5 text-xs {{ $days < 0 ? 'text-red-600 font-medium' : ($days <= 7 ? 'text-amber-600' : 'text-gray-400') }}">
                            Échéance {{ $opp->expected_close->format('d/m/Y') }}
                            @if($days < 0) ({{ abs($days) }} j de retard)
                            @elseif($days === 0) (aujourd'hui)
                            @elseif($days <= 7) (dans {{ $days }} j)
                            @endif
                        </div>
                        @endif

                        @if($opp->user)
                        <div class="mt-1 text-[11px] text-gray-400 truncate">{{ $opp->user->name }}</div>
                        @endif
                    </div>
                    @endforeach

                    {{-- Placeholder drop --}}
                    <div class="h-8 rounded-[4px] border-2 border-dashed border-emerald-200 opacity-0 transition-opacity"
                         :class="{ 'opacity-100': dragOver }"></div>
                </div>

                {{-- Ajouter dans ce stage --}}
                <a href="{{ route('crm.opportunities.create', ['stage' => $stage]) }}"
                   class="mt-2 flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-400 hover:text-emerald-700 hover:bg-[#eef5f0]/60 rounded-[4px] transition-colors">
                    + Ajouter
                </a>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══ Synthèse (pattern X3 : barre basse) ═══════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 lg:grid-cols-5 divide-x divide-gray-200">
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Opportunités</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-gray-900 mt-0.5">{{ $totalOpps }}</p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Pipeline actif</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ number_format($totalPipeline, 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Pondéré</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ number_format($totalWeighted, 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Gagné</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-emerald-700 mt-0.5">{{ number_format($totalWon, 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Perdu</p>
            <p class="text-[15px] font-bold font-mono tabular-nums {{ $totalLost > 0 ? 'text-red-600' : 'text-gray-800' }} mt-0.5">{{ number_format($totalLost, 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">CRM — Pipeline</strong></span>
            <span>Filtre : <strong class="text-white">{{ array_filter($filters ?? []) ? 'personnalisé' : 'aucun' }}</strong></span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Route template générée côté serveur (évite tout hardcoding d'URL)
const moveStageUrl = '{{ route("crm.opportunities.move-stage", ["opportunity" => "__ID__"]) }}';

async function handleDrop(event, toStage) {
    const oppId    = event.dataTransfer.getData('oppId');
    const fromStage = event.dataTransfer.getData('fromStage');
    if (!oppId || toStage === fromStage) return;

    try {
        const url  = moveStageUrl.replace('__ID__', oppId);
        const resp = await fetch(url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ stage: toStage }),
        });
        if (resp.ok) {
            // Déplacer la carte visuellement
            const card      = document.getElementById(`opp-${oppId}`);
            const targetCol = document.getElementById(`stage-${toStage}`);
            const dropZone  = targetCol?.querySelector('[data-drop-zone]');
            if (card && dropZone) {
                dropZone.insertBefore(card, dropZone.lastElementChild);
            }
            refreshColumnTotals();
            window.toast?.('Opportunité déplacée', 'success');
        } else {
            const err = await resp.json().catch(() => ({}));
            window.toast?.(err.message ?? 'Erreur lors du déplacement', 'error');
            window.location.reload();
        }
    } catch (e) {
        window.toast?.('Erreur réseau', 'error');
        window.location.reload();
    }
}

// Recalcule compteur + montant de chaque colonne depuis les cartes présentes
// (sinon les en-têtes restent figés sur les valeurs du chargement après un drop).
function refreshColumnTotals() {
    document.querySelectorAll('[id^="stage-"]').forEach((col) => {
        const cards = col.querySelectorAll('[data-drop-zone] [data-amount]');
        let total = 0;
        cards.forEach((c) => { total += parseFloat(c.dataset.amount) || 0; });
        const countEl = col.querySelector('[data-stage-count]');
        const totalEl = col.querySelector('[data-stage-total]');
        if (countEl) countEl.textContent = cards.length;
        if (totalEl) totalEl.textContent = new Intl.NumberFormat('fr-FR').format(Math.round(total)) + ' F';
    });
}
</script>
@endpush
