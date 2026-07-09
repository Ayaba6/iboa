@extends('layouts.erp')

@section('title', 'Types de primes & indemnités')

@section('content')
<div class="space-y-3" x-data="typePrimesPage()">

    {{-- ── En-tête ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Primes &amp; Indemnités</h1>
            <p class="text-sm text-gray-500 mt-0.5">Types de rémunérations accessoires — Burkina Faso</p>
        </div>
        <button @click="openCreate()"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-[4px] bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau type
        </button>
    </div>

    {{-- ── Tableau ──────────────────────────────────────────────────────────── --}}
    <div class="tbl-wrap">
        <div class="tbl-rx">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th class="text-center">IUTS</th>
                    <th class="text-center">CNSS</th>
                    <th>Description</th>
                    <th class="text-center">Utilisations</th>
                    <th class="text-center">Statut</th>
                    <th class="text-right w-24">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                <tr class="{{ $type->is_active ? '' : 'opacity-50' }}">
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                            {{ $type->code }}
                        </span>
                    </td>
                    <td class="font-medium text-gray-900">{{ $type->name }}</td>

                    <td class="text-center">
                        @if($type->is_taxable)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                Oui
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-50 text-green-700 border border-green-200">Exo</span>
                        @endif
                    </td>

                    <td class="text-center">
                        @if($type->is_social_charged)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-50 text-orange-700 border border-orange-200">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                Oui
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-50 text-gray-500 border border-gray-200">Non</span>
                        @endif
                    </td>

                    <td class="text-xs text-gray-500 max-w-xs truncate" title="{{ $type->description }}">
                        {{ $type->description ?? '—' }}
                    </td>

                    <td class="text-center">
                        <span class="text-sm font-semibold {{ $type->employee_allowances_count > 0 ? 'text-emerald-700' : 'text-gray-400' }}">
                            {{ $type->employee_allowances_count }}
                        </span>
                    </td>

                    <td class="text-center">
                        @if($type->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-50 text-green-700 border border-green-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Actif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactif
                            </span>
                        @endif
                    </td>

                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button @click="openEdit({{ $type->id }}, @js($type->only(['name','code','is_taxable','is_social_charged','description','is_active'])))"
                                    class="p-1.5 rounded-[4px] text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 transition" title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            @if($type->employee_allowances_count === 0)
                            <button x-on:click="$dispatch('confirm-action', {
                                        title: 'Supprimer ce type ?',
                                        message: 'Cette action est irréversible.',
                                        confirmText: 'Supprimer',
                                        confirmClass: 'btn-danger',
                                        action: function() {
                                            document.getElementById('delete-form-{{ $type->id }}').submit();
                                        }
                                    })"
                                    class="p-1.5 rounded-[4px] text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Supprimer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            <form id="delete-form-{{ $type->id }}" method="POST"
                                  action="{{ route('rh.types-primes.destroy', $type) }}" class="hidden">
                                @csrf @method('DELETE')
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12 text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Aucun type de prime défini
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- ── Légende ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-4 text-xs text-gray-500 bg-gray-50 rounded-[4px] px-3 py-1.5 border border-gray-200">
        <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">Oui</span>
            <span>= Soumis à l'IUTS (impôt sur salaires)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-50 text-orange-700 border border-orange-200">Oui</span>
            <span>= Soumis à la CNSS (cotisations sociales)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="font-mono text-xs bg-gray-100 px-1.5 rounded border border-gray-200">ANCIENNETE</span>
            <span>= Calculée automatiquement par le moteur de paie (montant ignoré)</span>
        </div>
    </div>

    {{-- ── Modal création / édition ────────────────────────────────────────── --}}
    <div x-show="modal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="close()"></div>

        <div class="relative bg-white rounded-[4px] shadow-2xl w-full max-w-lg mx-auto"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            {{-- Header --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900" x-text="editId ? 'Modifier le type' : 'Nouveau type de prime'"></h2>
                <button @click="close()" class="p-1.5 rounded-[4px] text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Form --}}
            <form :action="editId ? '{{ url('rh/types-primes') }}/' + editId : '{{ route('rh.types-primes.store') }}'"
                  method="POST" class="px-6 py-5 space-y-4">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                {{-- Nom --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="form.name" required maxlength="100"
                           class="w-full rounded-[4px] border border-gray-300 text-sm px-3 py-2 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                           placeholder="ex. Prime de responsabilité">
                </div>

                {{-- Code --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" x-model="form.code" required maxlength="30"
                           @input="form.code = form.code.toUpperCase()"
                           class="w-full rounded-[4px] border border-gray-300 text-sm px-3 py-2 font-mono uppercase focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                           placeholder="ex. RESPONSABILITE">
                    <p class="text-xs text-gray-400 mt-1">Identifiant unique, en majuscules, sans espaces.</p>
                </div>

                {{-- Fiscalité --}}
                <div class="grid grid-cols-2 gap-4">
                    <label class="flex items-start gap-3 p-3 rounded-[4px] border border-gray-300 cursor-pointer hover:bg-amber-50 hover:border-amber-300 transition"
                           :class="form.is_taxable ? 'bg-amber-50 border-amber-300' : ''">
                        <input type="hidden" name="is_taxable" value="0">
                        <input type="checkbox" name="is_taxable" value="1" x-model="form.is_taxable"
                               class="mt-0.5 rounded text-amber-500 focus:ring-amber-400">
                        <div>
                            <p class="text-xs font-semibold text-gray-800">Soumis IUTS</p>
                            <p class="text-xs text-gray-500">Inclus dans le revenu imposable</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-[4px] border border-gray-300 cursor-pointer hover:bg-orange-50 hover:border-orange-300 transition"
                           :class="form.is_social_charged ? 'bg-orange-50 border-orange-300' : ''">
                        <input type="hidden" name="is_social_charged" value="0">
                        <input type="checkbox" name="is_social_charged" value="1" x-model="form.is_social_charged"
                               class="mt-0.5 rounded text-orange-500 focus:ring-orange-400">
                        <div>
                            <p class="text-xs font-semibold text-gray-800">Soumis CNSS</p>
                            <p class="text-xs text-gray-500">Inclus dans l'assiette CNSS</p>
                        </div>
                    </label>
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" x-model="form.description" rows="2" maxlength="500"
                              class="w-full rounded-[4px] border border-gray-300 text-sm px-3 py-2 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none resize-none"
                              placeholder="Description facultative…"></textarea>
                </div>

                {{-- Statut actif --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active"
                           class="rounded text-emerald-700 focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">Type actif (disponible dans les formulaires)</span>
                </label>

                {{-- Boutons --}}
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" @click="close()"
                            class="px-3 py-1.5 rounded-[4px] text-sm text-gray-600 hover:bg-gray-100 transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-[4px] bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium shadow-sm transition">
                        <span x-text="editId ? 'Enregistrer' : 'Créer le type'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
function typePrimesPage() {
    return {
        modal: false,
        editId: null,
        form: { name: '', code: '', is_taxable: false, is_social_charged: false, description: '', is_active: true },

        openCreate() {
            this.editId = null;
            this.form = { name: '', code: '', is_taxable: false, is_social_charged: false, description: '', is_active: true };
            this.modal = true;
        },

        openEdit(id, data) {
            this.editId = id;
            this.form = {
                name:              data.name             || '',
                code:              data.code             || '',
                is_taxable:        !!data.is_taxable,
                is_social_charged: !!data.is_social_charged,
                description:       data.description      || '',
                is_active:         !!data.is_active,
            };
            this.modal = true;
        },

        close() { this.modal = false; },
    };
}
</script>
@endpush
@endsection
