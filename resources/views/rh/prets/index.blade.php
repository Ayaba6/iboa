@extends('layouts.erp')
@section('title', 'Prêts salariés')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Prêts salariés</span>
@endsection

@section('content')
<div x-data="{ showModal: false }">

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Prêts salariés</h1>
        <p class="text-sm text-gray-500 mt-1">Remboursements sur plusieurs mois — différents des avances ponctuelles.</p>
    </div>
    <button @click="showModal = true"
            class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouveau prêt
    </button>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-[4px] text-emerald-700 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-[4px] text-red-700 text-sm">{{ session('error') }}</div>
@endif

{{-- Filtres --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="employee_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les employés</option>
        @foreach($employees as $emp)
            <option value="{{ $emp->id }}" @selected(request('employee_id') == $emp->id)>
                {{ $emp->last_name }} {{ $emp->first_name }} ({{ $emp->matricule }})
            </option>
        @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les statuts</option>
        <option value="actif"      @selected(request('status') === 'actif')>Actif</option>
        <option value="rembourse"  @selected(request('status') === 'rembourse')>Remboursé</option>
        <option value="annule"     @selected(request('status') === 'annule')>Annulé</option>
    </select>
    <button type="submit" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-[4px] text-sm hover:bg-gray-200">Filtrer</button>
    @if(request()->hasAny(['employee_id','status']))
        <a href="{{ route('rh.prets.index') }}" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Réinitialiser</a>
    @endif
</form>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">N° Prêt</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase">Mensualité</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase">Solde restant</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase">Progression</th>
                    <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase">Statut</th>
                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Début</th>
                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($loans as $loan)
                @php
                    $pct = $loan->amount > 0 ? round(($loan->amount - $loan->remaining_balance) / $loan->amount * 100) : 0;
                    $statusColors = ['actif'=>'bg-blue-100 text-blue-700','rembourse'=>'bg-emerald-100 text-emerald-700','annule'=>'bg-red-100 text-red-700'];
                    $statusLabels = ['actif'=>'Actif','rembourse'=>'Remboursé','annule'=>'Annulé'];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5">
                        <code class="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $loan->loan_number }}</code>
                    </td>
                    <td class="px-3 py-1.5">
                        <a href="{{ route('rh.employes.show', $loan->employee) }}" class="font-medium text-gray-800 hover:text-emerald-700">
                            {{ $loan->employee->full_name }}
                        </a>
                        <div class="text-xs text-gray-400">{{ $loan->employee->department?->name }}</div>
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono">{{ number_format($loan->amount, 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-right font-mono">{{ number_format($loan->monthly_deduction, 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-right font-mono {{ $loan->remaining_balance > 0 ? 'text-orange-600 font-semibold' : 'text-emerald-600' }}">
                        {{ number_format($loan->remaining_balance, 0, ',', ' ') }} F
                    </td>
                    <td class="px-3 py-1.5">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                        <div class="text-xs text-gray-400 text-center mt-0.5">{{ $pct }} %</div>
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusColors[$loan->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusLabels[$loan->status] ?? $loan->status }}
                        </span>
                    </td>
                    <td class="px-3 py-1.5 text-gray-600">{{ \Carbon\Carbon::parse($loan->start_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 text-right">
                        <a href="{{ route('rh.prets.show', $loan) }}"
                           class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">
                            Détail →
                        </a>
                    </td>
                </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-gray-400">Aucun prêt trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($loans->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $loans->links() }}</div>
    @endif
</div>

{{-- Modal nouveau prêt --}}
<div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-lg p-6" @click.outside="showModal = false">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nouveau prêt salarié</h3>
        <form method="POST" action="{{ route('rh.prets.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employé <span class="text-red-500">*</span></label>
                    <select name="employee_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        <option value="">— Sélectionner —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->last_name }} {{ $emp->first_name }} ({{ $emp->matricule }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant total (FCFA)</label>
                        <input type="number" name="amount" min="1000" step="1000" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensualité (FCFA)</label>
                        <input type="number" name="monthly_deduction" min="1000" step="500" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nb mensualités</label>
                        <input type="number" name="nb_months" min="1" max="60" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                        <input type="date" name="start_date" value="{{ now()->format('Y-m-d') }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif du prêt</label>
                    <input type="text" name="reason" maxlength="500"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showModal = false"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
                        Enregistrer le prêt
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
