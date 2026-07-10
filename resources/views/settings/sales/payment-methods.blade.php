@extends('layouts.erp')
@section('title', 'Modes de règlement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('settings.sales.hub') }}" class="hover:text-gray-700">Paramétrage Vente</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modes de règlement</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp
<div class="space-y-4"
     x-data="{
        modal: false, editId: null,
        form: { name: '', code: '', type: '', cash_account_id: '', journal_code: '', requires_reference: false, attachment_required: false, is_active: true },
        openCreate() { this.form = { name: '', code: '', type: '', cash_account_id: '', journal_code: '', requires_reference: false, attachment_required: false, is_active: true }; this.editId = null; this.modal = true; },
        openEdit(m) { this.form = { ...m, requires_reference: !!m.requires_reference, attachment_required: !!m.attachment_required, is_active: !!m.is_active }; this.editId = m.id; this.modal = true; },
     }">

    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Modes de règlement</h1>
            <p class="text-[11.5px] text-gray-500">Espèces, chèque, virement, Mobile Money… — compte de trésorerie et journal liés</p>
        </div>
        <button @click="openCreate()" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">+ Nouveau mode</button>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3 py-2.5 rounded-[4px] text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left w-24">Code</th>
                <th class="{{ $th }} text-left">Intitulé</th>
                <th class="{{ $th }} text-left">Compte de trésorerie</th>
                <th class="{{ $th }} text-left w-24">Journal</th>
                <th class="{{ $th }} text-center w-24">Référence</th>
                <th class="{{ $th }} text-center w-24">PJ oblig.</th>
                <th class="{{ $th }} text-center w-20">Statut</th>
                <th class="{{ $th }} text-right w-24">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($methods as $m)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1.5 font-mono font-semibold text-emerald-800">{{ $m->code }}</td>
                    <td class="px-3 py-1.5 font-semibold text-gray-800">{{ $m->name }}
                        @if($m->is_mobile_money)<span class="ml-1 inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 align-middle">mobile</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $cashAccounts->firstWhere('id', $m->cash_account_id)?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 font-mono text-gray-500">{{ $m->journal_code ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center">{{ $m->requires_reference ? '✓' : '—' }}</td>
                    <td class="px-3 py-1.5 text-center">{{ $m->attachment_required ? '✓' : '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $m->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $m->is_active ? 'Actif' : 'Inactif' }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        <button @click="openEdit(@js($m->only(['id','name','code','type','cash_account_id','journal_code','requires_reference','attachment_required','is_active'])))"
                                class="text-[12px] font-semibold text-emerald-700 hover:underline">Modifier</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-16 text-center text-gray-400">Aucun mode de règlement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-show="modal" x-cloak x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-xl w-full max-w-md overflow-hidden" @click.outside="modal = false">
            <div class="px-3 py-2.5 border-b border-gray-200 bg-gradient-to-b from-[#eef5f0] to-white">
                <h3 class="text-[15px] font-bold text-emerald-900" x-text="editId ? 'Modifier le mode' : 'Nouveau mode de règlement'"></h3>
            </div>
            <form :action="editId ? '{{ url('parametres/modes-reglement') }}/' + editId : '{{ route('settings.payment-methods.store') }}'" method="POST" class="p-4 space-y-3">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $lbl }}">Code <span class="text-red-500">*</span></label>
                        <input type="text" name="code" x-model="form.code" required maxlength="20" class="{{ $inp }} font-mono uppercase">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Intitulé <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required maxlength="60" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Compte de trésorerie</label>
                        <select name="cash_account_id" x-model="form.cash_account_id" class="{{ $inp }}">
                            <option value="">— Aucun —</option>
                            @foreach($cashAccounts as $ca)<option value="{{ $ca->id }}">{{ $ca->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Journal comptable</label>
                        <input type="text" name="journal_code" x-model="form.journal_code" maxlength="10" placeholder="BQ, CA…" class="{{ $inp }} font-mono uppercase">
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="requires_reference" value="0">
                        <input type="checkbox" name="requires_reference" value="1" x-model="form.requires_reference" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Référence obligatoire</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="attachment_required" value="0">
                        <input type="checkbox" name="attachment_required" value="1" x-model="form.attachment_required" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Pièce jointe obligatoire</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Actif</span>
                    </label>
                </div>
                <div class="flex gap-2 justify-end pt-2 border-t border-gray-100">
                    <button type="button" @click="modal = false" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</button>
                    <button type="submit" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors" x-text="editId ? 'Enregistrer' : 'Créer'"></button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
