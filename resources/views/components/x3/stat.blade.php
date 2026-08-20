{{--
    [Design system X3] Case de synthèse (dans x-x3.synthesis>).

    Props :
        label : libellé uppercase 10px
        value : valeur (string formatée — number_format côté vue)
        color : gray (défaut) | blue (débit/info) | emerald (positif) | red (alerte) | amber
        unit  : suffixe discret (ex. FCFA)
        sub   : ligne 10px sous la valeur
        href  : rend la case cliquable (hover bandeau)
--}}
@props(['label', 'value', 'color' => 'gray', 'unit' => null, 'sub' => null, 'href' => null])

@php
    $valueColor = ['gray' => 'text-gray-900', 'blue' => 'text-blue-700', 'emerald' => 'text-emerald-700', 'red' => 'text-red-600', 'amber' => 'text-amber-600'][$color] ?? 'text-gray-900';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'p-3 text-center' . ($href ? ' hover:bg-band/40 transition-colors' : '')]) }}>
    <p class="text-[11px] text-gray-500 uppercase font-semibold tracking-wide">{{ $label }}</p>
    <p class="text-[15px] font-bold font-mono tabular-nums {{ $valueColor }} mt-0.5">{{ $value }}@if($unit) <span class="text-[11px] font-normal text-gray-400">{{ $unit }}</span>@endif</p>
    @if($sub)
        <p class="text-[11px] text-gray-400">{{ $sub }}</p>
    @endif
</{{ $tag }}>
