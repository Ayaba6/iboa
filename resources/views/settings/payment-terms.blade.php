@extends('layouts.erp')
@section('title', 'Conditions de paiement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Conditions de paiement</span>
@endsection

@section('content')
<div x-data="{
    modal: '',
    form: { name: '', days: 0, end_of_month: false, additional_days: 0, is_active: true },
    editId: null,
    openCreate() {
        this.form = { name: '', days: 0, end_of_month: false, additional_days: 0, is_active: true };
        this.editId = null; this.modal = 'form';
    },
    openEdit(id, name, days, endOfMonth, additionalDays, isActive) {
        this.form = { name, days, end_of_month: endOfMonth, additional_days: additionalDays, is_active: isActive };
        this.editId = id; this.modal = 'form';
    },
}" class="space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Conditions de paiement</h1>
            <p class="text-sm text-gray-500 mt-0.5">Délais appliqués au calcul automatique de l'échéance des factures</p>
        </div>
        @can('settings.manage')
        <button @click="openCreate()"
                class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle condition
        </button>
        @endcan
    </div>

    {{-- Info banner --}}
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 flex gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="text-sm text-blue-700">
            Sélectionnez une condition lors de la création d'une facture pour calculer automatiquement l'<strong>échéance</strong>.
            <br>Algorithme : <em>Date émission + Jours → [Fin de mois] → + Jours supplémentaires</em>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-600 uppercase">Libellé</th>
                    <th class="px-3 py-1.5 text-center text-xs font-semibold text-gray-600 uppercase">Délai (j)</th>
                    <th class="px-3 py-1.5 text-center text-xs font-semibold text-gray-600 uppercase">Fin de mois</th>
                    <th class="px-3 py-1.5 text-center text-xs font-semibold text-gray-600 uppercase">Jours supp.</th>
                    <th class="px-3 py-1.5 text-center text-xs font-semibold text-gray-600 uppercase">Statut</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($paymentTerms as $term)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 font-medium text-gray-900">{{ $term->name }}</td>
                    <td class="px-3 py-1.5 text-center tabular-nums">{{ $term->days }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($term->end_of_month)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-emerald-100 text-emerald-800">Oui</span>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center tabular-nums text-gray-500">
                        {{ $term->additional_days > 0 ? '+' . $term->additional_days : '—' }}
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($term->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @can('settings.manage')
                        <div class="flex items-center justify-end gap-2">
                            <button @click="openEdit({{ $term->id }}, '{{ addslashes($term->name) }}', {{ $term->days }}, {{ $term->end_of_month ? 'true' : 'false' }}, {{ $term->additional_days }}, {{ $term->is_active ? 'true' : 'false' }})"
                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                Modifier
                            </button>
                            <form action="{{ route('settings.payment-terms.destroy', $term) }}" method="POST"
                                  onsubmit="return confirm('Supprimer cette condition ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Supprimer</button>
                            </form>
                        </div>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                        Aucune condition de paiement configurée.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Create / Edit Modal --}}
    @can('settings.manage')
    <div x-show="modal === 'form'" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div @click.outside="modal = ''" class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6">
            <h2 class="text-[16px] font-bold text-gray-900 mb-4"
                x-text="editId ? 'Modifier la condition' : 'Nouvelle condition de paiement'"></h2>

            <form :action="editId ? '{{ url('parametres/conditions-paiement') }}/' + editId : '{{ route('settings.payment-terms.store') }}'"
                  method="POST" class="space-y-4">
                @csrf
                <template x-if="editId">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="form.name" required
                           class="w-full rounded-[4px] border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                           placeholder="ex : 30 jours nets">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Délai (jours) <span class="text-red-500">*</span></label>
                        <input type="number" name="days" x-model="form.days" min="0" max="365" required
                               class="w-full rounded-[4px] border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jours supp.</label>
                        <input type="number" name="additional_days" x-model="form.additional_days" min="0" max="60"
                               class="w-full rounded-[4px] border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="end_of_month" x-model="form.end_of_month" value="1"
                               class="rounded border-gray-300 text-blue-600">
                        Snap fin de mois
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="is_active" x-model="form.is_active" value="1"
                               class="rounded border-gray-300 text-green-600">
                        Actif
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="modal = ''"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">
                        <span x-text="editId ? 'Enregistrer' : 'Créer'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

</div>
@endsection
