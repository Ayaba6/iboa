{{-- <x-erp.card title="Mes validations" :flush="true"> [slot actions] contenu </x-erp.card> --}}
@props(['title' => null, 'flush' => false, 'footer' => null])
<div {{ $attributes->merge(['class' => 'erp-card']) }}>
    @if($title || isset($actions))
    <div class="erp-card-header">
        @if($title)<h2 class="erp-card-title">{{ $title }}</h2>@endif
        @isset($actions)<div class="erp-toolbar">{{ $actions }}</div>@endisset
    </div>
    @endif
    <div class="erp-card-body {{ $flush ? 'erp-card-body--flush' : '' }}">{{ $slot }}</div>
    @if($footer)<div class="erp-card-footer">{{ $footer }}</div>@endif
</div>
