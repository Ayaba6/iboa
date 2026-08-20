{{--
    x-ui.form-section — Section de formulaire avec titre et fond distinct
    Props:
      title       : titre de la section (requis)
      description : description (optionnel)
      collapsible : peut être réduit (default: false)
      collapsed   : réduit par défaut (default: false)
      icon        : emoji (optionnel)
--}}
@props([
    'title'       => '',
    'description' => null,
    'collapsible' => false,
    'collapsed'   => false,
    'icon'        => null,
])

@php $xOpen = $collapsed ? 'false' : 'true'; @endphp

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}
     @if($collapsible) x-data="{ open: {{ $xOpen }} }" @endif>

    <div @class([
            'flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50/60',
            'cursor-pointer select-none' => $collapsible,
         ])
         @if($collapsible) @click="open = !open" role="button" @endif>
        <div class="flex items-center gap-2.5">
            @if($icon)
            <span class="text-[15px]">{{ $icon }}</span>
            @endif
            <div>
                <h3 class="text-[13px] font-semibold text-gray-800">{{ $title }}</h3>
                @if($description)
                <p class="text-[12px] text-gray-500 mt-0.5">{{ $description }}</p>
                @endif
            </div>
        </div>
        @if($collapsible)
        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? '' : '-rotate-90'"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
        @endif
    </div>

    <div @if($collapsible)
             x-show="open"
             x-transition:enter="transition-all ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition-all ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
         @endif
         class="p-5">
        {{ $slot }}
    </div>
</div>
