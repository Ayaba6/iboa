@props(['href', 'label', 'icon', 'active' => false, 'indent' => false])
<a href="{{ $href }}"
   title="{{ $label }}"
   class="group relative flex items-center gap-3 rounded-lg text-[13px] transition-all duration-150
          {{ $indent ? 'px-3 py-2 font-normal' : 'px-3 py-2.5 font-medium' }}
          {{ $active
              ? 'bg-white/15 text-white shadow-sm'
              : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}"
   :class="sidebarCollapsed ? '' : '{{ $indent ? 'pl-7' : '' }}'">

    {{-- Left accent for active item --}}
    @if($active)
    <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-indigo-300 rounded-r-full"></span>
    @endif

    {{-- Icon --}}
    <svg class="flex-shrink-0 transition-transform duration-150
                {{ $indent ? 'w-4 h-4' : 'w-[18px] h-[18px]' }}
                {{ $active ? 'text-white' : 'text-indigo-400 group-hover:text-white group-hover:scale-110' }}"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        {!! $icon !!}
    </svg>

    {{-- Label --}}
    <span x-show="!sidebarCollapsed"
          x-transition:enter="transition-opacity duration-150"
          x-transition:enter-start="opacity-0"
          x-transition:enter-end="opacity-100"
          class="truncate leading-none">{{ $label }}</span>

    {{-- Collapsed tooltip --}}
    <div x-show="sidebarCollapsed"
         class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-[12px] rounded-md whitespace-nowrap
                opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50">
        {{ $label }}
        <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
    </div>
</a>
