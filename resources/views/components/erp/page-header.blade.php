{{-- <x-erp.page-header title="Tableau de bord" subtitle="…"> [slot = toolbar boutons] </x-erp.page-header> --}}
@props(['title', 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'erp-page-header']) }}>
    <div>
        <h1>{{ $title }}</h1>
        @if($subtitle)<p class="erp-page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if(!$slot->isEmpty())<div class="erp-toolbar">{{ $slot }}</div>@endif
</div>
