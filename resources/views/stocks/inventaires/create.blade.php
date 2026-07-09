@extends('layouts.erp')
@section('title', 'Nouvel inventaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.inventaires.index') }}" class="hover:text-gray-700">Inventaires</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvel inventaire</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    // py-0 : neutralise le py-2 du plugin @tailwindcss/forms sur <select> (texte tronqué en h-8 sinon)
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'text-[13px] font-bold text-emerald-700 mb-3';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel = 'bg-white border border-gray-200 rounded-[4px] p-4';
    $chk   = 'w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600 focus:ring-emerald-500';
@endphp

<div class="max-w-[1400px]" x-data="{ tab: 'general', saveAndNew: false, submitting: false }">

    <form method="POST" action="{{ route('stocks.inventaires.store') }}" enctype="multipart/form-data"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvel inventaire</h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" @click="saveAndNew = true; $nextTick(() => $refs.form.submit())" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Aperçu</button>
                <a href="{{ route('stocks.inventaires.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general' => 'Général', 'lignes' => 'Lignes', 'comptage' => 'Comptage', 'validation' => 'Validation', 'pieces' => 'Pièces jointes', 'complement' => 'Complément'] as $tk => $tl)
            <button type="button" @click="tab = '{{ $tk }}'; $refs['sec_{{ $tk }}']?.scrollIntoView({behavior: 'smooth', block: 'start'})"
                    class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-[4px] text-[14px]">
            <p class="font-semibold mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- ═══ Rangée 1 : 1. Informations générales | 2. Paramètres de comptage ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            {{-- 1. Informations générales --}}
            <section x-ref="sec_general" class="{{ $panel }} xl:col-span-7">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">N° inventaire <span class="text-red-500">*</span></label>
                        <input type="text" value="INV-Auto" class="{{ $inpRo }} font-mono" readonly>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                        <input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}">
                        <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Dépôt <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="warehouse_id" required class="{{ $lk }} @error('warehouse_id') border-red-500 @enderror">
                                <option value="">—</option>
                                @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}{{ $wh->code ? ' (' . $wh->code . ')' : '' }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        @error('warehouse_id')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Date d'inventaire <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ now()->format('d/m/Y') }}" class="{{ $inpRo }} tabular-nums" readonly>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Heure <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ now()->format('H:i') }}" class="{{ $inpRo }} tabular-nums" readonly>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Type d'inventaire <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="type" class="{{ $lk }}">
                                @foreach(['complet' => 'Inventaire complet', 'tournant' => 'Inventaire tournant', 'annuel' => 'Inventaire annuel'] as $tv => $tlabel)
                                <option value="{{ $tv }}" {{ old('type', 'complet') === $tv ? 'selected' : '' }}>{{ $tlabel }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Statut <span class="text-red-500">*</span></label>
                        <input type="text" value="Ouvert" class="{{ $inpRo }}" readonly>
                    </div>

                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Responsable <span class="text-red-500">*</span></label>
                        <input type="text" name="responsible" maxlength="100" value="{{ old('responsible', auth()->user()->name) }}" class="{{ $inp }}">
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Méthode de comptage <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="counting_method" class="{{ $lk }}">
                                @foreach(['par_article' => 'Par article', 'par_emplacement' => 'Par emplacement', 'par_lot' => 'Par lot'] as $mv => $ml)
                                <option value="{{ $mv }}" {{ old('counting_method', 'par_article') === $mv ? 'selected' : '' }}>{{ $ml }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Devise</label>
                        <input type="text" name="currency_code" maxlength="3" value="{{ old('currency_code', 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Commentaire</label>
                        <textarea name="comment" rows="2" maxlength="500" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Ex : Inventaire périodique mensuel.">{{ old('comment') }}</textarea>
                    </div>
                </div>
            </section>

            {{-- 2. Paramètres de comptage --}}
            <section x-ref="sec_comptage" class="{{ $panel }} xl:col-span-5">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Paramètres de comptage</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-5">
                        <label class="{{ $lbl }}">Comptage <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4 h-8">
                            <label class="flex items-center gap-1.5 text-[13px] text-gray-800 cursor-pointer">
                                <input type="radio" name="counting_type" value="complet" {{ old('counting_type', 'complet') === 'complet' ? 'checked' : '' }} class="w-3.5 h-3.5 text-emerald-600 focus:ring-emerald-500"> Complet
                            </label>
                            <label class="flex items-center gap-1.5 text-[13px] text-gray-800 cursor-pointer">
                                <input type="radio" name="counting_type" value="partiel" {{ old('counting_type') === 'partiel' ? 'checked' : '' }} class="w-3.5 h-3.5 text-emerald-600 focus:ring-emerald-500"> Partiel
                            </label>
                        </div>
                    </div>
                    <div class="col-span-7 flex flex-wrap items-center gap-x-4 gap-y-1 pt-5">
                        <label class="flex items-center gap-1.5 text-[12px] font-semibold text-gray-800 cursor-pointer">
                            <input type="checkbox" name="freeze_stock" value="1" {{ old('freeze_stock', '1') ? 'checked' : '' }} class="{{ $chk }}"> Geler stock
                        </label>
                        <label class="flex items-center gap-1.5 text-[12px] font-semibold text-gray-800 cursor-pointer">
                            <input type="checkbox" name="include_lots" value="1" {{ old('include_lots', '1') ? 'checked' : '' }} class="{{ $chk }}"> Inclure lots/séries
                        </label>
                        <label class="flex items-center gap-1.5 text-[12px] font-semibold text-gray-800 cursor-pointer">
                            <input type="checkbox" name="include_locations" value="1" {{ old('include_locations', '1') ? 'checked' : '' }} class="{{ $chk }}"> Inclure emplacements
                        </label>
                    </div>

                    <div class="col-span-6">
                        <label class="{{ $lbl }}">Sélection emplacements</label>
                        <div class="relative">
                            <select name="location_scope" class="{{ $lk }}">
                                @foreach(['Tous les emplacements', 'Emplacements sélectionnés'] as $ls)
                                <option value="{{ $ls }}" {{ old('location_scope', 'Tous les emplacements') === $ls ? 'selected' : '' }}>{{ $ls }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-6">
                        <label class="{{ $lbl }}">Sélection articles</label>
                        <div class="relative">
                            <select name="article_scope" class="{{ $lk }}">
                                @foreach(['Tous les articles', 'Par famille', 'Articles sélectionnés'] as $as_)
                                <option value="{{ $as_ }}" {{ old('article_scope', 'Tous les articles') === $as_ ? 'selected' : '' }}>{{ $as_ }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="col-span-6">
                        <label class="{{ $lbl }}">Valorisation <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="valuation_method" class="{{ $lk }}">
                                @foreach(['cout_standard' => 'Au coût standard', 'cmp' => 'Au CMP', 'fifo' => 'FIFO', 'derniere_entree' => 'Dernière entrée'] as $vv => $vl)
                                <option value="{{ $vv }}" {{ old('valuation_method', 'cout_standard') === $vv ? 'selected' : '' }}>{{ $vl }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-6">
                        <label class="{{ $lbl }}">Devise de valorisation</label>
                        <input type="text" name="valuation_currency" maxlength="3" value="{{ old('valuation_currency', 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══ 3. Lignes d'inventaire ═══ --}}
        <section x-ref="sec_lignes" class="bg-white border border-gray-200 rounded-[4px]">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }} mb-0"><span class="text-gray-400 font-normal">3.</span> Lignes d'inventaire</h2>
                <span class="text-[12px] text-gray-400">Générées automatiquement à l'enregistrement</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[14px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="px-3 py-1.5 text-left w-14">Ligne</th>
                            <th class="px-3 py-1.5 text-left">Article</th>
                            <th class="px-3 py-1.5 text-left">Désignation</th>
                            <th class="px-3 py-1.5 text-left">Lot</th>
                            <th class="px-3 py-1.5 text-left">Emplacement</th>
                            <th class="px-3 py-1.5 text-left">U.S.</th>
                            <th class="px-3 py-1.5 text-right">Stock théorique</th>
                            <th class="px-3 py-1.5 text-right">Quantité comptée</th>
                            <th class="px-3 py-1.5 text-right">Écart</th>
                            <th class="px-3 py-1.5 text-right">Valeur écart</th>
                            <th class="px-3 py-1.5 text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-gray-400 text-[13px]">
                                Les lignes seront <span class="font-semibold text-gray-500">générées automatiquement</span> à l'enregistrement, depuis le stock théorique du dépôt sélectionné.<br>
                                La saisie des quantités comptées et le calcul des écarts se font ensuite sur la fiche de la session.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 text-[13px] font-bold text-gray-700">
                            <td class="px-3 py-1.5" colspan="6">Total</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">0,000</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">0,000</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">0,000</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">0,00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        {{-- ═══ Rangée 3 : 4. Validation | 5. Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            {{-- 4. Validation --}}
            <section x-ref="sec_validation" class="{{ $panel }} xl:col-span-7">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">4.</span> Validation</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">Demandé par <span class="text-red-500">*</span></label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Validateur</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Date de validation</label><input type="text" value="jj/mm/aaaa" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Statut de validation</label><input type="text" value="En attente" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-12">
                        <label class="{{ $lbl }}">Observations</label>
                        <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Saisissez vos observations…">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </section>

            {{-- 5. Traçabilité + Pièces jointes --}}
            <section x-ref="sec_pieces" class="{{ $panel }} xl:col-span-5">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">5.</span> Traçabilité</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Dernier statut</label><input type="text" value="Ouvert" class="{{ $inpRo }}" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">N° version</label><input type="text" value="1" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-12" x-ref="sec_complement">
                        <label class="{{ $lbl }}">Pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-gray-400 rounded-[3px] px-2 py-1 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                        <p class="text-[12px] text-gray-500 mt-0.5">PDF, images, Office — 5 Mo max. par fichier.</p>
                        @error('documents.*')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection
