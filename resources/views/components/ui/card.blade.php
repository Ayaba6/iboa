{{--
    x-ui.card — Carte de contenu universelle
    Props:
      title    : titre de la carte (optionnel)
      subtitle : sous-titre (optionnel)
      padding  : true|false (default: true)
      hover    : effet hover (default: false)
      noBorder : supprimer la bordure (default: false)
--}}
@props([
    'title'    => null,
    'subtitle' => null,
    'padding'  => true,
    'hover'    => false,
    'noBorder' => false,
])
<div {{ $attributes->merge(['class' => 'card ' . ($hover ? 'card-interactive' : '') . ($noBorder ? ' border-0 shadow-none' : '')]) }}>
    @php $hasHeader = isset($header) && $header->isNotEmpty(); @endphp
    @if($title || $hasHeader)
    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
        <div>
            @if($title)
            <h3 class="text-[13px] font-semibold text-gray-800">{{ $title }}</h3>
            @endif
            @if($subtitle)
            <p class="text-[12px] text-gray-500 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @if($hasHeader)
        <div class="flex items-center gap-2">{{ $header }}</div>
        @endif
    </div>
    @endif
    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</div>
