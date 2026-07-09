@extends('layouts.erp')
@section('title', 'Avances sur salaire')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Avances sur salaire</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-[16px] font-bold text-gray-900">Avances sur salaire</h1>
    <button onclick="document.getElementById('modal-avance').classList.remove('hidden')"
            class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
        + Nouvelle avance
    </button>
</div>

{{-- Filtres --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="employee_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les employés</option>
        @foreach($employees as $e)
        <option value="{{ $e->id }}" @selected(($filters['employee_id']??'')==$e->id)>{{ $e->full_name }}</option>
        @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous statuts</option>
        @foreach(\App\Models\SalaryAdvance::STATUSES as $v=>$s)
        <option value="{{ $v }}" @selected(($filters['status']??'')===$v)>{{ $s['label'] }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3 py-1.5 bg-gray-700 text-white rounded-[4px] text-sm">Filtrer</button>
    <a href="{{ route('rh.avances.index') }}" class="px-3 py-1.5 border border-gray-300 text-gray-600 rounded-[4px] text-sm">Réinitialiser</a>
</form>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-1.5 text-left">Employé</th>
                <th class="px-3 py-1.5 text-right">Montant</th>
                <th class="px-3 py-1.5 text-left">Date</th>
                <th class="px-3 py-1.5 text-left">Motif</th>
                <th class="px-3 py-1.5 text-center">Statut</th>
                <th class="px-3 py-1.5 text-left">Remboursement</th>
                <th class="px-3 py-1.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($advances as $adv)
        @php $s = \App\Models\SalaryAdvance::STATUSES[$adv->status] ?? ['label'=>$adv->status,'color'=>'gray']; @endphp
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-1.5 font-medium">{{ $adv->employee->full_name }}</td>
            <td class="px-3 py-1.5 text-right font-mono font-semibold text-emerald-800">{{ number_format($adv->amount, 0, ',', ' ') }} F</td>
            <td class="px-3 py-1.5 text-gray-600">{{ $adv->advance_date->format('d/m/Y') }}</td>
            <td class="px-3 py-1.5 text-gray-600 text-xs">{{ $adv->reason ?? '—' }}</td>
            <td class="px-3 py-1.5 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $s['color'] }}-100 text-{{ $s['color'] }}-800">{{ $s['label'] }}</span>
            </td>
            <td class="px-3 py-1.5 text-xs text-gray-500">
                @if($adv->status === 'rembourse')
                    {{ $adv->recoveredIn?->period_label ?? '—' }}<br>
                    <span class="text-gray-400">{{ $adv->recovered_at?->format('d/m/Y') }}</span>
                @else —
                @endif
            </td>
            <td class="px-3 py-1.5">
                <div class="flex gap-2">
                @if($adv->status === 'en_attente')
                    <form method="POST" action="{{ route('rh.avances.approve', $adv) }}">@csrf
                        <button class="px-3 py-1 bg-emerald-700 text-white text-xs rounded hover:bg-emerald-800">Approuver</button>
                    </form>
                    <form method="POST" action="{{ route('rh.avances.cancel', $adv) }}">@csrf
                        <button class="px-3 py-1 border border-gray-300 text-gray-600 text-xs rounded hover:bg-gray-50">Annuler</button>
                    </form>
                @elseif($adv->status === 'approuve')
                    <form method="POST" action="{{ route('rh.avances.cancel', $adv) }}">@csrf
                        <button class="px-3 py-1 border border-red-300 text-red-600 text-xs rounded hover:bg-red-50">Annuler</button>
                    </form>
                @endif
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Aucune avance enregistrée.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($advances->hasPages())
    <div class="px-3 py-1.5 border-t">{{ $advances->links() }}</div>
    @endif
</div>

{{-- Modal nouvelle avance --}}
<div id="modal-avance" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6">
        <h3 class="text-base font-semibold mb-4">Nouvelle avance sur salaire</h3>
        <form method="POST" action="{{ route('rh.avances.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employé <span class="text-red-500">*</span></label>
                    <select name="employee_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach($employees as $e)
                        <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->matricule }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) *</label>
                        <input type="number" name="amount" min="1000" step="500" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="advance_date" value="{{ date('Y-m-d') }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif</label>
                    <input type="text" name="reason" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-avance').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
