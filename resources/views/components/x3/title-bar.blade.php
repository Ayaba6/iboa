{{--
    [Design system X3] Barre titre + actions — étape 1 du squelette.

    Usage :
        x-x3.title-bar title="Balance générale" subtitle="Référentiel SYSCOHADA — 10 comptes">
            <a href="…" class="…bg-emerald-700…">Action primaire</a>
            <a href="…" class="…border…">✕ Fermer</a>
        /x-x3.title-bar>

    Règles charte : UNE action primaire émeraude, secondaires blanches bordées,
    « ✕ Fermer » en dernier.
--}}
@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center justify-between gap-2']) }}>
    <div>
        <h1 class="text-[17px] font-bold text-gray-900">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-xs text-gray-400 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @if(trim($slot))
    <div class="flex items-center gap-2 self-start flex-wrap">
        {{ $slot }}
    </div>
    @endif
</div>
