@extends('layouts.erp')
@section('title', 'Mon Espace RH')
@section('breadcrumb')
    <span>Mon Espace RH</span>
@endsection

@section('content')
<div class="mb-6">
    <div class="flex items-center gap-4">
        @if($employee->photo_path)
            <img src="{{ route('rh.employes.photo', $employee) }}"
                 class="w-16 h-16 rounded-full object-cover border-2 border-emerald-200 shadow-sm" alt="Photo">
        @else
            <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center border-2 border-emerald-200">
                <span class="text-[16px] font-bold text-emerald-500">{{ strtoupper(substr($employee->last_name,0,1)) }}</span>
            </div>
        @endif
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Bonjour, {{ $employee->first_name }} !</h1>
            <p class="text-sm text-gray-500">
                {{ $employee->job_title ?? 'Employé(e)' }} · {{ $employee->department?->name ?? '' }}
                · Matricule <span class="font-mono">{{ $employee->matricule }}</span>
            </p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    {{-- Salaire de base --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Salaire de base</p>
        <p class="text-[17px] font-bold text-gray-900 font-mono mt-1">
            {{ $employee->activeContract ? number_format($employee->activeContract->base_salary, 0, ',', ' ') . ' F' : '—' }}
        </p>
        <p class="text-xs text-gray-400 mt-0.5">{{ $employee->activeContract?->type ?? 'Aucun contrat actif' }}</p>
    </div>

    {{-- Dernier net à payer --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Dernier net à payer</p>
        @if($lastBulletin)
            <p class="text-[17px] font-bold text-emerald-700 font-mono mt-1">
                {{ number_format($lastBulletin->salaire_net, 0, ',', ' ') }} F
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $lastBulletin->payrollRun?->period_month }}/{{ $lastBulletin->payrollRun?->period_year }}
            </p>
        @else
            <p class="text-[17px] font-bold text-gray-400 mt-1">—</p>
        @endif
    </div>

    {{-- Ancienneté --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Ancienneté</p>
        <p class="text-[17px] font-bold text-gray-900 mt-1">{{ $employee->anciennete }} an(s)</p>
        <p class="text-xs text-gray-400 mt-0.5">Depuis le {{ $employee->hiring_date?->format('d/m/Y') ?? '—' }}</p>
    </div>

    {{-- Parts fiscales --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Parts fiscales IUTS</p>
        <p class="text-[17px] font-bold text-gray-900 mt-1">{{ number_format($employee->nb_parts, 1) }} parts</p>
        <p class="text-xs text-gray-400 mt-0.5">
            {{ ['celibataire'=>'Célibataire','marie'=>'Marié(e)','veuf'=>'Veuf/Veuve','divorce'=>'Divorcé(e)'][$employee->family_status] ?? '—' }}
            · {{ $employee->nb_children }} enfant(s)
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- Soldes congés --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Soldes de congés</h2>
            <a href="{{ route('rh.portail.conges') }}" class="text-xs text-emerald-700 hover:underline">Gérer →</a>
        </div>
        @if($leaveBalances->isNotEmpty())
            <div class="space-y-2">
                @foreach($leaveBalances as $balance)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">{{ $balance->leaveType?->name ?? '—' }}</span>
                    <span class="font-semibold text-sm {{ $balance->balance > 0 ? 'text-emerald-700' : 'text-red-600' }}">
                        {{ number_format($balance->balance, 1) }} j
                    </span>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">Aucun solde enregistré.</p>
        @endif
    </div>

    {{-- Demandes récentes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Dernières demandes de congé</h2>
            <a href="{{ route('rh.portail.conges') }}" class="text-xs text-emerald-700 hover:underline">Tout voir →</a>
        </div>
        @if($recentLeaves->isNotEmpty())
            <div class="space-y-2">
                @foreach($recentLeaves as $leave)
                @php
                    $statusColors = ['en_attente'=>'amber','approuve'=>'emerald','refuse'=>'red','annule'=>'gray'];
                    $statusLabels = ['en_attente'=>'En attente','approuve'=>'Approuvé','refuse'=>'Refusé','annule'=>'Annulé'];
                    $c = $statusColors[$leave->status] ?? 'gray';
                @endphp
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">
                        {{ $leave->leaveType?->name ?? '—' }}
                        <span class="text-xs text-gray-400">
                            {{ \Carbon\Carbon::parse($leave->start_date)->format('d/m') }}
                            – {{ \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y') }}
                        </span>
                    </span>
                    <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                        {{ $statusLabels[$leave->status] ?? $leave->status }}
                    </span>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">Aucune demande.</p>
        @endif
    </div>

    {{-- Bulletins de paie --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Mes bulletins de paie</h2>
            <a href="{{ route('rh.portail.bulletins') }}" class="text-xs text-emerald-700 hover:underline">Tout voir →</a>
        </div>
        @if($lastBulletin)
            <div class="p-3 bg-[#eef5f0] rounded-[4px] border border-emerald-100">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium text-emerald-900">
                            {{ $lastBulletin->payrollRun?->period_month }}/{{ $lastBulletin->payrollRun?->period_year }}
                        </p>
                        <p class="text-xs text-emerald-700 mt-0.5">
                            Brut : {{ number_format($lastBulletin->salaire_brut, 0, ',', ' ') }} F
                            · Net : {{ number_format($lastBulletin->salaire_net, 0, ',', ' ') }} F
                        </p>
                    </div>
                    <a href="{{ route('rh.portail.bulletin-pdf', $lastBulletin) }}"
                       class="text-xs bg-emerald-700 text-white px-3 py-1 rounded-[4px] hover:bg-emerald-800">
                        Télécharger
                    </a>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400">Aucun bulletin disponible.</p>
        @endif
    </div>

    {{-- Prêt actif --}}
    @if($activeLoan)
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Mon prêt en cours</h2>
        @php $pct = $activeLoan->amount > 0 ? round(($activeLoan->amount - $activeLoan->remaining_balance) / $activeLoan->amount * 100) : 0; @endphp
        <div class="space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Montant accordé</span>
                <span class="font-mono font-semibold">{{ number_format($activeLoan->amount, 0, ',', ' ') }} F</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Solde restant</span>
                <span class="font-mono font-semibold text-orange-600">{{ number_format($activeLoan->remaining_balance, 0, ',', ' ') }} F</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Mensualité</span>
                <span class="font-mono">{{ number_format($activeLoan->monthly_deduction, 0, ',', ' ') }} F</span>
            </div>
            <div class="mt-2">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>Remboursé</span><span>{{ $pct }} %</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-emerald-600 h-2 rounded-full" style="width:{{ $pct }}%"></div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
