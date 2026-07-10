@extends('layouts.erp')
@section('title', $center->exists ? 'Modifier centre ' . $center->code : 'Nouveau centre analytique')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('analytique.centres-couts.index') }}" class="hover:text-gray-700">Centres de coûts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $center->exists ? $center->code : 'Nouveau centre' }}</span>
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
    $isEdit = $center->exists;
@endphp

<div class="max-w-[1400px]" x-data="{ saveAndNew: false, submitting: false }">

    <form method="POST" action="{{ $isEdit ? route('analytique.centres-couts.update', $center) : route('analytique.centres-couts.store') }}"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        @if($isEdit) @method('PUT') @endif
        @if(! $isEdit)<input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">@endif

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">
                {{ $isEdit ? 'Centre analytique' : 'Nouveau centre analytique' }}
                @if($isEdit)<span class="font-mono text-emerald-700 text-[18px] ml-1">{{ $center->code }}</span>@endif
            </h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                @if(! $isEdit)
                <button type="button" @click="saveAndNew = true; $nextTick(() => $refs.form.submit())" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                @endif
                <a href="{{ route('analytique.centres-couts.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        <x-validation-errors />

        {{-- ═══ Rangée : 1. Informations générales | 2. Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            <section class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Code <span class="text-red-500">*</span></label>
                        <input type="text" name="code" value="{{ old('code', $center->code) }}" required maxlength="20"
                               class="{{ $inp }} font-mono uppercase" placeholder="CC-PROD-001">
                    </div>
                    <div class="col-span-5">
                        <label class="{{ $lbl }}">Nom <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $center->name) }}" required maxlength="120"
                               class="{{ $inp }}" placeholder="Production Tôles Bac">
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Type <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="type" required class="{{ $lk }}">
                                @foreach(['cost' => 'Centre de coûts', 'profit' => 'Centre de profit', 'investment' => 'Centre d\'investissement'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('type', $center->type) === $v)>{{ $l }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Centre parent</label>
                        <div class="relative">
                            <select name="parent_id" class="{{ $lk }}">
                                <option value="">— Aucun (racine) —</option>
                                @foreach($parents as $p)
                                <option value="{{ $p->id }}" @selected(old('parent_id', $center->parent_id) == $p->id)>{{ $p->code }} — {{ $p->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Statut</label>
                        <input type="hidden" name="is_active" value="0">
                        <div class="relative">
                            <select name="is_active" class="{{ $lk }}">
                                <option value="1" @selected(old('is_active', $center->is_active ?? true))>Actif</option>
                                <option value="0" @selected(! old('is_active', $center->is_active ?? true))>Inactif</option>
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-5">
                        <label class="{{ $lbl }}">Société</label>
                        <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                    </div>

                    <div class="col-span-12">
                        <label class="{{ $lbl }}">Description</label>
                        <textarea name="description" rows="2" maxlength="500"
                                  class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none"
                                  placeholder="Axe analytique : périmètre, responsable, règles d'imputation…">{{ old('description', $center->description) }}</textarea>
                    </div>
                </div>
            </section>

            <section class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Traçabilité</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6"><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ $isEdit ? $center->created_at?->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Modifié le</label><input type="text" value="{{ $isEdit ? $center->updated_at?->format('d/m/Y H:i') : '—' }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    @if($isEdit)
                    <div class="col-span-6"><label class="{{ $lbl }}">Lignes imputées</label><input type="text" value="{{ number_format($center->analyticLines()->count(), 0, ',', ' ') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-6"><label class="{{ $lbl }}">Total ventilé</label><input type="text" value="{{ number_format(abs($center->analyticLines()->sum('amount')), 0, ',', ' ') }} F" class="{{ $inpRo }} tabular-nums" readonly></div>
                    @else
                    <div class="col-span-12"><p class="text-[12px] text-gray-500">Les lignes analytiques (coûts de revient OF, saisies manuelles) s'imputeront sur ce centre après création.</p></div>
                    @endif
                </div>
            </section>
        </div>

    </form>
</div>
@endsection
