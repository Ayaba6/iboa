{{--
    x-ui.form-footer — Pied de formulaire avec actions
    Sur mobile : barre fixée en bas de l'écran (safe area).
    Sur desktop : footer inline aligné à droite.

    Props :
        cancelUrl    : URL du bouton Annuler (string|null)
        cancelLabel  : libellé Annuler (default: 'Annuler')
        submitLabel  : libellé du bouton principal (default: 'Enregistrer')
        submitColor  : couleur bouton submit — indigo|emerald|blue (default: indigo)
        loadingVar   : nom de la variable Alpine x-data pour le loading (optionnel)
        loadingText  : texte pendant le chargement (default: 'Enregistrement...')
        formId       : id du formulaire parent — si renseigné, le submit mobile est form="{{ $formId }}" (optionnel)
    Slot default : boutons supplémentaires insérés avant le bouton principal
--}}
@props([
    'cancelUrl'   => null,
    'cancelLabel' => 'Annuler',
    'submitLabel' => 'Enregistrer',
    'submitColor' => 'indigo',
    'loadingVar'  => null,
    'loadingText' => 'Enregistrement...',
    'formId'      => null,
])

@php
$colorMap = [
    'indigo'  => 'bg-emerald-700 hover:bg-emerald-800 focus:ring-emerald-500',
    'emerald' => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500',
    'blue'    => 'bg-emerald-700 hover:bg-emerald-800 focus:ring-emerald-500',
    'amber'   => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
    'red'     => 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
];
$btnColor = $colorMap[$submitColor] ?? $colorMap['indigo'];
$submitAttrs = $formId ? "form=\"{$formId}\"" : '';
@endphp

{{-- ── Desktop : footer inline ──────────────────────────────────────────────── --}}
<div class="hidden sm:flex items-center justify-end gap-3 mt-6 pt-5 border-t border-gray-100">

    {{ $slot }}

    @if($cancelUrl)
    <a href="{{ $cancelUrl }}"
       class="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        {{ $cancelLabel }}
    </a>
    @endif

    <button type="submit"
            {!! $submitAttrs !!}
            @if($loadingVar) @click="{{ $loadingVar }} = true" :disabled="{{ $loadingVar }}"
            :class="{{ $loadingVar }} ? 'opacity-60 cursor-not-allowed' : ''"
            x-text="{{ $loadingVar }} ? '{{ $loadingText }}' : '{{ $submitLabel }}'" @endif
            class="{{ $btnColor }} text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 shadow-sm disabled:opacity-60">
        {{ $submitLabel }}
    </button>
</div>

{{-- ── Mobile : barre fixée en bas ─────────────────────────────────────────── --}}
{{-- Spacer pour que le contenu ne soit pas caché derrière la barre --}}
<div class="sm:hidden h-20"></div>

<div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-lg"
     style="padding-bottom: env(safe-area-inset-bottom, 0px);">
    <div class="flex items-center gap-2 px-4 py-3">

        @if($cancelUrl)
        <a href="{{ $cancelUrl }}"
           class="flex-shrink-0 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            {{ $cancelLabel }}
        </a>
        @endif

        {{ $slot }}

        <button type="submit"
                {!! $submitAttrs !!}
                @if($loadingVar) @click="{{ $loadingVar }} = true" :disabled="{{ $loadingVar }}"
                :class="{{ $loadingVar }} ? 'opacity-60 cursor-not-allowed' : ''"
                x-text="{{ $loadingVar }} ? '{{ $loadingText }}' : '{{ $submitLabel }}'" @endif
                class="flex-1 {{ $btnColor }} text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 shadow-sm disabled:opacity-60 text-center">
            {{ $submitLabel }}
        </button>
    </div>
</div>
