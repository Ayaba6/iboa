@extends('layouts.erp')
@section('title', 'Exercices fiscaux')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Exercices fiscaux</span>
@endsection

@section('content')
<div x-data="{
    modal: '',
    form: { label: '', starts_at: '', ends_at: '', is_current: false },
    editId: null,
    openCreate() { this.form = { label: '', starts_at: '', ends_at: '', is_current: false }; this.editId = null; this.modal = 'form'; },
    openEdit(id, label, starts, ends) { this.form = { label, starts_at: starts, ends_at: ends, is_current: false }; this.editId = id; this.modal = 'form'; },
}" class="space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Exercices fiscaux</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gérez les exercices comptables de votre société</p>
        </div>
        @can('settings.manage')
        <a href="{{ route('settings.fiscal-years.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-full transition-colors">+ Nouvel exercice</a>
        @endcan
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Exercice</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Début</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Fin</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Durée</th>
                    <th class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Courant</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($years as $fy)
                @php
                    $statusConfig = [
                        'ouvert'  => ['bg-green-100 text-green-700',  'Ouvert'],
                        'cloture' => ['bg-orange-100 text-orange-700','Clôturé'],
                        'archive' => ['bg-gray-100 text-gray-500',    'Archivé'],
                    ][$fy->status] ?? ['bg-gray-100 text-gray-500', $fy->status];
                    $days = $fy->starts_at->diffInDays($fy->ends_at) + 1;
                    $months = round($days / 30.4, 1);
                    // [I-FIX-02] Detect if RAN was already generated (idempotence indicator)
                    $ranGenerated = $fy->status === 'cloture'
                        && \App\Models\JournalEntry::where('reference', 'RAN-'.$fy->label)->exists();
                @endphp
                <tr class="{{ $fy->is_current ? 'bg-blue-50/40' : '' }} hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <span class="font-semibold text-gray-900">{{ $fy->label }}</span>
                        @if($fy->is_current)
                        <span class="ml-2 text-xs font-medium text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded">Courant</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $fy->starts_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ $fy->ends_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-gray-500 text-xs">{{ $days }} j (≈ {{ $months }} mois)</td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusConfig[0] }}">
                            {{ $statusConfig[1] }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($fy->is_current)
                        <svg class="w-4 h-4 text-blue-600 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        @can('settings.manage')
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            @if(!$fy->is_current && $fy->status !== 'archive')
                            <form method="POST" action="{{ route('settings.fiscal-years.set-current', $fy) }}">@csrf
                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">Définir courant</button>
                            </form>
                            @endif
                            @if($fy->status === 'ouvert')
                            <a href="{{ route('settings.fiscal-years.edit', $fy) }}"
                                    class="text-xs text-emerald-700 hover:text-emerald-900 font-medium hover:underline">Modifier</a>
                            <form method="POST" action="{{ route('settings.fiscal-years.close', $fy) }}"
                                  onsubmit="return confirm('Clôturer l\'exercice {{ addslashes($fy->label) }} ? Cette action est irréversible.')">@csrf
                                <button type="submit" class="text-xs text-orange-600 hover:text-orange-800 font-medium">Clôturer</button>
                            </form>
                            @endif
                            @if($fy->status === 'cloture')
                                @if($ranGenerated)
                                <span class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium whitespace-nowrap" title="Le report à nouveau a déjà été généré">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    RAN généré
                                </span>
                                @else
                                <form method="POST" action="{{ route('settings.fiscal-years.report-a-nouveau', $fy) }}"
                                      onsubmit="return confirm('Générer le Report à nouveau pour l\'exercice {{ addslashes($fy->label) }} ? Cela créera une écriture d\'ouverture dans l\'exercice suivant.')">@csrf
                                    <button type="submit" class="text-xs text-violet-600 hover:text-violet-800 font-medium whitespace-nowrap">Report à nouveau</button>
                                </form>
                                @endif
                            @endif
                            @if($fy->status === 'cloture' && !$fy->is_current)
                            <form method="POST" action="{{ route('settings.fiscal-years.archive', $fy) }}"
                                  onsubmit="return confirm('Archiver définitivement cet exercice ?')">@csrf
                                <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 font-medium">Archiver</button>
                            </form>
                            @endif
                        </div>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                        Aucun exercice fiscal défini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal create/edit --}}
    <div x-show="modal === 'form'" x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6">
            <h3 class="text-[16px] font-bold text-gray-900 mb-5"
                x-text="editId ? 'Modifier l\'exercice' : 'Nouvel exercice fiscal'"></h3>
            <form method="POST"
                  :action="editId ? ('{{ url('parametres/exercices') }}/' + editId) : '{{ route('settings.fiscal-years.store') }}'"
                  class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="editId ? 'PUT' : 'POST'">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
                    <input type="text" name="label" x-model="form.label" required maxlength="50"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="2025-2026">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Début <span class="text-red-500">*</span></label>
                        <input type="date" name="starts_at" x-model="form.starts_at" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fin <span class="text-red-500">*</span></label>
                        <input type="date" name="ends_at" x-model="form.ends_at" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
                <template x-if="!editId">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="is_current" value="0">
                        <input type="checkbox" name="is_current" value="1" x-model="form.is_current"
                               class="rounded border-gray-300 text-blue-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-gray-700">Définir comme exercice courant</span>
                    </label>
                </template>

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
