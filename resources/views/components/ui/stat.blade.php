{{--
    x-ui.stat — Carte KPI / statistique
    Props:
      label   : libellé (requis)
      value   : valeur principale (requis)
      sub     : sous-valeur ou unité (optionnel)
      icon    : emoji (optionnel)
      color   : indigo|emerald|amber|red|blue|violet|teal|cyan|sky|gray (default: indigo)
      trend   : "+N%" (optionnel, vert si positif, rouge si négatif)
      href    : lien cliquable (optionnel)
--}}
@props([
    'label'  => '',
    'value'  => '',
    'sub'    => null,
    'icon'   => null,
    'color'  => 'indigo',
    'trend'  => null,
    'href'   => null,
])

@php
$colors = [
    'indigo'  => ['bg' => 'bg-emerald-100',  'text' => 'text-emerald-700',  'ring' => 'ring-emerald-200'],
    'emerald' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'ring' => 'ring-emerald-200'],
    'amber'   => ['bg' => 'bg-amber-100',   'text' => 'text-amber-600',   'ring' => 'ring-amber-200'],
    'red'     => ['bg' => 'bg-red-100',     'text' => 'text-red-600',     'ring' => 'ring-red-200'],
    'blue'    => ['bg' => 'bg-blue-100',    'text' => 'text-blue-600',    'ring' => 'ring-blue-200'],
    'violet'  => ['bg' => 'bg-teal-100',  'text' => 'text-teal-600',  'ring' => 'ring-teal-200'], // [Charte X3] violet proscrit
    'teal'    => ['bg' => 'bg-emerald-100',    'text' => 'text-emerald-700',    'ring' => 'ring-teal-200'],
    'cyan'    => ['bg' => 'bg-cyan-100',    'text' => 'text-cyan-600',    'ring' => 'ring-cyan-200'],
    'sky'     => ['bg' => 'bg-sky-100',     'text' => 'text-sky-600',     'ring' => 'ring-sky-200'],
    'gray'    => ['bg' => 'bg-gray-100',    'text' => 'text-gray-500',    'ring' => 'ring-gray-200'],
];
$c = $colors[$color] ?? $colors['indigo'];
$trendPositive = $trend && str_starts_with(ltrim((string) $trend), '+');
$classes = 'card p-5 flex flex-col gap-3' . ($href ? ' card-interactive cursor-pointer' : '');
@endphp

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
@else
<div {{ $attributes->merge(['class' => $classes]) }}>
@endif
    <div class="flex items-center justify-between">
        @if($icon)
        <div class="w-10 h-10 rounded-xl {{ $c['bg'] }} {{ $c['text'] }} flex items-center justify-center text-[20px] ring-1 {{ $c['ring'] }}">
            {{ $icon }}
        </div>
        @endif
        @if($trend)
        <span class="ml-auto text-[12px] font-semibold px-2 py-0.5 rounded-full {{ $trendPositive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
            {{ $trend }}
        </span>
        @endif
    </div>
    <div>
        <p class="text-[20px] font-bold text-gray-900 leading-tight tabular-nums">{{ $value }}</p>
        @if($sub)
        <p class="text-[12px] text-gray-500 mt-0.5">{{ $sub }}</p>
        @endif
        <p class="text-[13px] font-medium text-gray-600 mt-1">{{ $label }}</p>
    </div>
    {{ $slot }}
@if($href)
</a>
@else
</div>
@endif
