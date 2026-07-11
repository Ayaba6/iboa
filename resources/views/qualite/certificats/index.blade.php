@extends('layouts.erp')
@section('title', 'Certificats Qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Certificats Qualité</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Certificats Qualité</h1>
            <p class="text-[12px] text-gray-500">§8 &amp; §10 CDC — traçabilité et conformité matière</p>
        </div>
        @can('quality.manage')
        <a href="{{ route('qualite.certificats.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau certificat
        </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="N°, fournisseur, lot…" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Type</label>
                <div class="relative"><select name="type" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($types as $val => $label)
                    <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Résultat</label>
                <div class="relative"><select name="resultat" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($resultats as $val => $r)
                    <option value="{{ $val }}" @selected(request('resultat') === $val)>{{ $r['label'] }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                <a href="{{ route('qualite.certificats.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">N° Certificat</th>
                        <th class="{{ $th }} text-left">Type</th>
                        <th class="{{ $th }} text-left hidden lg:table-cell">Lot</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Fournisseur</th>
                        <th class="{{ $th }} text-left">Date</th>
                        <th class="{{ $th }} text-center">Résultat</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Validé</th>
                        <th class="{{ $th }} text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($certificates as $cert)
                    @php
                        $rc = $cert->resultat === 'conforme' ? 'bg-emerald-100 text-emerald-700' :
                             ($cert->resultat === 'non_conforme' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                    @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $cert->number }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $cert->typeLabel() }}</td>
                        <td class="px-3 py-1.5 font-mono text-[12px] text-gray-600 hidden lg:table-cell whitespace-nowrap">{{ $cert->lot_number ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 max-w-[180px] truncate hidden md:table-cell">{{ $cert->fournisseur ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $cert->date_certificat?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium {{ $rc }}">{{ $cert->resultatLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 hidden md:table-cell whitespace-nowrap">
                            @if($cert->validated_at)
                                <span class="inline-flex items-center gap-1 text-[12px] text-emerald-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    {{ $cert->validated_at->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-[12px] text-gray-400">En attente</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            <a href="{{ route('qualite.certificats.show', $cert) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Voir</a>
                            <a href="{{ route('qualite.certificats.pdf', $cert) }}" target="_blank" class="text-gray-500 hover:text-red-600 text-[12px] ml-2">PDF</a>
                            @can('quality.manage')
                            <a href="{{ route('qualite.certificats.edit', $cert) }}" class="text-gray-500 hover:text-emerald-700 text-[12px] ml-2">Modifier</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun certificat trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $certificates->total() }} certificat(s)</span>
            @if($certificates->hasPages())<div>{{ $certificates->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Certificats qualité</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
