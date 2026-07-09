@extends('layouts.erp')
@section('title', 'Mes bulletins de paie')
@section('breadcrumb')
    <a href="{{ route('rh.portail.dashboard') }}" class="hover:text-gray-700">Mon Espace RH</a>
    <span class="mx-1">/</span><span>Mes bulletins</span>
@endsection

@section('content')
<h1 class="text-[16px] font-bold text-gray-900 mb-6">Mes bulletins de paie</h1>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($bulletins as $item)
    @php
        $months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                   'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $mois = $months[($item->payrollRun?->period_month ?? 1) - 1] ?? $item->payrollRun?->period_month;
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
            <div>
                <p class="font-semibold text-gray-900">{{ $mois }} {{ $item->payrollRun?->period_year }}</p>
                <p class="text-xs text-gray-400">{{ $item->worked_days ?? '—' }} / {{ $item->total_days ?? '—' }} jours</p>
            </div>
            <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium
                {{ $item->payrollRun?->status === 'paye' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                {{ $item->payrollRun?->status === 'paye' ? 'Payé' : 'Validé' }}
            </span>
        </div>
        <dl class="space-y-1 text-sm mb-4">
            <div class="flex justify-between">
                <dt class="text-gray-500">Salaire brut</dt>
                <dd class="font-mono font-medium">{{ number_format($item->salaire_brut, 0, ',', ' ') }} F</dd>
            </div>
            <div class="flex justify-between text-red-600">
                <dt>CNSS</dt>
                <dd class="font-mono">- {{ number_format($item->cnss_employee, 0, ',', ' ') }} F</dd>
            </div>
            <div class="flex justify-between text-purple-600">
                <dt>IUTS</dt>
                <dd class="font-mono">- {{ number_format($item->iuts_amount, 0, ',', ' ') }} F</dd>
            </div>
            <div class="flex justify-between font-semibold text-emerald-700 border-t pt-1">
                <dt>Net à payer</dt>
                <dd class="font-mono">{{ number_format($item->salaire_net, 0, ',', ' ') }} F</dd>
            </div>
        </dl>
        <a href="{{ route('rh.portail.bulletin-pdf', $item) }}"
           class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[#eef5f0] text-emerald-800 rounded-[4px] text-sm font-medium hover:bg-emerald-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Télécharger le bulletin
        </a>
    </div>
    @empty
    <div class="col-span-3 py-16 text-center text-gray-400">
        Aucun bulletin disponible.
    </div>
    @endforelse
</div>

@if($bulletins->hasPages())
    <div class="mt-6">{{ $bulletins->links() }}</div>
@endif
@endsection
