{{-- <x-erp.input name="code" label="Code" required help="Sous-libellé" /> --}}
@props(['name', 'label' => null, 'required' => false, 'help' => null, 'type' => 'text', 'value' => null])
<div class="erp-form-group">
    @if($label)<label for="{{ $name }}" class="erp-label {{ $required ? 'erp-required' : '' }}">{{ $label }}</label>@endif
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
           value="{{ old($name, $value) }}" @if($required) required @endif
           {{ $attributes->merge(['class' => 'erp-input' . ($errors->has($name) ? ' erp-input--error' : '')]) }}>
    @if($help)<p class="erp-field-help">{{ $help }}</p>@endif
    @error($name)<p class="erp-field-error">{{ $message }}</p>@enderror
</div>
