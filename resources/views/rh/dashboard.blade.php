@extends('layouts.erp')
@section('title', 'Dashboard RH')
@section('breadcrumb')
    <span>RH / Paie</span><span class="mx-1">/</span><span>Tableau de bord</span>
@endsection

@section('content')
<div class="space-y-3">

{{-- ── KPIs ──────────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach([
        ['Effectif actif',    $totalActif,    'bg-blue-600',    'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['Suspendus',         $totalSuspendu, 'bg-yellow-500',  'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ['Sorties (cumul)',   $totalSorties,  'bg-red-500',     'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
        ['Bulletins/an',      $bulletinsAnnee, 'bg-emerald-700', 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
    ] as [$label, $val, $color, $icon])
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 flex items-center gap-4">
        <div class="w-12 h-12 {{ $color }} rounded-[4px] flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
            </svg>
        </div>
        <div>
            <div class="text-[16px] font-bold text-gray-900">{{ $val }}</div>
            <div class="text-xs text-gray-500">{{ $label }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Masse salariale (dernier bulletin) ───────────────────────────────── --}}
@if($lastRun)
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach([
        ['Masse salariale brute', number_format($lastRun->total_brut, 0, ',', ' ').' F', 'text-gray-900'],
        ['CNSS salarial',         number_format($lastRun->total_cnss_employee, 0, ',', ' ').' F', 'text-red-600'],
        ['CNSS patronal',         number_format($lastRun->total_cnss_employer, 0, ',', ' ').' F', 'text-amber-600'],
        ['IUTS',                  number_format($lastRun->total_iuts, 0, ',', ' ').' F', 'text-purple-600'],
        ['Total net',             number_format($lastRun->total_net, 0, ',', ' ').' F', 'text-green-700'],
        ['Coût total employeur',  number_format($lastRun->total_brut + $lastRun->total_cnss_employer, 0, ',', ' ').' F', 'text-emerald-800'],
    ] as [$l, $v, $c])
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="text-xs text-gray-500 mb-1">{{ $l }}</div>
        <div class="text-[16px] font-bold {{ $c }} font-mono">{{ $v }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ $lastRun->period_label }}</div>
    </div>
    @endforeach
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Évolution masse salariale ──────────────────────────────────────── --}}
    <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Évolution masse salariale brute (12 derniers mois)</h3>
        @if($evolution->count())
        <div class="flex items-end gap-2 h-40">
            @php $maxBrut = $evolution->max('total_brut') ?: 1; @endphp
            @foreach($evolution as $r)
            @php $pct = round($r->total_brut / $maxBrut * 100); @endphp
            <div class="flex-1 flex flex-col items-center gap-1 min-w-0">
                <div class="text-xs text-gray-500 truncate" style="font-size:9px">{{ number_format($r->total_brut/1000,0,'','') }}k</div>
                <div class="w-full bg-emerald-700 rounded-t-sm" style="height:{{ max(4,$pct*0.9) }}px; max-height:120px"></div>
                <div class="text-xs text-gray-500 truncate" style="font-size:9px">{{ str_pad($r->period_month,2,'0',STR_PAD_LEFT).'/'.substr($r->period_year,2,2) }}</div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400 text-center py-8">Aucun bulletin validé.</p>
        @endif
    </div>

    {{-- ── Répartition par catégorie ──────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Effectifs par catégorie</h3>
        <div class="space-y-3">
            @php
            $cats = ['cadre'=>'Cadre','agent_maitrise'=>'Agent maîtrise','employe'=>'Employé','ouvrier'=>'Ouvrier'];
            $total = $byCategory->sum() ?: 1;
            $colors = ['cadre'=>'bg-emerald-600','agent_maitrise'=>'bg-blue-500','employe'=>'bg-emerald-500','ouvrier'=>'bg-amber-500'];
            @endphp
            @foreach($cats as $key=>$label)
            @php $nb = $byCategory[$key] ?? 0; $pct = round($nb/$total*100); @endphp
            <div>
                <div class="flex justify-between text-xs text-gray-600 mb-1">
                    <span>{{ $label }}</span><span class="font-semibold">{{ $nb }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full">
                    <div class="{{ $colors[$key] }} h-2 rounded-full" style="width:{{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- ── Répartition par département ─────────────────────────────────────── --}}
    @if(count($byDept))
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Masse salariale par département — {{ $lastRun?->period_label }}</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-1.5 text-left">Département</th>
                    <th class="px-3 py-1.5 text-center">Effectif</th>
                    <th class="px-3 py-1.5 text-right">Brut</th>
                    <th class="px-3 py-1.5 text-right">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            @foreach($byDept as $d)
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5 font-medium">{{ $d->department_name ?: '—' }}</td>
                <td class="px-3 py-1.5 text-center text-gray-600">{{ $d->nb }}</td>
                <td class="px-3 py-1.5 text-right font-mono text-xs">{{ number_format($d->brut, 0, ',', ' ') }}</td>
                <td class="px-3 py-1.5 text-right font-mono text-xs text-green-700">{{ number_format($d->net, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ── Congés en attente ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Congés en attente</h3>
            <a href="{{ route('rh.conges.index') }}" class="text-xs text-emerald-700 hover:underline">Voir tout</a>
        </div>
        @forelse($pendingLeaves as $leave)
        <div class="px-5 py-3 border-b border-gray-50 flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-900">{{ $leave->employee->full_name }}</div>
                <div class="text-xs text-gray-500">{{ $leave->leaveType->name }} · {{ $leave->start_date->format('d/m') }} → {{ $leave->end_date->format('d/m/Y') }} · <strong>{{ $leave->days }}j</strong></div>
            </div>
            <form method="POST" action="{{ route('rh.conges.approve', $leave) }}">
                @csrf
                <button class="px-3 py-1 bg-green-600 text-white text-xs rounded-[4px] hover:bg-green-700">Approuver</button>
            </form>
        </div>
        @empty
        <p class="px-5 py-6 text-sm text-gray-400 text-center">Aucun congé en attente.</p>
        @endforelse
    </div>

</div>

{{-- ── Avances en attente ───────────────────────────────────────────────── --}}
@if($pendingAdvances->count())
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Avances en attente d'approbation</h3>
        <a href="{{ route('rh.avances.index') }}" class="text-xs text-emerald-700 hover:underline">Voir tout</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-1.5 text-left">Employé</th>
                <th class="px-3 py-1.5 text-right">Montant</th>
                <th class="px-3 py-1.5 text-left">Date</th>
                <th class="px-3 py-1.5 text-left">Motif</th>
                <th class="px-3 py-1.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        @foreach($pendingAdvances as $adv)
        <tr>
            <td class="px-3 py-1.5 font-medium">{{ $adv->employee->full_name }}</td>
            <td class="px-3 py-1.5 text-right font-mono text-emerald-800">{{ number_format($adv->amount, 0, ',', ' ') }} F</td>
            <td class="px-3 py-1.5 text-gray-500">{{ $adv->advance_date->format('d/m/Y') }}</td>
            <td class="px-3 py-1.5 text-gray-600 text-xs">{{ $adv->reason ?? '—' }}</td>
            <td class="px-3 py-1.5">
                <form method="POST" action="{{ route('rh.avances.approve', $adv) }}">@csrf
                    <button class="px-3 py-1 bg-emerald-700 text-white text-xs rounded-[4px] hover:bg-emerald-800">Approuver</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Actions rapides ─────────────────────────────────────────────────── --}}
<div class="flex flex-wrap gap-3">
    <a href="{{ route('rh.employes.create') }}" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Nouvel employé
    </a>
    <a href="{{ route('rh.paie.create') }}" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        Nouveau bulletin
    </a>
    <a href="{{ route('rh.paie.livre-paie', ['year' => now()->year]) }}" class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-700 text-white rounded-[4px] text-sm font-medium hover:bg-gray-800">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Livre de paie {{ now()->year }}
    </a>
    <a href="{{ route('rh.conges.index') }}" class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50">
        Congés & Absences
    </a>
    <a href="{{ route('rh.avances.index') }}" class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50">
        Avances sur salaire
    </a>
</div>

</div>
@endsection
