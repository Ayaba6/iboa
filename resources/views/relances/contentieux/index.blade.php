@extends('layouts.erp')
@section('title', 'Contentieux')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('relances.index') }}" class="hover:text-gray-700">Relances</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Contentieux</span>
@endsection

@section('content')
@php
    $stageLabels = ['mise_en_demeure'=>'Mise en demeure','huissier'=>'Huissier','avocat'=>'Avocat','tribunal'=>'Tribunal','abandon'=>'Abandon'];
    $statusLabels = ['ouvert'=>'Ouvert','en_cours'=>'En cours','suspendu'=>'Suspendu','recouvre'=>'Recouvré','irrecouvrable'=>'Irrécouvrable'];
@endphp
<div class="space-y-3" x-data="{
        createOpen: false,
        editOpen: false, editId: null, editNumber: '', editStage: '', editStatus: '', editNotes: '',
        openEdit(id, num, stage, status, notes) { this.editId=id; this.editNumber=num; this.editStage=stage; this.editStatus=status; this.editNotes=notes||''; this.editOpen=true; }
     }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Contentieux & recouvrement</h1>
            <p class="text-sm text-gray-500 mt-0.5">Dossiers litigieux escaladés après échec des relances</p>
        </div>
        @can('clients.create')
        <button type="button" @click="createOpen = true"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Ouvrir un dossier
        </button>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Dossiers ouverts</p>
            <p class="text-[16px] font-bold text-amber-800 mt-1">{{ $stats['ouverts'] }}</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Montant en litige</p>
            <p class="text-[16px] font-bold text-red-800 tabular-nums mt-1">{{ number_format($stats['montant'], 0, ',', ' ') }} F</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Recouvrés</p>
            <p class="text-[16px] font-bold text-green-800 mt-1">{{ $stats['recouvres'] }}</p>
        </div>
        <div class="bg-gray-100 border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs font-medium text-gray-600 uppercase tracking-wider">Pertes (irrécouvrable)</p>
            <p class="text-[16px] font-bold text-gray-800 tabular-nums mt-1">{{ number_format($stats['irrecouvrable'], 0, ',', ' ') }} F</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-3">
        <select name="client_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm min-w-48">
            <option value="">Tous les clients</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(($filters['client_id'] ?? '') == $c->id)>{{ $c->trade_name ?? $c->name }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Tous les statuts</option>
            @foreach($statusLabels as $k => $v)
                <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['client_id','status']))
        <a href="{{ route('contentieux.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N°</th>
                        <th class="text-left">Client</th>
                        <th class="text-left">Facture</th>
                        <th class="text-right">Montant</th>
                        <th class="text-left">Stade</th>
                        <th class="text-left">Statut</th>
                        <th class="text-left">Ouvert le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                    <tr>
                        <td class="font-mono font-semibold text-emerald-700">{{ $case->number }}</td>
                        <td class="text-gray-800">{{ $case->client?->trade_name ?? $case->client?->name ?? '—' }}</td>
                        <td class="font-mono text-xs text-gray-500">{{ $case->invoice?->number ?? '—' }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums text-gray-900">{{ number_format($case->amount, 0, ',', ' ') }} F</td>
                        <td><span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-slate-100 text-slate-700">{{ $case->stageLabel() }}</span></td>
                        <td>
                            @php
                                $sc = match($case->status) {
                                    'ouvert','en_cours' => 'bg-amber-100 text-amber-700',
                                    'suspendu'          => 'bg-blue-100 text-blue-700',
                                    'recouvre'          => 'bg-green-100 text-green-700',
                                    'irrecouvrable'     => 'bg-gray-200 text-gray-600',
                                    default             => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $sc }}">{{ $case->statusLabel() }}</span>
                            @if($case->journal_entry_id)<span class="text-xs text-gray-400 block mt-0.5">écriture 6514/411</span>@endif
                        </td>
                        <td class="tabular-nums text-gray-600">{{ $case->opened_at?->format('d/m/Y') }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('clients.create')
                            <button type="button"
                                    @click="openEdit({{ $case->id }}, '{{ $case->number }}', '{{ $case->stage }}', '{{ $case->status }}', @js($case->notes))"
                                    class="text-emerald-700 hover:underline text-xs font-medium">Gérer</button>
                            @endcan
                            @can('clients.delete')
                            @unless($case->journal_entry_id)
                            <form method="POST" action="{{ route('contentieux.destroy', $case) }}" class="inline ml-2" data-confirm="Supprimer ce dossier ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button>
                            </form>
                            @endunless
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun dossier de contentieux.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cases->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $cases->links() }}</div>
        @endif
    </div>

    {{-- Modal création --}}
    @can('clients.create')
    <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="createOpen = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Ouvrir un dossier de contentieux</h3>
            <form method="POST" action="{{ route('contentieux.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                    <select name="client_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-300">
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" @selected(($filters['client_id'] ?? '') == $c->id)>{{ $c->trade_name ?? $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" min="1" step="1" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right font-mono focus:ring-1 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ouvert le <span class="text-red-500">*</span></label>
                        <input type="date" name="opened_at" value="{{ date('Y-m-d') }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-300">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stade</label>
                    <select name="stage" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-300">
                        @foreach($stageLabels as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" maxlength="2000"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-red-300"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="createOpen = false" class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Annuler</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Ouvrir</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal gestion --}}
    <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editOpen = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Gérer le dossier <span x-text="editNumber" class="font-mono text-emerald-700"></span></h3>
            <form method="POST" :action="'{{ url('gestion/contentieux') }}/' + editId" class="space-y-3">
                @csrf @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stade</label>
                    <select name="stage" x-model="editStage" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                        @foreach($stageLabels as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" x-model="editStatus" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                        @foreach($statusLabels as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                    <p class="text-xs text-amber-600 mt-1" x-show="editStatus === 'irrecouvrable'" x-cloak>
                        ⚠ Passage en perte : génère l'écriture comptable 6514 / 411 (irréversible).
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" x-model="editNotes" rows="2" maxlength="2000"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-emerald-300"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="editOpen = false" class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Fermer</button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

</div>
@endsection
