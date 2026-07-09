@extends('layouts.erp')
@section('title', 'Congés & Absences')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Congés & Absences</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-[16px] font-bold text-gray-900">Congés & Absences</h1>
    <div class="flex gap-2">
        <a href="{{ route('rh.conges.balances') }}" class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">Soldes de congés</a>
        <a href="{{ route('rh.conges.types.index') }}" class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">Types de congés</a>
        <button onclick="document.getElementById('modal-conge').classList.remove('hidden')"
                class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
            + Nouvelle demande
        </button>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" data-autosubmit class="flex flex-wrap gap-3 mb-5">
    <select name="employee_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les employés</option>
        @foreach($employees as $e)
        <option value="{{ $e->id }}" @selected(($filters['employee_id']??'')==$e->id)>{{ $e->full_name }}</option>
        @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous statuts</option>
        @foreach(\App\Models\LeaveRequest::STATUSES as $v=>$s)
        <option value="{{ $v }}" @selected(($filters['status']??'')===$v)>{{ $s['label'] }}</option>
        @endforeach
    </select>
    <select name="year" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        @foreach(range(now()->year, now()->year-3) as $y)
        <option value="{{ $y }}" @selected(($filters['year']??now()->year)==$y)>{{ $y }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3 py-1.5 bg-gray-700 text-white rounded-[4px] text-sm">Filtrer</button>
    <a href="{{ route('rh.conges.index') }}" class="px-3 py-1.5 border border-gray-300 text-gray-600 rounded-[4px] text-sm">Réinitialiser</a>
</form>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-1.5 text-left">Employé</th>
                <th class="px-3 py-1.5 text-left">Type</th>
                <th class="px-3 py-1.5 text-left">Du</th>
                <th class="px-3 py-1.5 text-left">Au</th>
                <th class="px-3 py-1.5 text-center">Jours</th>
                <th class="px-3 py-1.5 text-center">Statut</th>
                <th class="px-3 py-1.5 text-left">Motif</th>
                <th class="px-3 py-1.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($requests as $leave)
        @php $s = \App\Models\LeaveRequest::STATUSES[$leave->status] ?? ['label'=>$leave->status,'color'=>'gray']; @endphp
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-1.5 font-medium">{{ $leave->employee->full_name }}</td>
            <td class="px-3 py-1.5 text-gray-600">{{ $leave->leaveType->name }}</td>
            <td class="px-3 py-1.5">{{ $leave->start_date->format('d/m/Y') }}</td>
            <td class="px-3 py-1.5">{{ $leave->end_date->format('d/m/Y') }}</td>
            <td class="px-3 py-1.5 text-center font-semibold">{{ $leave->days }}</td>
            <td class="px-3 py-1.5 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $s['color'] }}-100 text-{{ $s['color'] }}-800">{{ $s['label'] }}</span>
            </td>
            <td class="px-3 py-1.5 text-xs text-gray-500">{{ Str::limit($leave->reason ?? '—', 40) }}</td>
            <td class="px-3 py-1.5">
                @if($leave->status === 'en_attente')
                <div class="flex gap-1">
                    <form method="POST" action="{{ route('rh.conges.approve', $leave) }}">@csrf
                        <button class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">✓</button>
                    </form>
                    <form method="POST" action="{{ route('rh.conges.refuse', $leave) }}">@csrf
                        <button class="px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600">✗</button>
                    </form>
                </div>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">Aucune demande de congé.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($requests->hasPages())
    <div class="px-3 py-1.5 border-t">{{ $requests->links() }}</div>
    @endif
</div>

{{-- Modal nouvelle demande --}}
<div id="modal-conge" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6">
        <h3 class="text-base font-semibold mb-4">Nouvelle demande de congé</h3>
        <form method="POST" action="{{ route('rh.conges.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employé *</label>
                    <select name="employee_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach($employees as $e)
                        <option value="{{ $e->id }}">{{ $e->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de congé *</label>
                    <select name="leave_type_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach($types as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->days_per_year }}j/an)</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de début *</label>
                        <input type="date" name="start_date" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin *</label>
                        <input type="date" name="end_date" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif</label>
                    <textarea name="reason" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-conge').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
