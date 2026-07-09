@extends('layouts.erp')
@section('title', 'Écriture ' . $entry->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.journaux.index') }}" class="hover:text-gray-700">Journal comptable</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $entry->number }}</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $entry->number }}</h1>
                @php $color = $entry->statusColor(); @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                    {{ $entry->statusLabel() }}
                </span>
                @if(! $entry->isBalanced())
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">
                    Déséquilibré
                </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $entry->journalType?->code }} — {{ $entry->entry_date?->format('d/m/Y') }}
                @if($entry->reference) · Réf. : {{ $entry->reference }} @endif
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($entry->status === 'brouillon')
            @can('accounting.write')
            <a href="{{ route('comptabilite.journaux.edit', $entry) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Modifier
            </a>
            @endcan
            @can('accounting.validate')
            <form action="{{ route('comptabilite.journaux.validate', $entry) }}" method="POST"
                  onsubmit="return confirm('Valider cette écriture ? Les soldes comptables seront mis à jour.')">
                @csrf
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Valider
                </button>
            </form>
            @endcan
            <form action="{{ route('comptabilite.journaux.destroy', $entry) }}" method="POST"
                  onsubmit="return confirm('Supprimer cette écriture ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="border border-gray-300 text-gray-600 hover:bg-red-50 hover:border-red-300 hover:text-red-600 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Supprimer
                </button>
            </form>
            @endif

            {{-- [COMPTA-FIX-03] Contre-passation pour les écritures validées non encore contre-passées --}}
            @if($entry->status === 'valide' && !$entry->reversed_by_entry_id && !$entry->reverses_entry_id)
            @can('accounting.validate')
            <form action="{{ route('comptabilite.journaux.reverse', $entry) }}" method="POST"
                  x-data="{ open: false, reason: '' }">
                @csrf
                <button type="button" @click="open = true"
                        class="border border-amber-300 text-amber-700 hover:bg-amber-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Contre-passer
                </button>
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                    <div class="relative bg-white rounded-[4px] shadow-xl w-full max-w-md p-6 z-10">
                        <h3 class="text-base font-semibold text-gray-900">Contre-passer l'écriture {{ $entry->number }}</h3>
                        <p class="text-sm text-gray-500 mt-1">Une écriture miroir sera créée. Cette action est tracée et irréversible.</p>
                        <label class="block mt-4 text-xs font-medium text-gray-700">Motif (obligatoire, ≥ 5 caractères)</label>
                        <textarea name="reason" x-model="reason" rows="3" required minlength="5"
                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm mt-1 focus:ring-1 focus:ring-amber-500"
                                  placeholder="Ex. : erreur de saisie sur le compte 411"></textarea>
                        <div class="flex justify-end gap-2 mt-4">
                            <button type="button" @click="open = false" class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
                            <button type="submit" :disabled="reason.length < 5"
                                    :class="reason.length >= 5 ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                    class="text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                                Confirmer la contre-passation
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            @endcan
            @endif

            <a href="{{ route('comptabilite.journaux.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                ← Retour
            </a>
        </div>
    </div>

    {{-- Description --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="font-medium text-gray-900">{{ $entry->description }}</p>
    </div>

    {{-- Lines --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Lignes d'imputation</h2>
        </div>
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Compte</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                    <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit</th>
                    <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($entry->lines as $line)
                <tr>
                    <td class="px-3 py-1.5">
                        <span class="font-mono font-semibold text-violet-700">{{ $line->account?->code }}</span>
                        <span class="text-gray-600 ml-2 text-xs">{{ $line->account?->name }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $line->label ?: '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $line->debit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                        {{ $line->debit > 0 ? number_format($line->debit, 0, ',', ' ') : '—' }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $line->credit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                        {{ $line->credit > 0 ? number_format($line->credit, 0, ',', ' ') : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-bold">
                <tr>
                    <td class="px-3 py-1.5 text-right text-gray-500 text-xs uppercase" colspan="2">Totaux</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-900">
                        {{ number_format($entry->total_debit, 0, ',', ' ') }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $entry->isBalanced() ? 'text-green-700' : 'text-red-700' }}">
                        {{ number_format($entry->total_credit, 0, ',', ' ') }}
                        @if(! $entry->isBalanced())
                        <span class="text-xs font-normal text-red-500 ml-1">
                            (diff. {{ number_format(abs($entry->total_debit - $entry->total_credit), 0, ',', ' ') }})
                        </span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Metadata --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Journal</p>
                <p class="font-medium text-gray-900">{{ $entry->journalType?->name }}</p>
            </div>
            <div>
                <p class="text-gray-500">Date</p>
                <p class="font-medium text-gray-900">{{ $entry->entry_date?->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-gray-500">Créé par</p>
                <p class="font-medium text-gray-900">{{ $entry->createdBy?->name ?? '—' }}</p>
            </div>
            @if($entry->validatedBy)
            <div>
                <p class="text-gray-500">Validé par</p>
                <p class="font-medium text-gray-900">{{ $entry->validatedBy->name }} le {{ $entry->validated_at?->format('d/m/Y') }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- [COMPTA-FIX-03] Bandeau de traçabilité contre-passation --}}
    @if($entry->reversed_by_entry_id || $entry->reverses_entry_id)
        @php $rev = $entry->reversedBy; $orig = $entry->reverses; @endphp
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4 text-sm">
            @if($rev)
                <p class="font-medium text-amber-800">
                    ⓘ Cette écriture a été contre-passée le {{ $rev->entry_date?->format('d/m/Y') }} par
                    <a href="{{ route('comptabilite.journaux.show', $rev) }}" class="underline font-mono font-semibold">{{ $rev->number }}</a>.
                    Ses effets comptables sont neutralisés. Une nouvelle contre-passation n'est pas autorisée.
                </p>
            @elseif($orig)
                <p class="font-medium text-amber-800">
                    ⓘ Cette écriture est la contre-passation de
                    <a href="{{ route('comptabilite.journaux.show', $orig) }}" class="underline font-mono font-semibold">{{ $orig->number }}</a>
                    (datée du {{ $orig->entry_date?->format('d/m/Y') }}).
                </p>
            @endif
        </div>
    @endif

    {{-- [F] Pièces justificatives comptables --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="mb-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            <div>
                <h2 class="text-base font-semibold text-gray-900">Pièces justificatives</h2>
                <p class="text-xs text-gray-500 mt-0.5">Joignez les documents source (factures fournisseur scannées, contrats, bordereaux…) à cette écriture comptable.</p>
            </div>
        </div>
        <x-attachments.manager model="JournalEntry" :id="$entry->id" />
    </div>


    <x-audit.timeline :model="\App\Models\JournalEntry::class" :id="$entry->id" />

</div>
@endsection
