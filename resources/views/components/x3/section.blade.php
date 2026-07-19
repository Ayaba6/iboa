{{--
    [Design system X3] Section numérotée en carte — étape 2 du squelette.

    Usage :
        x-x3.section number="1" title="Critères de sélection">
            x-slot:meta>10 compte(s)/x-slot:meta>   (droite du bandeau, optionnel)
            … contenu (padding p-4 par défaut ; flush pour les tables) …
        /x-x3.section>

    Props :
        number : numéro affiché devant le titre (optionnel)
        title  : titre uppercase émeraude
        flush  : true = pas de padding interne (tables pleine largeur)
--}}
@props(['number' => null, 'title', 'flush' => false])

<div {{ $attributes->merge(['class' => 'bg-white rounded-[4px] border border-gray-300 overflow-hidden']) }}>
    <div class="flex items-center justify-between px-4 py-2 bg-band border-b border-emerald-100">
        <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">{{ $number ? $number . '. ' : '' }}{{ $title }}</p>
        @isset($meta)
            <div class="text-[11px] text-emerald-600">{{ $meta }}</div>
        @endisset
    </div>
    <div @class(['p-4' => ! $flush])>
        {{ $slot }}
    </div>
</div>
