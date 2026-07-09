{{--
  x-dashboard.kpi-card
  Props:
    $label       — libellé
    $value       — valeur numérique (entier)
    $unit        — ex: "FCFA" | null
    $sub         — sous-texte libre
    $icon        — SVG path data
    $color       — tailwind color key: indigo|emerald|violet|sky|rose|amber
    $trend       — ['value'=>12.5, 'direction'=>'up'|'down'] | null
    $href        — lien cliquable (optionnel)
    $counter     — true = animation counter
--}}
@props([
    'label'   => '',
    'value'   => 0,
    'unit'    => null,
    'sub'     => null,
    'icon'    => '',
    'color'   => 'indigo',
    'trend'   => null,
    'href'    => null,
    'counter' => true,
])
@php
    $colors = [
        'indigo'  => ['icon_bg' => 'bg-emerald-700 shadow-indigo-200',  'icon_text' => 'text-white', 'ring' => 'hover:ring-indigo-100'],
        'emerald' => ['icon_bg' => 'bg-emerald-500 shadow-emerald-200','icon_text' => 'text-white', 'ring' => 'hover:ring-emerald-100'],
        'violet'  => ['icon_bg' => 'bg-violet-600 shadow-violet-200',  'icon_text' => 'text-white', 'ring' => 'hover:ring-violet-100'],
        'sky'     => ['icon_bg' => 'bg-sky-500 shadow-sky-200',        'icon_text' => 'text-white', 'ring' => 'hover:ring-sky-100'],
        'rose'    => ['icon_bg' => 'bg-rose-500 shadow-rose-200',      'icon_text' => 'text-white', 'ring' => 'hover:ring-rose-100'],
        'amber'   => ['icon_bg' => 'bg-amber-500 shadow-amber-200',    'icon_text' => 'text-white', 'ring' => 'hover:ring-amber-100'],
    ];
    $c = $colors[$color] ?? $colors['indigo'];
@endphp

<div
    @if($counter)
    x-data="kpiCounter({{ (int)$value }})"
    x-init="init()"
    @endif
    class="kpi-card group relative bg-white rounded-[4px] border border-gray-300 overflow-hidden
           ring-2 ring-transparent transition-all duration-200 {{ $c['ring'] }}
           @if($href) cursor-pointer @endif">

    @if($href)
    <a href="{{ $href }}" class="absolute inset-0 z-10"></a>
    @endif

    {{-- Top section --}}
    <div class="p-5">
        <div class="flex items-start justify-between gap-3">
            {{-- Label + Value --}}
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 truncate">{{ $label }}</p>
                <p class="mt-2.5 text-3xl font-black text-gray-900 tabular-nums leading-none tracking-tight"
                   @if($counter) x-text="formatted()" @else data-value="{{ number_format($value, 0, ',', ' ') }}" @endif>
                    @if(!$counter){{ number_format($value, 0, ',', ' ') }}@endif
                </p>
                @if($unit)
                <p class="mt-1 text-xs font-medium text-gray-400">{{ $unit }}</p>
                @endif
                @if($sub)
                <p class="mt-0.5 text-xs text-gray-400">{{ $sub }}</p>
                @endif
            </div>

            {{-- Icon + Trend --}}
            <div class="flex flex-col items-end gap-2 flex-shrink-0">
                <div class="w-11 h-11 rounded-xl {{ $c['icon_bg'] }} shadow-md flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $c['icon_text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $icon !!}
                    </svg>
                </div>

                @if($trend && $trend['value'] !== null)
                <div class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold
                            {{ $trend['direction'] === 'up' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($trend['direction'] === 'up')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"/>
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/>
                        @endif
                    </svg>
                    {{ $trend['direction'] === 'up' ? '+' : '-' }}{{ $trend['value'] }}%
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Slot for sparkline or extra content --}}
    @if($slot->isNotEmpty())
    <div class="-mt-1">{{ $slot }}</div>
    @endif

    {{-- Bottom accent bar --}}
    <div class="h-0.5 w-0 group-hover:w-full transition-all duration-500 ease-out {{ str_replace('bg-', 'bg-', str_replace('shadow-', '', $c['icon_bg'])) }}"
         style="background: inherit;opacity:.15"></div>
</div>
