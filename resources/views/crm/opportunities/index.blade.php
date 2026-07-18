@extends('layouts.erp')
@section('title', 'CRM — Pipeline')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Pipeline</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Pipeline commercial</h1>
            <p class="text-xs text-gray-400 mt-0.5">
                Pipeline actif : <strong class="text-gray-600">{{ number_format($totalPipeline, 0, ',', ' ') }} FCFA</strong>
                · Gagné : <strong class="text-emerald-700">{{ number_format($totalWon, 0, ',', ' ') }} FCFA</strong>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Rechercher..."
                       class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 w-48">
                <button type="submit" class="px-3 py-2 bg-white border border-gray-300 rounded-[4px] text-sm text-gray-600 hover:bg-gray-50">Filtrer</button>
                @if(array_filter($filters ?? []))
                <a href="{{ route('crm.opportunities.index') }}" class="px-3 py-2 border border-gray-300 text-gray-500 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
                @endif
            </form>
            <a href="{{ route('crm.opportunities.create') }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle
            </a>
        </div>
    </div>

    {{-- KANBAN --}}
    <div class="flex gap-4 overflow-x-auto pb-4" style="min-height:500px;">
        @foreach(\App\Models\CrmOpportunity::STAGES as $stage => $cfg)
        @php $opps = $kanban[$stage]; @endphp
        <div id="stage-{{ $stage }}"
             class="flex-shrink-0 w-72 flex flex-col"
             x-data="{ dragOver: false }"
             @dragover.prevent="dragOver = true"
             @dragleave="dragOver = false"
             @drop.prevent="dragOver = false; handleDrop($event, '{{ $stage }}')">

            {{-- Entête colonne --}}
            <div class="flex items-center justify-between px-3 py-2.5 rounded-[4px] mb-2
                        bg-{{ $cfg['color'] }}-50 border border-{{ $cfg['color'] }}-100"
                 :class="{ 'ring-2 ring-{{ $cfg['color'] }}-400 ring-offset-1': dragOver }">
                <div class="flex items-center gap-2">
                    <span class="text-lg">{{ $cfg['icon'] }}</span>
                    <span class="text-sm font-semibold text-{{ $cfg['color'] }}-700">{{ $cfg['label'] }}</span>
                    <span class="text-xs font-bold text-{{ $cfg['color'] }}-500 bg-{{ $cfg['color'] }}-100 rounded-full px-2 py-0.5" data-stage-count>{{ $opps->count() }}</span>
                </div>
                <span class="text-xs text-{{ $cfg['color'] }}-600 font-medium font-mono tabular-nums" data-stage-total>
                    {{ number_format($opps->sum('amount'), 0, ',', ' ') }} F
                </span>
            </div>

            {{-- Cartes --}}
            <div class="flex-1 space-y-2.5" data-drop-zone>
                @foreach($opps as $opp)
                <div class="bg-white rounded-[4px] border border-gray-300 p-4 shadow-sm hover:shadow-md transition-shadow cursor-grab active:cursor-grabbing"
                     draggable="true"
                     data-amount="{{ (float) $opp->amount }}"
                     @dragstart="$event.dataTransfer.setData('oppId', {{ $opp->id }}); $event.dataTransfer.setData('fromStage', '{{ $stage }}')"
                     id="opp-{{ $opp->id }}">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <a href="{{ route('crm.opportunities.show', $opp) }}"
                           class="text-sm font-semibold text-gray-900 hover:text-emerald-700 leading-tight flex-1">{{ $opp->title }}</a>
                        <a href="{{ route('crm.opportunities.edit', $opp) }}"
                           class="text-gray-300 hover:text-gray-500 flex-shrink-0 mt-0.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </a>
                    </div>

                    @if($opp->contact)
                    <p class="text-xs text-gray-500 truncate mb-2">👤 {{ $opp->contact->name }}
                        @if($opp->contact->company_name) · {{ $opp->contact->company_name }}@endif
                    </p>
                    @endif

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-gray-900">{{ number_format($opp->amount, 0, ',', ' ') }} F</span>
                        <span class="text-xs text-gray-400">{{ $opp->probability }}%</span>
                    </div>

                    @if($opp->expected_close)
                    @php $days = $opp->daysToClose(); @endphp
                    <div class="mt-2 flex items-center gap-1 text-xs {{ $days < 0 ? 'text-red-500' : ($days <= 7 ? 'text-amber-500' : 'text-gray-400') }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        {{ $opp->expected_close->format('d/m/Y') }}
                        @if($days < 0) ({{ abs($days) }}j dépassé)
                        @elseif($days === 0) (aujourd'hui)
                        @elseif($days <= 7) (dans {{ $days }}j)
                        @endif
                    </div>
                    @endif

                    @if($opp->user)
                    <div class="mt-2 text-xs text-gray-300">👤 {{ $opp->user->name }}</div>
                    @endif
                </div>
                @endforeach

                {{-- Placeholder drop --}}
                <div class="h-8 rounded-[4px] border-2 border-dashed border-gray-200 opacity-0 transition-opacity"
                     :class="{ 'opacity-100': dragOver }"></div>
            </div>

            {{-- Ajouter dans ce stage --}}
            <a href="{{ route('crm.opportunities.create', ['stage' => $stage]) }}"
               class="mt-2 flex items-center gap-1.5 px-3 py-2 text-xs text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-[4px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter
            </a>
        </div>
        @endforeach
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">CRM — Pipeline</strong></span>
            <span>Pipeline actif : <strong class="text-white">{{ number_format($totalPipeline, 0, ',', ' ') }} FCFA</strong></span>
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
