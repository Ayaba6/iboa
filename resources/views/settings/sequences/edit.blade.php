@extends('layouts.erp')
@section('title', 'Modifier la numérotation - ' . $label)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('settings.sequences.index') }}" class="hover:text-gray-700">Numérotation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $label }}</span>
@endsection

@section('content')
<div class="max-w-5xl space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Modifier : {{ $label }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Format & compteur — exercice {{ $sequence->fiscalYear?->label ?? $sequence->fiscalYear?->name ?? '—' }}
            </p>
        </div>
        <a href="{{ route('settings.sequences.index') }}"
           class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">
            ← Retour
        </a>
    </div>

    {{-- Statut --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Compteur actuel</div>
            <div class="font-mono font-bold text-2xl text-gray-900 tabular-nums">{{ number_format($sequence->last_number) }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">N° max émis (BDD)</div>
            <div class="font-mono font-bold text-2xl {{ $maxUsed > $sequence->last_number ? 'text-red-600' : 'text-gray-700' }} tabular-nums">
                {{ number_format($maxUsed) }}
            </div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Mode</div>
            <div class="mt-1">
                @if($sequence->numbering_mode === 'manual')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">Manuel</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Automatique</span>
                @endif
            </div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">État format</div>
            <div class="mt-1 text-sm">
                @if($sequence->is_locked)
                    <span class="text-red-600 font-medium">🔒 Verrouillé</span>
                @else
                    <span class="text-gray-600">🔓 Libre</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Erreurs --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-[4px] px-3 py-1.5 text-sm">
        <strong>Action refusée :</strong>
        <ul class="mt-1 list-disc list-inside">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════
         FORMULAIRE 1 : Format & compteur
    ═══════════════════════════════════════════════════════════════════════ --}}
    <form method="POST" action="{{ route('settings.sequences.update', $sequence) }}" data-turbo="false"
          class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
        @csrf
        @method('PUT')

        <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-2">Format & compteur</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Préfixe <span class="text-red-500">*</span></label>
                <input type="text" name="prefix" value="{{ old('prefix', $sequence->prefix) }}" required maxlength="20"
                       @if($sequence->is_locked) disabled @endif
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Suffixe (optionnel)</label>
                <input type="text" name="suffix" value="{{ old('suffix', $sequence->suffix) }}" maxlength="20"
                       @if($sequence->is_locked) disabled @endif
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nombre de chiffres</label>
                <select name="padding"
                        @if($sequence->is_locked) disabled @endif
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
                    @for($i = 1; $i <= 9; $i++)
                        <option value="{{ $i }}" @selected(old('padding', $sequence->padding) == $i)>
                            {{ $i }} — exemple : {{ str_pad('1', $i, '0', STR_PAD_LEFT) }}
                        </option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Inclure l'année</label>
                <select name="include_year"
                        @if($sequence->is_locked) disabled @endif
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
                    <option value="1" @selected(old('include_year', $sequence->include_year))>Oui</option>
                    <option value="0" @selected(!old('include_year', $sequence->include_year))>Non</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Inclure le mois</label>
                <select name="include_month"
                        @if($sequence->is_locked) disabled @endif
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
                    <option value="1" @selected(old('include_month', $sequence->include_month))>Oui</option>
                    <option value="0" @selected(!old('include_month', $sequence->include_month))>Non</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Format année</label>
                <select name="year_format"
                        @if($sequence->is_locked) disabled @endif
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
                    <option value="4" @selected(old('year_format', $sequence->year_format) === '4')>YYYY (4 chiffres)</option>
                    <option value="2" @selected(old('year_format', $sequence->year_format) === '2')>YY (2 chiffres)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Séparateur année</label>
                <input type="text" name="year_separator" value="{{ old('year_separator', $sequence->year_separator) }}" maxlength="5"
                       @if($sequence->is_locked) disabled @endif
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
                       placeholder="-">
            </div>

            {{-- Compteur (séparé visuellement) --}}
            <div class="sm:col-span-2 lg:col-span-3 pt-3 border-t border-gray-100">
                <label class="block text-xs font-medium text-orange-700 mb-1">
                    Compteur actuel <span class="text-gray-400 font-normal">(modification manuelle)</span>
                </label>
                <input type="number" name="last_number" value="{{ old('last_number', $sequence->last_number) }}"
                       min="0" max="999999999" step="1"
                       class="w-full max-w-xs border border-orange-300 bg-orange-50 rounded-[4px] px-3 py-2 text-base font-mono tabular-nums focus:ring-1 focus:ring-orange-500">
                <p class="text-xs text-orange-600 mt-1">
                    Le prochain n° généré sera : <strong>{{ $sequence->last_number + 1 }}</strong> (compteur + 1).
                    ⚠ Le système refuse de descendre sous le n° max déjà émis ({{ $maxUsed }}) sauf si "Forcer" est coché.
                </p>
            </div>

        </div>

        {{-- Motif + force --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end pt-3 border-t border-gray-100">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Motif (audit)</label>
                <input type="text" name="reason" maxlength="255" value="{{ old('reason') }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm"
                       placeholder="ex: correction suite migration, début d'exercice...">
            </div>
            <div>
                <label class="flex items-center gap-2 text-xs font-medium text-red-700 cursor-pointer">
                    <input type="checkbox" name="force" value="1" class="rounded border-red-300 text-red-600 focus:ring-red-500">
                    <span>Forcer (régression compteur)</span>
                </label>
                <p class="text-[10px] text-red-600 mt-1">⚠ Risque de doublons</p>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-3">
            <a href="{{ route('settings.sequences.index') }}"
               class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">
                ✓ Enregistrer
            </button>
        </div>
    </form>

    {{-- ═══════════════════════════════════════════════════════════════════════
         OPÉRATIONS AVANCÉES
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3">
        <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-2">Opérations avancées</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Mode --}}
            <form method="POST" action="{{ route('settings.sequences.set-mode', $sequence) }}" data-turbo="false"
                  class="border border-gray-200 rounded-[4px] p-4 space-y-3">
                @csrf
                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-1">Mode de numérotation</div>
                    <div class="text-xs text-gray-500">Auto = séquentiel ; Manuel = l'utilisateur saisit le n° à la création.</div>
                </div>
                <select name="numbering_mode" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    <option value="auto"   @selected($sequence->numbering_mode === 'auto')>Automatique</option>
                    <option value="manual" @selected($sequence->numbering_mode === 'manual')>Manuel</option>
                </select>
                <input type="text" name="reason" maxlength="255" placeholder="Motif (optionnel)"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-xs">
                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                    Appliquer le mode
                </button>
            </form>

            {{-- Verrou --}}
            <form method="POST" action="{{ route('settings.sequences.toggle-lock', $sequence) }}" data-turbo="false"
                  class="border border-gray-200 rounded-[4px] p-4 space-y-3">
                @csrf
                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-1">
                        @if($sequence->is_locked) Déverrouiller le format @else Verrouiller le format @endif
                    </div>
                    <div class="text-xs text-gray-500">
                        @if($sequence->is_locked)
                            Le format est actuellement verrouillé. Le déverrouiller permettra de modifier préfixe/suffixe/padding.
                        @else
                            Verrouiller empêche toute modification accidentelle du format. Le compteur reste modifiable.
                        @endif
                    </div>
                </div>
                <input type="text" name="reason" maxlength="255" placeholder="Motif (optionnel)"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-xs">
                <button type="submit"
                        class="w-full {{ $sequence->is_locked ? 'bg-gray-600 hover:bg-gray-700' : 'bg-red-600 hover:bg-red-700' }} text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                    @if($sequence->is_locked) 🔓 Déverrouiller @else 🔒 Verrouiller @endif
                </button>
            </form>

            {{-- Reset --}}
            <form method="POST" action="{{ route('settings.sequences.reset', $sequence) }}" data-turbo="false"
                  onsubmit="return confirm('Remettre le compteur à 0 ?\n\n⚠ Si des documents ont déjà été émis, le système refusera sauf avec « Forcer ».\nLe prochain document portera le n° 001.')"
                  class="border border-red-200 bg-red-50/30 rounded-[4px] p-4 space-y-3">
                @csrf
                <div>
                    <div class="text-sm font-semibold text-red-900 mb-1">Remise à zéro</div>
                    <div class="text-xs text-red-600">⚠ Action destructrice. Le système refuse si des documents existent déjà.</div>
                </div>
                <input type="text" name="reason" maxlength="255" placeholder="Motif (obligatoire en audit)" required
                       class="w-full border border-red-300 bg-white rounded-[4px] px-3 py-2 text-xs">
                <label class="flex items-center gap-2 text-xs text-red-700">
                    <input type="checkbox" name="force" value="1" class="rounded border-red-300 text-red-600">
                    Forcer même si documents existants
                </label>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                    🗑 Remettre à 0
                </button>
            </form>

        </div>
    </div>

</div>
@endsection
