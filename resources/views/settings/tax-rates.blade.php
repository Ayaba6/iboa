@extends('layouts.erp')
@section('title', 'Taux de TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Taux de TVA</span>
@endsection

@section('content')
@php
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
@endphp
<div x-data="{
    modal: '',
    form: { name: '', short_name: '', rate: '', type: 'tva', collected_account_id: '', deductible_account_id: '', is_default: false, is_active: true },
    editId: null,
    openCreate() {
        this.form = { name: '', short_name: '', rate: '', type: 'tva', collected_account_id: '', deductible_account_id: '', is_default: false, is_active: true };
        this.editId = null; this.modal = 'form';
    },
    openEdit(id, name, short_name, rate, type, collected, deductible, isActive) {
        this.form = { name, short_name, rate, type: type || 'tva', collected_account_id: collected || '', deductible_account_id: deductible || '', is_default: false, is_active: isActive };
        this.editId = id; this.modal = 'form';
    },
}" class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Taux de TVA & retenues</h1>
            <p class="text-[11.5px] text-gray-500">{{ $taxRates->count() }} taux configurés — appliqués aux articles et documents de vente/achat</p>
        </div>
        @can('settings.manage')
        <button @click="openCreate()"
                class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">+ Nouveau taux</button>
        @endcan
    </div>

    {{-- Table SAGE --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left">Libellé</th>
                <th class="{{ $th }} text-left w-28">Code court</th>
                <th class="{{ $th }} text-left w-36">Type</th>
                <th class="{{ $th }} text-right w-24">Taux</th>
                <th class="{{ $th }} text-center w-24">Par défaut</th>
                <th class="{{ $th }} text-center w-20">Statut</th>
                <th class="{{ $th }} text-right">Simulation (HT 10 000)</th>
                <th class="{{ $th }} text-right w-52">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($taxRates as $tax)
                @php
                    $taxAmount = round(10000 * $tax->rate / 100);
                    $isRetenue = $tax->type === 'retenue';
                    $ttc = $isRetenue ? 10000 - $taxAmount : 10000 + $taxAmount;
                @endphp
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 {{ $tax->is_default ? '!bg-emerald-50/70' : '' }}">
                    <td class="px-3 py-1.5 font-semibold text-gray-800">
                        {{ $tax->name }}
                        @if($tax->is_default)
                        <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded-full text-[10.5px] font-semibold bg-emerald-100 text-emerald-800 align-middle">Défaut</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[12px] text-gray-600">{{ $tax->short_name }}</td>
                    <td class="px-3 py-1.5">
                        @if($isRetenue)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">Retenue source</span>
                        @else
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">TVA</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-[13px] font-bold {{ $tax->rate == 0 ? 'text-gray-400' : 'text-gray-900' }}">{{ number_format($tax->rate, 2, ',', '') }} %</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($tax->is_default)
                        <svg class="w-4 h-4 text-emerald-600 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tax->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $tax->is_active ? 'Actif' : 'Inactif' }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono text-[11.5px] tabular-nums whitespace-nowrap">
                        @if($isRetenue)
                        <span class="text-amber-700">Ret. -{{ number_format($taxAmount, 0, ',', ' ') }}</span>
                        <span class="text-gray-300 mx-0.5">→</span>
                        <span class="font-semibold text-gray-800">Net {{ number_format($ttc, 0, ',', ' ') }}</span>
                        @else
                        <span class="text-gray-600">TVA {{ number_format($taxAmount, 0, ',', ' ') }}</span>
                        <span class="text-gray-300 mx-0.5">→</span>
                        <span class="font-semibold text-gray-800">TTC {{ number_format($ttc, 0, ',', ' ') }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        @can('settings.manage')
                        @if(!$tax->is_default)
                        <form method="POST" action="{{ route('settings.tax-rates.set-default', $tax) }}" class="inline">@csrf
                            <button type="submit" class="text-[12px] font-medium text-gray-500 hover:text-emerald-700 hover:underline">Par défaut</button>
                        </form>
                        <span class="text-gray-300 mx-1">|</span>
                        @endif
                        <button @click="openEdit({{ $tax->id }}, '{{ addslashes($tax->name) }}', '{{ addslashes($tax->short_name) }}', '{{ $tax->rate }}', '{{ $tax->type }}', {{ $tax->collected_account_id ?? 'null' }}, {{ $tax->deductible_account_id ?? 'null' }}, {{ $tax->is_active ? 'true' : 'false' }})"
                                class="text-[12px] font-semibold text-emerald-700 hover:underline">Modifier</button>
                        @if(!$tax->is_default)
                        <span class="text-gray-300 mx-1">|</span>
                        <form method="POST" action="{{ route('settings.tax-rates.destroy', $tax) }}" class="inline"
                              onsubmit="return confirm('Supprimer le taux {{ addslashes($tax->name) }} ?')">@csrf @method('DELETE')
                            <button type="submit" class="text-[12px] font-medium text-red-400 hover:text-red-600 hover:underline">Supprimer</button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-16 text-center text-gray-400">Aucun taux de TVA configuré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-[11.5px] text-gray-400 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Le taux par défaut est appliqué automatiquement aux nouveaux articles. Un taux à 0 % représente une opération exonérée ou hors taxe.
    </p>

    {{-- Modal --}}
    <div x-show="modal === 'form'" x-transition x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-200 bg-gradient-to-b from-[#eef5f0] to-white">
                <h3 class="text-[15px] font-bold text-emerald-900" x-text="editId ? 'Modifier le taux' : 'Nouveau taux de TVA'"></h3>
            </div>

            <form :method="'POST'"
                  :action="editId ? `/parametres/taux-tva/${editId}` : '{{ route('settings.tax-rates.store') }}'"
                  class="p-4 space-y-3">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Libellé <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required maxlength="50" class="{{ $inp }}" placeholder="TVA 18%">
                    </div>

                    {{-- Type de taxe --}}
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label @click="form.type = 'tva'"
                                   :class="form.type === 'tva' ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-400' : 'border-gray-200 hover:border-emerald-300'"
                                   class="cursor-pointer border rounded-[3px] p-2.5 transition-all select-none">
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="type" value="tva" x-model="form.type" class="sr-only">
                                    <span :class="form.type === 'tva' ? 'bg-emerald-600 border-emerald-600' : 'border-gray-300'"
                                          class="w-4 h-4 rounded-full border-2 flex-shrink-0 flex items-center justify-center">
                                        <span x-show="form.type === 'tva'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                    </span>
                                    <div>
                                        <p class="text-[13px] font-semibold text-gray-800">TVA</p>
                                        <p class="text-[11px] text-gray-500">Collectée — ajoutée au TTC</p>
                                    </div>
                                </div>
                            </label>
                            <label @click="form.type = 'retenue'"
                                   :class="form.type === 'retenue' ? 'border-amber-400 bg-amber-50 ring-1 ring-amber-300' : 'border-gray-200 hover:border-amber-200'"
                                   class="cursor-pointer border rounded-[3px] p-2.5 transition-all select-none">
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="type" value="retenue" x-model="form.type" class="sr-only">
                                    <span :class="form.type === 'retenue' ? 'bg-amber-600 border-amber-600' : 'border-gray-300'"
                                          class="w-4 h-4 rounded-full border-2 flex-shrink-0 flex items-center justify-center">
                                        <span x-show="form.type === 'retenue'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                    </span>
                                    <div>
                                        <p class="text-[13px] font-semibold text-gray-800">Retenue source</p>
                                        <p class="text-[11px] text-gray-500">BIC, AIB… déduite du net</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Code court <span class="text-red-500">*</span></label>
                        <input type="text" name="short_name" x-model="form.short_name" required maxlength="10" class="{{ $inp }} font-mono" placeholder="TVA18">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Taux (%) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" name="rate" x-model="form.rate" required min="0" max="100" step="0.01"
                                   class="{{ $inp }} pr-7 text-right font-mono" placeholder="18.00">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[12px]">%</span>
                        </div>
                    </div>
                </div>

                {{-- Comptes GL associés (visible uniquement pour les TVA) --}}
                <div x-show="form.type === 'tva'" class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
                    <div>
                        <label class="{{ $lbl }}">Compte TVA collectée <span class="font-normal text-gray-400">(44xx)</span></label>
                        <select name="collected_account_id" x-model="form.collected_account_id" class="{{ $inp }} font-mono text-[12px]">
                            <option value="">— Par défaut (4431) —</option>
                            @foreach($tvaAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Compte TVA déductible <span class="font-normal text-gray-400">(44xx)</span></label>
                        <select name="deductible_account_id" x-model="form.deductible_account_id" class="{{ $inp }} font-mono text-[12px]">
                            <option value="">— Par défaut (4455) —</option>
                            @foreach($tvaAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Live simulation --}}
                <div class="bg-[#f7faf8] border border-gray-200 rounded-[3px] p-2.5" x-show="form.rate !== ''">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Simulation sur 10 000 FCFA HT</p>
                    <template x-if="form.type === 'tva'">
                        <div class="flex items-center gap-3 font-mono text-[12.5px] tabular-nums">
                            <span class="text-gray-600">TVA : <strong class="text-gray-900" x-text="Math.round(10000 * form.rate / 100).toLocaleString('fr-FR')"></strong></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600">TTC : <strong class="text-emerald-800" x-text="(10000 + Math.round(10000 * form.rate / 100)).toLocaleString('fr-FR')"></strong></span>
                        </div>
                    </template>
                    <template x-if="form.type === 'retenue'">
                        <div class="flex items-center gap-3 font-mono text-[12.5px] tabular-nums">
                            <span class="text-amber-700">Retenue : <strong x-text="'-' + Math.round(10000 * form.rate / 100).toLocaleString('fr-FR')"></strong></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600">Net : <strong class="text-gray-900" x-text="(10000 - Math.round(10000 * form.rate / 100)).toLocaleString('fr-FR')"></strong></span>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active"
                               class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Actif</span>
                    </label>
                    <template x-if="!editId">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" value="1" x-model="form.is_default"
                                   class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-[13px] font-medium text-gray-700">Taux par défaut</span>
                        </label>
                    </template>
                </div>

                <div class="flex gap-2 justify-end pt-2 border-t border-gray-100">
                    <button type="button" @click="modal = ''"
                            class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</button>
                    <button type="submit"
                            class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors"
                            x-text="editId ? 'Enregistrer' : 'Créer'"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">Paramètres — Taux de TVA</strong></span>
            <span>Taux configurés : <strong class="text-white">{{ $taxRates->count() }}</strong> ({{ $taxRates->where('is_active', true)->count() }} actifs)</span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
