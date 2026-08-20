{{--
    <x-form-field name="client_id" label="Client" required>
        <select name="client_id" class="...">...</select>
    </x-form-field>

    Props :
      name     — clé de l'erreur ($errors->get($name))
      label    — texte du label (affiché au-dessus du slot)
      required — booléen → affiche l'astérisque rouge
      hint     — texte d'aide sous le champ (optionnel)
--}}
@props([
    'name',
    'label'    => null,
    'required' => false,
    'hint'     => null,
])

@php $hasError = $errors->has($name); @endphp

<div class="{{ $hasError ? 'has-error' : '' }}">
    @if($label)
    <label for="{{ $name }}"
           class="block text-[13px] font-medium {{ $hasError ? 'text-red-700' : 'text-gray-700' }} mb-1">
        {{ $label }}
        @if($required)
            <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
        @endif
    </label>
    @endif

    {{-- Slot : l'input/select/textarea du parent --}}
    {{ $slot }}

    {{-- Message d'erreur inline --}}
    @error($name)
        <p class="mt-1 text-[12px] text-red-600 flex items-center gap-1" role="alert">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
        </p>
    @enderror

    {{-- Texte d'aide (masqué si erreur) --}}
    @if($hint && !$hasError)
        <p class="mt-1 text-[12px] text-gray-400">{{ $hint }}</p>
    @endif
</div>
