{{--
    x-ui.empty — État vide (no data)
    Props:
      icon     : emoji (default: 📭)
      title    : titre (default: 'Aucune donnée')
      message  : message explicatif (optionnel)
      action   : libellé du CTA (optionnel)
      href     : URL du CTA (optionnel)
--}}
@props([
    'icon'    => '📭',
    'title'   => 'Aucune donnée',
    'message' => null,
    'action'  => null,
    'href'    => null,
])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-14 px-6 text-center']) }}>
    <div class="w-16 h-16 rounded-[4px] bg-gray-100 flex items-center justify-center text-3xl mb-4 shadow-inner">
        {{ $icon }}
    </div>
    <p class="text-base font-semibold text-gray-700 mb-1">{{ $title }}</p>
    @if($message)
    <p class="text-sm text-gray-400 max-w-xs leading-relaxed">{{ $message }}</p>
    @endif
    @if($slot->isNotEmpty())
    <div class="mt-4">{{ $slot }}</div>
    @elseif($action && $href)
    <a href="{{ $href }}" class="mt-4 btn btn-primary btn-sm">
        + {{ $action }}
    </a>
    @endif
</div>
