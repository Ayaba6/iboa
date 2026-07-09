@extends('layouts.erp')
@section('title', 'Modifier l\'unité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('units.index') }}" class="hover:text-gray-700">Unités de mesure</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modification</span>
@endsection

@section('content')
@php
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp
<div class="max-w-5xl space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Unité de mesure : Modification</h1>
            <p class="text-[11.5px] text-gray-500 font-mono">{{ $unit->code ? $unit->code.' — ' : '' }}{{ $unit->name }} ({{ $unit->abbreviation }})</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-unit" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('units.index') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</a>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form id="form-unit" method="POST" action="{{ route('units.update', $unit) }}" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @csrf @method('PUT')
        <div class="{{ $secH }}">Informations générales</div>
        <div class="p-4">
            @include('units._form', ['unit' => $unit])
        </div>
    </form>

    {{-- Conversions [Maquette] --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between {{ $secH }}">
            <span>Conversions</span>
            <a href="{{ route('units.create') }}" class="text-[12px] font-semibold text-emerald-700 hover:underline normal-case">+ Ajouter une conversion</a>
        </div>
        @if(($conversions ?? collect())->isNotEmpty())
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left">Unité cible</th>
                <th class="{{ $th }} text-left w-24">Symbole</th>
                <th class="{{ $th }} text-right w-44">Facteur de conversion</th>
                <th class="{{ $th }} text-left w-52">Équivalence</th>
                <th class="{{ $th }} text-center w-20">Statut</th>
                <th class="{{ $th }} text-right w-24">Actions</th>
            </tr></thead>
            <tbody>
                @foreach($conversions as $conv)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1.5 font-semibold text-gray-700">{{ $conv->name }}</td>
                    <td class="px-3 py-1.5 font-mono text-gray-600">{{ $conv->abbreviation }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ number_format((float) $conv->conversion_factor, 6, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 font-mono text-[11.5px] text-gray-500">1 {{ $unit->abbreviation }} = {{ $conv->conversion_factor > 0 ? rtrim(rtrim(number_format(1 / (float) $conv->conversion_factor, 6, ',', ' '), '0'), ',') : '—' }} {{ $conv->abbreviation }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $conv->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $conv->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    <td class="px-3 py-1.5 text-right"><a href="{{ route('units.edit', $conv) }}" class="text-[12px] font-semibold text-emerald-700 hover:underline">Modifier</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-[12px] text-gray-400 px-3 py-1.5">Aucune unité rattachée — créez une unité avec « {{ $unit->name }} » comme unité parente pour définir une conversion.</p>
        @endif
    </div>

    {{-- Informations système [Maquette] --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Informations système</div>
        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Créé par</p>
                <p class="text-[13px] text-gray-700">—</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Date de création</p>
                <p class="text-[13px] text-gray-700 font-mono tabular-nums">{{ $unit->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Dernière modification</p>
                <p class="text-[13px] text-gray-700 font-mono tabular-nums">{{ $unit->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Commentaires internes</p>
                <textarea name="internal_notes" form="form-unit" rows="2" maxlength="1000" placeholder="Ajouter un commentaire interne…"
                          class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[12.5px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">{{ old('internal_notes', $unit->internal_notes) }}</textarea>
            </div>
        </div>
    </div>

</div>
@endsection
