@extends('layouts.erp')
@section('title', 'Devises')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Devises</span>
@endsection

@section('content')
<div x-data="{
    modal: '',
    form: { code: '', name: '', symbol: '', decimal_places: 0, thousands_separator: ' ', decimal_separator: ',', is_default: false, is_active: true },
    editId: null,
    editCode: '',
    openCreate() {
        this.form = { code: '', name: '', symbol: '', decimal_places: 0, thousands_separator: ' ', decimal_separator: ',', is_default: false, is_active: true };
        this.editId = null; this.editCode = ''; this.modal = 'form';
    },
    openEdit(id, code, name, symbol, dp, ts, ds, isActive) {
        this.form = { code, name, symbol, decimal_places: dp, thousands_separator: ts, decimal_separator: ds, is_default: false, is_active: isActive };
        this.editId = id; this.editCode = code; this.modal = 'form';
    },
    preview() {
        const n = 1234567.89;
        const parts = n.toFixed(this.form.decimal_places).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.form.thousands_separator || ' ');
        return parts.join(this.form.decimal_places > 0 ? (this.form.decimal_separator || ',') : '') + ' ' + (this.form.symbol || '?');
    }
}" class="space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Devises</h1>
            <p class="text-sm text-gray-500 mt-0.5">Configurez les devises utilisées dans l'application</p>
        </div>
        @can('settings.manage')
        <button @click="openCreate()"
                class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle devise
        </button>
        @endcan
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Code</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nom</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Symbole</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Format</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Décimales</th>
                    <th class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Par défaut</th>
                    <th class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($currencies as $currency)
                <tr class="{{ $currency->is_default ? 'bg-blue-50/40' : '' }} hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <span class="font-mono font-bold text-gray-900">{{ $currency->code }}</span>
                    </td>
                    <td class="px-5 py-3 text-gray-700">{{ $currency->name }}</td>
                    <td class="px-5 py-3 font-medium text-gray-800">{{ $currency->symbol }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-gray-500">
                        @php
                            $sample = number_format(1234567, $currency->decimal_places, $currency->decimal_separator, $currency->thousands_separator);
                        @endphp
                        {{ $sample }} {{ $currency->symbol }}
                    </td>
                    <td class="px-5 py-3 text-center tabular-nums text-gray-600">{{ $currency->decimal_places }}</td>
                    <td class="px-5 py-3 text-center">
                        @if($currency->is_default)
                        <svg class="w-4 h-4 text-blue-600 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $currency->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $currency->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        @can('settings.manage')
                        <div class="flex items-center justify-end gap-2">
                            @if(!$currency->is_default)
                            <form method="POST" action="{{ route('settings.currencies.set-default', $currency) }}">@csrf
                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">Par défaut</button>
                            </form>
                            @endif
                            <button @click="openEdit({{ $currency->id }}, '{{ $currency->code }}', '{{ addslashes($currency->name) }}', '{{ addslashes($currency->symbol) }}', {{ $currency->decimal_places }}, '{{ $currency->thousands_separator }}', '{{ $currency->decimal_separator }}', {{ $currency->is_active ? 'true' : 'false' }})"
                                    class="text-xs text-gray-500 hover:text-gray-700 font-medium">Modifier</button>
                            @if(!$currency->is_default)
                            <form method="POST" action="{{ route('settings.currencies.destroy', $currency) }}"
                                  onsubmit="return confirm('Supprimer la devise {{ $currency->code }} ?')">@csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium">Supprimer</button>
                            </form>
                            @endif
                        </div>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-16 text-center text-gray-400">Aucune devise configurée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-show="modal === 'form'" x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-[4px] shadow-xl w-full max-w-lg p-6">
            <h3 class="text-[16px] font-bold text-gray-900 mb-5"
                x-text="editId ? 'Modifier la devise' : 'Nouvelle devise'"></h3>

            <form method="POST" data-turbo="false"
                  x-bind:action="editId
                    ? '{{ url('parametres/devises') }}/' + editId
                    : '{{ route('settings.currencies.store') }}'"
                  class="space-y-4">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code ISO <span class="text-red-500">*</span></label>
                        <input type="text" name="code" x-model="form.code" :readonly="editId" required maxlength="3"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-emerald-500"
                               placeholder="XOF">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Symbole <span class="text-red-500">*</span></label>
                        <input type="text" name="symbol" x-model="form.symbol" required maxlength="10"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                               placeholder="FCFA">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Décimales</label>
                        <input type="number" name="decimal_places" x-model.number="form.decimal_places" min="0" max="4"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="form.name" required maxlength="80"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Franc CFA BCEAO">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Séparateur milliers</label>
                        <select name="thousands_separator" x-model="form.thousands_separator"
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            <option value=" ">Espace (1 234 567)</option>
                            <option value=".">Point (1.234.567)</option>
                            <option value=",">Virgule (1,234,567)</option>
                            <option value="">Aucun (1234567)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Séparateur décimal</label>
                        <select name="decimal_separator" x-model="form.decimal_separator"
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            <option value=",">,  (1 234,56)</option>
                            <option value=".">. (1,234.56)</option>
                        </select>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="bg-gray-50 border border-gray-200 rounded-[4px] px-3 py-1.5">
                    <p class="text-xs text-gray-500 mb-1">Aperçu du format</p>
                    <p class="font-mono font-semibold text-gray-900 text-lg" x-text="preview()"></p>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active"
                               class="rounded border-gray-300 text-blue-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-gray-700">Active</span>
                    </label>
                    <template x-if="!editId">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" value="1" x-model="form.is_default"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-emerald-500">
                            <span class="text-sm font-medium text-gray-700">Devise par défaut</span>
                        </label>
                    </template>
                </div>

                <div class="flex gap-3 justify-end pt-2">
                    <button type="button" @click="modal = ''"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
                    <button type="submit"
                            class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]"
                            x-text="editId ? 'Enregistrer' : 'Créer'"></button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
