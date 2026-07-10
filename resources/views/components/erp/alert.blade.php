{{-- <x-erp.alert variant="warning" title="4 documents en attente">détail…</x-erp.alert> --}}
@props(['variant' => 'info', 'title' => null])
<div {{ $attributes->merge(['class' => 'erp-alert erp-alert-' . $variant]) }}>
    <div>
        @if($title)<strong>{{ $title }}</strong>@endif
        @if(!$slot->isEmpty())<div>{{ $slot }}</div>@endif
    </div>
</div>
