{{--
    [Design system X3] Footer noir de contexte — étape 6 du squelette.
    Société / Module / infos additionnelles (slot) / Utilisateur / horodatage automatiques.

    Usage :
        x-x3.footer module="CRM — Tableau de bord">
            <span>Période : <strong class="text-white">juillet 2026</strong></span>
        /x-x3.footer>
--}}
@props(['module'])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs']) }}>
    <div class="flex items-center gap-4 flex-wrap">
        <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
        <span>Module : <strong class="text-white">{{ $module }}</strong></span>
        {{ $slot }}
    </div>
    <div class="flex items-center gap-4">
        <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
        <span>{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
