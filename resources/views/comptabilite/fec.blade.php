@extends('layouts.erp')
@section('title', 'Export FEC')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Export FEC</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-3">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Export FEC — Fichier des Écritures Comptables</h1>
        <p class="text-sm text-gray-500 mt-1">Format réglementaire d'audit fiscal (Art. A47-A1 LPF — applicable SYSCOA/OHADA).</p>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium">À propos du FEC</p>
        <ul class="list-disc list-inside mt-2 space-y-0.5 text-blue-700">
            <li>Texte tabulé (TSV), encodage ISO-8859-15 — ouvre directement dans Excel.</li>
            <li>Toutes les écritures <strong>validées</strong> de l'exercice (les brouillons sont exclus).</li>
            <li>18 colonnes normées : Journal, Date, Compte, Pièce, Débit, Crédit, Lettrage, etc.</li>
            <li>Nom du fichier : <code>&lt;IFU&gt;FEC&lt;AAAAMMJJ&gt;.txt</code>.</li>
        </ul>
    </div>

    <form action="{{ route('comptabilite.fec.export') }}" method="GET"
          class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Exercice fiscal <span class="text-red-500">*</span></label>
            <select name="fiscal_year_id" required
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">— Sélectionner —</option>
                @foreach($fiscalYears as $fy)
                <option value="{{ $fy->id }}" {{ $fy->is_current ? 'selected' : '' }}>
                    {{ $fy->label }} ({{ $fy->starts_at->format('d/m/Y') }} → {{ $fy->ends_at->format('d/m/Y') }})
                    @if($fy->status !== 'ouvert') — {{ ucfirst($fy->status) }} @endif
                </option>
                @endforeach
            </select>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('comptabilite.dashboard') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Télécharger le FEC
            </button>
        </div>
    </form>
</div>
@endsection
