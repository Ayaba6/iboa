{{-- <x-erp.button variant="primary|secondary|success|danger|outline|light" type="submit" href="..."> --}}
@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])
@php $cls = 'erp-btn erp-btn-' . $variant; @endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
