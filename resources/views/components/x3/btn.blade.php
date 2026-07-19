{{--
    [Design system X3] Bouton / lien d'action de barre titre.

    Props :
        variant : primary (émeraude, UN SEUL par page) | secondary (blanc bordé,
                  défaut) | danger (bordé rouge, actions destructives)
        href    : rend un <a> ; sinon <button type=submit|button via attributes>
--}}
@props(['variant' => 'secondary', 'href' => null])

@php
    $classes = match ($variant) {
        'primary' => 'inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors',
        'danger'  => 'inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50 transition-colors',
        default   => 'inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors',
    };
@endphp

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>{{ $slot }}</button>
@endif
