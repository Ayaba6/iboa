{{--
    [Design system X3] Barre de synthèse basse — étape 5 du squelette.
    Enfants : x-x3.stat …>. cols = nombre de colonnes lg (2..6).
--}}
@props(['cols' => 4])

@php
    $grid = ['2' => 'lg:grid-cols-2', '3' => 'lg:grid-cols-3', '4' => 'lg:grid-cols-4', '5' => 'lg:grid-cols-5', '6' => 'lg:grid-cols-6'][(string) $cols] ?? 'lg:grid-cols-4';
@endphp

<div {{ $attributes->merge(['class' => "bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 {$grid} divide-x divide-gray-200"]) }}>
    {{ $slot }}
</div>
