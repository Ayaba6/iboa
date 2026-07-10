@extends('layouts.erp')
@section('title', 'Comptabilisation de la paie')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Comptabilisation paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Comptabilisation de la paie</h1>
        <p class="text-sm text-gray-500 mt-1">Générez les écritures comptables pour chaque bulletin validé</p>
    </div>
</div>

{{-- Info --}}
<div class="bg-blue-50 border border-blue-200 rounded-[4px] px-5 py-4 mb-6 flex items-start gap-3">
    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div class="text-sm text-blue-800">
        La comptabilisation génère automatiquement les journaux de paie (brut, CNSS, IUTS, net à payer).
        Elle ne peut être faite qu'une seule fois par bulletin validé.
    </div>
</div>

@if($runs->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-16 text-center">
    <div class="text-gray-400 text-lg">Aucun bulletin de paie disponible.</div>
</div>
@else
<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left font-semibold text-gray-700">Période</th>
                    <th class="px-3 py-1.5 text-center font-semibold text-gray-700">Statut</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-gray-700">Net total</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-gray-700">CNSS salarié</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-gray-700">IUTS</th>
                    <th class="px-3 py-1.5 text-center font-semibold text-gray-700">Écriture comptable</th>
                    <th class="px-3 py-1.5 text-center font-semibold text-gray-700">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($runs as $run)
                @php
                    $periodDate  = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
                    $periodLabel = ucfirst($periodDate->translatedFormat('F Y'));
                    $isValide    = in_array($run->status, ['valide', 'paye']);
                    $hasEntry    = !empty($run->journal_entry_id);
                    $statusClass = match($run->status) {
                        'brouillon' => 'bg-gray-100 text-gray-600',
                        'calcule'   => 'bg-blue-100 text-blue-700',
                        'valide'    => 'bg-green-100 text-green-700',
                        'paye'      => 'bg-emerald-100 text-emerald-800',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                    $statusLabel = match($run->status) {
                        'brouillon' => 'Brouillon',
                        'calcule'   => 'Calculé',
                        'valide'    => 'Validé',
                        'paye'      => 'Payé',
                        default     => ucfirst($run->status),
                    };
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('rh.paie.show', $run) }}" class="font-semibold text-gray-900 hover:text-emerald-700">
                            {{ $periodLabel }}
                        </a>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $run->employee_count ?? 0 }} employé(s)</div>
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono text-gray-900">
                        {{ $run->total_net ? number_format($run->total_net, 0, ',', ' ') . ' F' : '—' }}
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono text-gray-600">
                        {{ $run->total_cnss_employee ? number_format($run->total_cnss_employee, 0, ',', ' ') . ' F' : '—' }}
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono text-gray-600">
                        {{ $run->total_iuts ? number_format($run->total_iuts, 0, ',', ' ') . ' F' : '—' }}
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($hasEntry)
                            <div class="inline-flex items-center gap-1.5 text-xs text-emerald-700 font-medium">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Écriture #{{ $run->journal_entry_id }}
                            </div>
                        @elseif(!$isValide)
                            <span class="text-xs text-gray-400 italic">Bulletin non validé</span>
                        @else
                            <span class="text-xs text-amber-600 font-medium">Non comptabilisé</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($hasEntry)
                            <a href="{{ route('comptabilite.journaux.index') }}"
                               class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">
                                Voir journal
                            </a>
                        @elseif($isValide)
                            <form method="POST" action="{{ route('rh.paie.journalize', $run) }}"
                                  onsubmit="return confirm('Comptabiliser le bulletin {{ $periodLabel }} ? Cette action est définitive.')">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-xs font-medium hover:bg-emerald-800 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    Comptabiliser
                                </button>
                            </form>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($runs->hasPages())
    <div class="px-3 py-1.5 border-t border-gray-200">
        {{ $runs->links() }}
    </div>
    @endif
</div>
@endif

@if(session('success'))
<div class="fixed bottom-4 right-4 z-50 bg-emerald-500 text-white px-5 py-3 rounded-[4px] shadow-lg text-sm font-medium" x-data x-init="setTimeout(() => $el.remove(), 4000)">
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="fixed bottom-4 right-4 z-50 bg-red-500 text-white px-5 py-3 rounded-[4px] shadow-lg text-sm font-medium" x-data x-init="setTimeout(() => $el.remove(), 5000)">
    {{ session('error') }}
</div>
@endif
@endsection
