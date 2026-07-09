@extends('layouts.erp')
@section('title', 'Mes congés')
@section('breadcrumb')
    <a href="{{ route('rh.portail.dashboard') }}" class="hover:text-gray-700">Mon Espace RH</a>
    <span class="mx-1">/</span><span>Mes congés</span>
@endsection

@section('content')
<div x-data="{ showModal: false }">

<div class="flex items-center justify-between mb-6">
    <h1 class="text-[16px] font-bold text-gray-900">Mes congés</h1>
    <button @click="showModal = true"
            class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
        + Demande de congé
    </button>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-[4px] text-emerald-700 text-sm">{{ session('success') }}</div>
@endif

{{-- Soldes --}}
@if($leaveBalances->isNotEmpty())
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    @foreach($leaveBalances as $balance)
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">{{ $balance->leaveType?->name }}</p>
        <p class="text-[16px] font-bold {{ $balance->balance > 0 ? 'text-emerald-700' : 'text-red-600' }}">
            {{ number_format($balance->balance, 1) }}
        </p>
        <p class="text-xs text-gray-400">jours</p>
    </div>
    @endforeach
</div>
@endif

{{-- Historique --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Historique des demandes</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500">Type</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500">Du</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500">Au</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500">Jours</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500">Motif</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($leaveRequests as $leave)
                @php
                    $statusColors = ['en_attente'=>'amber','approuve'=>'emerald','refuse'=>'red','annule'=>'gray'];
                    $statusLabels = ['en_attente'=>'En attente','approuve'=>'Approuvé','refuse'=>'Refusé','annule'=>'Annulé'];
                    $c = $statusColors[$leave->status] ?? 'gray';
                    $days = \Carbon\Carbon::parse($leave->start_date)->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1;
                @endphp
                <tr>
                    <td class="px-3 py-1.5 font-medium">{{ $leave->leaveType?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 text-center">{{ $days }}</td>
                    <td class="px-3 py-1.5 text-xs text-gray-500 truncate max-w-[200px]">{{ $leave->reason ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                            {{ $statusLabels[$leave->status] ?? $leave->status }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Aucune demande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leaveRequests->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $leaveRequests->links() }}</div>
    @endif
</div>

{{-- Modal nouvelle demande --}}
<div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6" @click.outside="showModal = false">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nouvelle demande de congé</h3>
        <form method="POST" action="{{ route('rh.portail.conges.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de congé</label>
                    <select name="leave_type_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        <option value="">— Sélectionner —</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                        <input type="date" name="start_date" required min="{{ now()->format('Y-m-d') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                        <input type="date" name="end_date" required min="{{ now()->format('Y-m-d') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif (optionnel)</label>
                    <textarea name="reason" rows="2" maxlength="500"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showModal = false"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                        Soumettre
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
