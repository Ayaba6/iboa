@php
    $isEdit = isset($journalType);
    $action = $isEdit
        ? route('comptabilite.journal-types.update', $journalType)
        : route('comptabilite.journal-types.store');
@endphp

@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form action="{{ $action }}" method="POST" class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                Code <span class="text-red-500">*</span>
                <span class="text-gray-400 font-normal">(2-10 caractères)</span>
            </label>
            <input type="text" name="code" required maxlength="10"
                   value="{{ old('code', $journalType->code ?? '') }}"
                   placeholder="Ex. : OD2"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-violet-500"
                   style="text-transform: uppercase">
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="100"
                   value="{{ old('name', $journalType->name ?? '') }}"
                   placeholder="Ex. : Journal des opérations diverses 2"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
            <select name="type" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">— Sélectionner —</option>
                @foreach($types as $k => $label)
                <option value="{{ $k }}" {{ old('type', $journalType->type ?? '') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2 flex items-center pt-6">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $isEdit ? $journalType->is_active : true) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                <span class="text-sm text-gray-700">Actif (autorise la création d'écritures sur ce journal)</span>
            </label>
        </div>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('comptabilite.journal-types.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">
            {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le code journal' }}
        </button>
    </div>
</form>
