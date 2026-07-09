@extends('layouts.erp')
@section('title', $certificate->exists ? 'Modifier certificat ' . $certificate->number : 'Nouveau certificat qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.certificats.index') }}" class="hover:text-gray-700">Certificats Qualité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $certificate->exists ? $certificate->number : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = $inp . ' text-right font-mono';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="max-w-5xl">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $certificate->exists ? route('qualite.certificats.update', $certificate) : route('qualite.certificats.store') }}">
        @csrf
        @if($certificate->exists) @method('PUT') @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <div>
                    <h2 class="text-[15px] font-bold text-gray-900">Certificat qualité : {{ $certificate->exists ? 'Modification ' . $certificate->number : 'Création complète' }}</h2>
                    <p class="text-[11.5px] text-gray-500">§8 &amp; §10 CDC — attestation de conformité matière</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('qualite.certificats.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <div class="p-4 space-y-4">
                {{-- Informations générales --}}
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Type <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="type" required class="{{ $lk }}">
                                @foreach($types as $val => $label)
                                <option value="{{ $val }}" @selected(old('type', $certificate->type) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Date du certificat <span class="text-red-600">*</span></label>
                            <input type="date" name="date_certificat" value="{{ old('date_certificat', $certificate->date_certificat?->format('Y-m-d')) }}" required class="{{ $inp }}">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Date réception</label>
                            <input type="date" name="date_reception" value="{{ old('date_reception', $certificate->date_reception?->format('Y-m-d')) }}" class="{{ $inp }}">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">N° Lot</label>
                            <input type="text" name="lot_number" value="{{ old('lot_number', $certificate->lot_number ?? $lotPrefill ?? '') }}" placeholder="LOT-2026-001" class="{{ $inp }} font-mono">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Fournisseur</label>
                            <input type="text" name="fournisseur" value="{{ old('fournisseur', $certificate->fournisseur) }}" class="{{ $inp }}">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Norme / Référence</label>
                            <input type="text" name="norme" value="{{ old('norme', $certificate->norme) }}" placeholder="NF EN 10147, ISO 9001…" class="{{ $inp }}">
                        </div>
                    </div>
                </section>

                {{-- Caractéristiques physiques --}}
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques physiques <span class="font-normal text-emerald-800/70">(§13.5 CDC — contrôle bobines)</span></div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3">
                        <div>
                            <label class="{{ $lbl }}">Poids réel (t)</label>
                            <input type="number" step="0.001" name="poids_reel" value="{{ old('poids_reel', $certificate->poids_reel) }}" class="{{ $inpR }}">
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Largeur (mm)</label>
                            <input type="number" step="0.01" name="largeur_mm" value="{{ old('largeur_mm', $certificate->largeur_mm) }}" class="{{ $inpR }}">
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Épaisseur (mm)</label>
                            <input type="number" step="0.001" name="epaisseur_mm" value="{{ old('epaisseur_mm', $certificate->epaisseur_mm) }}" class="{{ $inpR }}">
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Couleur</label>
                            <input type="text" name="couleur" value="{{ old('couleur', $certificate->couleur) }}" placeholder="Galvanisé, Vert, Rouge…" class="{{ $inp }}">
                        </div>
                    </div>
                </section>

                {{-- Résultat --}}
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Résultat du contrôle</div>
                    <div class="p-4 space-y-3">
                        <div class="flex gap-6">
                            @foreach($resultats as $val => $r)
                            @php
                                $rc = match($val) { 'conforme' => 'text-emerald-600 focus:ring-emerald-500', 'non_conforme' => 'text-red-600 focus:ring-red-500', default => 'text-amber-600 focus:ring-amber-500' };
                            @endphp
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="resultat" value="{{ $val }}"
                                       @checked(old('resultat', $certificate->resultat ?? 'conforme') === $val)
                                       class="border-[#c3d3c9] {{ $rc }}">
                                <span class="text-[13px] font-medium text-gray-700">{{ $r['label'] }}</span>
                            </label>
                            @endforeach
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Observations</label>
                            <textarea name="observations" rows="3"
                                      class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white resize-none focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">{{ old('observations', $certificate->observations) }}</textarea>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
