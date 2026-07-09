{{-- <x-erp.select name="type" label="Type" :options="['a' => 'A']" :selected="old('type')" required /> --}}
@props(['name', 'label' => null, 'required' => false, 'help' => null, 'options' => [], 'selected' => null, 'placeholder' => null])
<div class="erp-form-group">
    @if($label)<label for="{{ $name }}" class="erp-label {{ $required ? 'erp-required' : '' }}">{{ $label }}</label>@endif
    <select id="{{ $name }}" name="{{ $name }}" @if($required) required @endif
            {{ $attributes->merge(['class' => 'erp-select' . ($errors->has($name) ? ' erp-input--error' : '')]) }}>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $v => $l)
        <option value="{{ $v }}" @selected(old($name, $selected) == $v)>{{ $l }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if($help)<p class="erp-field-help">{{ $help }}</p>@endif
    @error($name)<p class="erp-field-error">{{ $message }}</p>@enderror
</div>
