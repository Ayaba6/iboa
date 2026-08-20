{{--
    x-ui.badge — Badge de statut universel
    Props:
      color  : gray|blue|green|red|orange|purple|teal|yellow|indigo|amber|sky|emerald|cyan (default: gray)
      dot    : afficher la pastille (default: true)
      size   : sm|md (default: md)
--}}
@props([
    'color' => 'gray',
    'dot'   => true,
    'size'  => 'md',
])

@php
$colorClass = match($color) {
    'blue'    => 'badge-blue',
    'green',
    'emerald' => 'badge-green',
    'red'     => 'badge-red',
    'orange'  => 'badge-orange',
    'purple'  => 'badge-purple',
    'teal'    => 'badge-teal',
    'yellow'  => 'badge-yellow',
    'indigo'  => 'badge-indigo',
    'amber'   => 'badge-amber',
    'sky'     => 'badge-sky',
    'cyan'    => 'bg-cyan-100 text-cyan-700',
    default   => 'badge-gray',
};
$sizeClass = $size === 'sm' ? 'text-[11px] px-2 py-0.5' : '';
$plainClass = $dot ? '' : 'badge-plain';
@endphp

<span {{ $attributes->merge(['class' => "badge $colorClass $sizeClass $plainClass"]) }}>
    {{ $slot }}
</span>
