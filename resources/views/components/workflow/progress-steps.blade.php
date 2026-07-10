{{--
    x-workflow.progress-steps — Barre de progression visuelle pour les workflows documentaires

    Usage :
        <x-workflow.progress-steps
            :steps="[
                ['key' => 'brouillon',  'label' => 'Brouillon',  'icon' => '✏️'],
                ['key' => 'emise',      'label' => 'Émise',       'icon' => '📄'],
                ['key' => 'envoyee',    'label' => 'Envoyée',     'icon' => '📬'],
                ['key' => 'payee',      'label' => 'Payée',       'icon' => '✅'],
            ]"
            current="envoyee"
        />

    Props :
        steps   : array de ['key', 'label', 'icon'?] — dans l'ordre du workflow
        current : clé du statut courant
        size    : sm | md (default: md)
--}}
@props([
    'steps'   => [],
    'current' => '',
    'size'    => 'md',
])

@php
    // Trouver l'index du statut courant
    $currentIndex = -1;
    foreach ($steps as $i => $step) {
        if ($step['key'] === $current) {
            $currentIndex = $i;
            break;
        }
    }

    // Tailles
    $circleSize  = $size === 'sm' ? 'w-7 h-7 text-xs'  : 'w-9 h-9 text-sm';
    $labelSize   = $size === 'sm' ? 'text-xs'           : 'text-xs';
    $iconSize    = $size === 'sm' ? 'text-sm'            : 'text-base';
    $lineHeight  = $size === 'sm' ? 'top-3.5'           : 'top-4.5';
@endphp

<div class="w-full overflow-x-auto">
    <div class="flex items-start justify-between min-w-max px-1 py-2" style="min-width: {{ count($steps) * 80 }}px;">
        @foreach($steps as $i => $step)
        @php
            $done    = $i < $currentIndex;
            $active  = $i === $currentIndex;
            $pending = $i > $currentIndex;
            $isLast  = $i === count($steps) - 1;
        @endphp

        <div class="flex flex-col items-center relative" style="flex: 1; min-width: 72px;">

            {{-- Ligne de connexion gauche --}}
            @if($i > 0)
            <div class="absolute left-0 right-1/2 h-0.5 {{ $lineHeight }} {{ $done || $active ? 'bg-emerald-600' : 'bg-gray-200' }}"
                 style="top: {{ $size === 'sm' ? '14px' : '18px' }}; z-index: 0;"></div>
            @endif

            {{-- Ligne de connexion droite --}}
            @if(!$isLast)
            <div class="absolute left-1/2 right-0 h-0.5 {{ $done ? 'bg-emerald-600' : 'bg-gray-200' }}"
                 style="top: {{ $size === 'sm' ? '14px' : '18px' }}; z-index: 0;"></div>
            @endif

            {{-- Cercle / icône --}}
            <div class="relative z-10 {{ $circleSize }} rounded-full flex items-center justify-center flex-shrink-0 transition-all duration-300 ring-2
                @if($done)    bg-emerald-700 ring-indigo-600 text-white
                @elseif($active) bg-white ring-indigo-600 text-emerald-800 shadow-md
                @else         bg-white ring-gray-200 text-gray-400
                @endif">
                @if($done)
                    {{-- Coche --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                @elseif(isset($step['icon']))
                    <span class="{{ $iconSize }} leading-none select-none">{{ $step['icon'] }}</span>
                @else
                    <span class="font-bold text-xs">{{ $i + 1 }}</span>
                @endif
            </div>

            {{-- Label --}}
            <div class="mt-1.5 text-center px-1">
                <span class="{{ $labelSize }} font-medium leading-tight
                    @if($active) text-emerald-800
                    @elseif($done) text-gray-600
                    @else text-gray-400
                    @endif">
                    {{ $step['label'] }}
                </span>
            </div>

        </div>
        @endforeach
    </div>
</div>
