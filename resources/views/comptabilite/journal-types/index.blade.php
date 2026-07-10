@extends('layouts.erp')
@section('title', 'Codes journaux')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Codes journaux</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Codes journaux</h1>
            <p class="text-sm text-gray-500">Référentiel des journaux comptables (Achats, Ventes, Banque, Caisse, OD, À-nouveau).</p>
        </div>
        @can('accounting.write')
        <a href="{{ route('comptabilite.journal-types.create') }}"
           class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau code journal
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
        <table class="tbl tbl-sticky w-full">
            <thead>
                <tr>
                    <th class="text-left">Code</th>
                    <th class="text-left">Libellé</th>
                    <th class="text-left">Type</th>
                    <th class="text-right">Écritures</th>
                    <th class="text-center">Actif</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($journalTypes as $jt)
                <tr>
                    <td class="font-mono font-bold text-violet-700">{{ $jt->code }}</td>
                    <td class="text-gray-900">{{ $jt->name }}</td>
                    <td class="text-xs text-gray-600">{{ $types[$jt->type] ?? $jt->type }}</td>
                    <td class="text-right tabular-nums">{{ $jt->entries_count }}</td>
                    <td class="text-center">
                        @if($jt->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">Inactif</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @can('accounting.write')
                        <a href="{{ route('comptabilite.journal-types.edit', $jt) }}" class="text-xs text-blue-600 hover:underline font-medium">Modifier</a>
                        <form action="{{ route('comptabilite.journal-types.destroy', $jt) }}" method="POST" class="inline ml-3"
                              data-confirm="Supprimer le code journal « {{ $jt->code }} » ? Cette action est définitive."
                              data-confirm-title="Supprimer le code journal">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline font-medium {{ $jt->entries_count > 0 ? 'opacity-40 cursor-not-allowed' : '' }}"
                                    @if($jt->entries_count > 0) title="Utilisé par {{ $jt->entries_count }} écriture(s) — désactivez-le plutôt" @endif>
                                Supprimer
                            </button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Aucun code journal défini.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Codes standards SYSCOA</p>
        <ul class="list-disc list-inside text-xs space-y-0.5 text-blue-700">
            <li><code class="font-mono font-semibold">AC</code> — Achats · <code class="font-mono font-semibold">VE</code> — Ventes · <code class="font-mono font-semibold">BQ</code> — Banque · <code class="font-mono font-semibold">CA</code> — Caisse</li>
            <li><code class="font-mono font-semibold">OD</code> — Opérations diverses · <code class="font-mono font-semibold">AN</code> — À-nouveau (ouverture d'exercice)</li>
            <li>Un journal utilisé par des écritures ne peut pas être supprimé — désactivez-le.</li>
        </ul>
    </div>
</div>
@endsection
