{{-- <x-erp.textarea name="notes" label="Notes" rows="2" /> --}}
@props(['name', 'label' => null, 'required' => false, 'help' => null, 'rows' => 2, 'value' => null])
<div class="erp-form-group">
    @if($label)<label for="{{ $name }}" class="erp-label {{ $required ? 'erp-required' : '' }}">{{ $label }}</label>@endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif
              {{ $attributes->merge(['class' => 'erp-textarea' . ($errors->has($name) ? ' erp-input--error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @if($help)<p class="erp-field-help">{{ $help }}</p>@endif
    @error($name)<p class="erp-field-error">{{ $message }}</p>@enderror
</div>
