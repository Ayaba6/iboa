{{--
    x-ui.page-header — En-tête de page uniforme
    Props:
      title       : titre principal (requis)
      subtitle    : description (optionnel)
      icon        : emoji ou SVG (optionnel)
      backUrl     : URL retour (optionnel — sinon JS history.back())
      backLabel   : label bouton retour (default: 'Retour')
    Slots:
      actions     : boutons d'action à droite
      meta        : badges/méta sous le titre
--}}
@props([
    'title'     => '',
    'subtitle'  => null,
    'icon'      => null,
    'backUrl'   => null,
    'backLabel' => 'Retour',
])
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-3 min-w-0">
        {{-- Back button --}}
        @if($backUrl !== false)
        <a href="{{ $backUrl ?? 'javascript:history.back()' }}"
           class="mt-1 flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors shadow-sm"
           title="{{ $backLabel }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        @endif

        <div class="min-w-0">
            <div class="flex items-center gap-2 mb-0.5">
                @if($icon)
                <span class="text-[20px] leading-none">{{ $icon }}</span>
                @endif
                <h1 class="text-[20px] font-bold text-gray-900 leading-tight truncate">{{ $title }}</h1>
            </div>
            @if($subtitle)
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $subtitle }}</p>
            @endif
            @isset($meta)
            <div class="flex items-center gap-2 mt-2 flex-wrap">{{ $meta }}</div>
            @endisset
        </div>
    </div>

    @isset($actions)
    <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
        {{ $actions }}
    </div>
    @endisset
</div>
