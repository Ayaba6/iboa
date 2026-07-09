@extends('layouts.erp')
@section('title', 'Rapprochement ' . $rapprochement->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.rapprochement.index') }}" class="hover:text-gray-700">Rapprochement</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $rapprochement->number }}</span>
@endsection

@section('content')
@php
    $totalLines   = $rapprochement->lines->count();
    $matchedLines = $rapprochement->lines->where('is_matched', true)->count();
    $pct          = $totalLines > 0 ? round($matchedLines / $totalLines * 100) : 0;
@endphp

<div x-data="matchingPanel()" class="space-y-3">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $rapprochement->number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $rapprochement->cashAccount?->name }} —
                {{ $rapprochement->period_start?->format('d/m/Y') }} au {{ $rapprochement->period_end?->format('d/m/Y') }}
                · Relevé du {{ $rapprochement->statement_date?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @php $statusColors = ['brouillon' => 'bg-gray-100 text-gray-700', 'valide' => 'bg-green-100 text-green-700']; @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$rapprochement->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $rapprochement->statusLabel() }}
            </span>

            @if($rapprochement->isEditable())
                {{-- Auto-match --}}
                @can('accounting.write')
                <form method="POST" action="{{ route('comptabilite.rapprochement.auto-match', $rapprochement) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Auto-match
                    </button>
                </form>
                @endcan

                {{-- Import CSV --}}
                @can('accounting.write')
                <button type="button" @click="showCsvModal = true"
                        class="inline-flex items-center gap-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Importer relevé
                </button>
                @endcan

                {{-- Validate --}}
                @can('accounting.validate')
                <form method="POST" action="{{ route('comptabilite.rapprochement.validate', $rapprochement) }}"
                      onsubmit="return confirm('Valider ce rapprochement ? Cette action est irréversible.')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-1.5 rounded-[4px] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Valider
                    </button>
                </form>
                @endcan
            @endif
        </div>
    </div>

    {{-- Session messages --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-[4px] px-3 py-1.5">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-[4px] px-3 py-1.5">
        {{ session('error') }}
    </div>
    @endif

    {{-- ── KPIs ─────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500">Solde ouverture</p>
            <p class="text-[16px] font-bold tabular-nums text-gray-800">{{ number_format($rapprochement->opening_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500">Solde clôture (relevé)</p>
            <p class="text-[16px] font-bold tabular-nums text-gray-800">{{ number_format($rapprochement->closing_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500">Solde comptable</p>
            <p class="text-[16px] font-bold tabular-nums text-gray-800">{{ number_format($rapprochement->book_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500">Écart</p>
            <p class="text-[16px] font-bold tabular-nums {{ $rapprochement->difference == 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $rapprochement->difference == 0 ? '✓ 0' : number_format($rapprochement->difference, 0, ',', ' ') }}
            </p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500">Progression</p>
            <p class="text-[16px] font-bold text-gray-800">{{ $matchedLines }}/{{ $totalLines }} <span class="text-sm font-normal text-gray-400">({{ $pct }}%)</span></p>
            <div class="mt-1.5 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full {{ $pct == 100 ? 'bg-green-500' : 'bg-violet-500' }}" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    </div>

    {{-- ── Modal CSV ────────────────────────────────────────────────────────── --}}
    @can('accounting.write')
    @if($rapprochement->isEditable())
    <div x-show="showCsvModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @keydown.escape.window="showCsvModal = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md p-6 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Importer un relevé (CSV / Excel)</h3>
                <button @click="showCsvModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-[4px] px-3 py-2 text-xs text-blue-800 space-y-1">
                <p class="font-semibold">Format attendu (séparateur ; ou ,) :</p>
                <p class="font-mono">date;libelle;reference;debit;credit</p>
                <p>Ex: 2026-05-01;Virement client;VIR-001;0;500000</p>
                <p class="text-blue-600">La 1re ligne (header) est détectée et ignorée automatiquement.</p>
                <p class="text-blue-600">Formats acceptés : .csv, .txt, .xlsx, .xls — colonnes dans le même ordre.</p>
            </div>
            <form method="POST" action="{{ route('comptabilite.rapprochement.import-csv', $rapprochement) }}"
                  enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fichier (CSV ou Excel)</label>
                    <input type="file" name="csv_file" accept=".csv,.txt,.xlsx,.xls" required
                           class="w-full text-sm border border-gray-300 rounded-[4px] px-3 py-2 focus:ring-violet-500 focus:border-violet-500">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showCsvModal = false"
                            class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Annuler</button>
                    <button type="submit"
                            class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Importer</button>
                </div>
            </form>
        </div>
    </div>
    @endif
    @endcan

    {{-- ── Panel rapprochement (relevé ↔ écritures) ─────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Gauche : lignes relevé --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">Lignes du relevé bancaire</h2>
                <span class="text-xs text-gray-400">{{ $matchedLines }}/{{ $totalLines }} rapprochées</span>
            </div>
            <div class="divide-y divide-gray-100 max-h-[520px] overflow-y-auto">
                @forelse($rapprochement->lines as $line)
                <div class="px-3 py-1.5 flex items-start justify-between gap-2 transition-colors"
                     :class="selectedBankLine == {{ $line->id }}
                         ? 'bg-violet-50 ring-1 ring-violet-300 ring-inset'
                         : '{{ $line->is_matched ? 'bg-green-50/30' : 'hover:bg-gray-50' }}'">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0 {{ $line->is_matched ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $line->label }}</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 pl-4">
                            {{ $line->value_date?->format('d/m/Y') }}
                            @if($line->reference)<span class="text-gray-500">· {{ $line->reference }}</span>@endif
                            @if($line->is_matched && $line->journalEntryLine)
                                <span class="text-green-600 font-medium">→ {{ $line->journalEntryLine->journalEntry?->number ?? '—' }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @if($line->debit > 0)
                        <p class="text-sm font-semibold tabular-nums text-red-600">−{{ number_format($line->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm font-semibold tabular-nums text-green-600">+{{ number_format($line->credit, 0, ',', ' ') }}</p>
                        @endif
                        @if($rapprochement->isEditable())
                        <div class="flex gap-1 mt-1 justify-end">
                            @if(!$line->is_matched)
                            <button type="button" @click="selectBankLine({{ $line->id }})"
                                    class="text-xs font-medium"
                                    :class="selectedBankLine == {{ $line->id }} ? 'text-violet-800 font-semibold' : 'text-violet-600 hover:text-violet-800'">
                                <span x-show="selectedBankLine != {{ $line->id }}">Associer →</span>
                                <span x-show="selectedBankLine == {{ $line->id }}">✓ Sélectionné</span>
                            </button>
                            @if($line->debit > 0)
                            <button type="button" @click="postFee({{ $line->id }}, @js($line->label))"
                                    class="text-xs text-amber-600 hover:text-amber-800 font-medium" title="Comptabiliser en frais bancaire (DR 6312 / CR 521)">Frais</button>
                            @endif
                            @else
                            <button type="button" @click="unmatch({{ $line->id }})"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium">Dissocier</button>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="px-4 py-12 text-center">
                    <p class="text-sm text-gray-400">Aucune ligne de relevé.</p>
                    @if($rapprochement->isEditable())
                    <p class="text-xs text-gray-400 mt-1">Importez un CSV ou ajoutez des lignes en créant un nouveau rapprochement.</p>
                    @endif
                </div>
                @endforelse
            </div>
            {{-- Totaux relevé --}}
            @if($rapprochement->lines->count() > 0)
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex justify-between text-xs font-semibold text-gray-600">
                <span>Total débits : <span class="text-red-600 tabular-nums">−{{ number_format($rapprochement->lines->sum('debit'), 0, ',', ' ') }}</span></span>
                <span>Total crédits : <span class="text-green-600 tabular-nums">+{{ number_format($rapprochement->lines->sum('credit'), 0, ',', ' ') }}</span></span>
            </div>
            @endif
        </div>

        {{-- Droite : écritures non rapprochées --}}
        @if($rapprochement->isEditable())
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Écritures comptables non rapprochées</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span x-show="selectedBankLine" class="text-violet-600 font-medium">✓ Ligne sélectionnée — cliquez sur une écriture pour l'associer</span>
                    <span x-show="!selectedBankLine">Sélectionnez d'abord une ligne du relevé (← gauche)</span>
                </p>
            </div>
            <div class="divide-y divide-gray-100 max-h-[520px] overflow-y-auto">
                @forelse($unmatchedJournalLines as $jl)
                <div class="px-3 py-1.5 flex items-start justify-between gap-2 transition-colors"
                     :class="selectedBankLine ? 'cursor-pointer hover:bg-violet-50' : 'opacity-60 cursor-not-allowed'"
                     @click="matchLine({{ $jl->id }})">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $jl->label }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $jl->journalEntry?->entry_date?->format('d/m/Y') }}
                            · {{ $jl->journalEntry?->number }}
                            · {{ $jl->account?->code }}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @if($jl->debit > 0)
                        <p class="text-sm font-semibold tabular-nums text-blue-700">D {{ number_format($jl->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm font-semibold tabular-nums text-purple-700">C {{ number_format($jl->credit, 0, ',', ' ') }}</p>
                        @endif
                    </div>
                </div>
                @empty
                <div class="px-4 py-12 text-center">
                    <p class="text-sm text-gray-400">Toutes les écritures de la période sont rapprochées ✓</p>
                </div>
                @endforelse
            </div>
        </div>
        @else
        {{-- Vue read-only : affiche les associations --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Associations rapprochées</h2>
                @if($rapprochement->validatedBy)
                <p class="text-xs text-gray-500 mt-0.5">
                    Validé par {{ $rapprochement->validatedBy->name }} le {{ $rapprochement->validated_at?->format('d/m/Y à H:i') }}
                </p>
                @endif
            </div>
            <div class="divide-y divide-gray-100 max-h-[520px] overflow-y-auto">
                @forelse($rapprochement->lines->where('is_matched', true) as $line)
                <div class="px-3 py-1.5 flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $line->label }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $line->value_date?->format('d/m/Y') }}
                            @if($line->journalEntryLine)
                                → <span class="text-green-600 font-medium">{{ $line->journalEntryLine->journalEntry?->number ?? '—' }}</span>
                                · {{ $line->journalEntryLine->label }}
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        @if($line->debit > 0)
                        <p class="text-sm tabular-nums text-red-600">−{{ number_format($line->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm tabular-nums text-green-600">+{{ number_format($line->credit, 0, ',', ' ') }}</p>
                        @endif
                    </div>
                </div>
                @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">Aucune association.</p>
                @endforelse
            </div>
        </div>
        @endif
    </div>

    {{-- Notes --}}
    @if($rapprochement->notes)
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-1.5 text-sm text-amber-800">
        <span class="font-semibold">Notes :</span> {{ $rapprochement->notes }}
    </div>
    @endif

</div>

@push('scripts')
<script>
function matchingPanel() {
    const matchUrlBase   = @json(url('comptabilite/rapprochement/lines'));
    const csrfToken      = document.querySelector('meta[name=csrf-token]')?.content ?? '';

    return {
        selectedBankLine: null,
        showCsvModal: false,

        selectBankLine(id) {
            this.selectedBankLine = this.selectedBankLine === id ? null : id;
        },

        async postFee(lineId, label) {
            const ok = await window.erpConfirm({
                message: 'Comptabiliser cette ligne en frais bancaire (DR 6312 / CR 521) et la rapprocher ?',
                title: 'Frais bancaire',
                confirmLabel: 'Comptabiliser',
                isDanger: false,
            });
            if (!ok) return;
            try {
                const resp = await fetch(`${matchUrlBase}/${lineId}/fee`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ label }),
                });
                const data = await resp.json();
                if (data.ok) {
                    window.toast(data.message, 'success');
                    window.location.reload();
                } else {
                    window.toast(data.message || 'Erreur.', 'error');
                }
            } catch (e) {
                window.toast('Erreur réseau. Réessayez.', 'error');
            }
        },

        async matchLine(journalLineId) {
            if (!this.selectedBankLine) {
                window.toast('Sélectionnez d\'abord une ligne du relevé bancaire (colonne gauche).', 'warning');
                return;
            }
            try {
                const resp = await fetch(`${matchUrlBase}/${this.selectedBankLine}/match`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ journal_entry_line_id: journalLineId }),
                });
                const data = await resp.json();
                if (data.ok) {
                    window.toast('Association enregistrée.', 'success');
                    window.location.reload();
                } else {
                    window.toast(data.message || 'Erreur lors de l\'association.', 'error');
                }
            } catch (e) {
                window.toast('Erreur réseau. Réessayez.', 'error');
            }
        },

        async unmatch(bankLineId) {
            const ok = await window.erpConfirm({
                message: 'Supprimer cette association ?',
                confirmLabel: 'Supprimer',
                isDanger: true,
            });
            if (!ok) return;
            try {
                const resp = await fetch(`${matchUrlBase}/${bankLineId}/unmatch`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                const data = await resp.json();
                if (data.ok) {
                    window.toast('Association supprimée.', 'success');
                    window.location.reload();
                } else {
                    window.toast(data.message || 'Erreur lors de la dissociation.', 'error');
                }
            } catch (e) {
                window.toast('Erreur réseau. Réessayez.', 'error');
            }
        },
    };
}
</script>
@endpush
@endsection
