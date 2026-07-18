@extends('layouts.erp')
@section('title', 'CRM — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">CRM</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">CRM — Tableau de bord</h1>
            <p class="text-xs text-gray-400 mt-0.5">Pipeline commercial, activités et contacts — {{ now()->translatedFormat('F Y') }}</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('crm.opportunities.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                + Nouvelle opportunité
            </a>
            <a href="{{ route('crm.contacts.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                Nouveau contact
            </a>
        </div>
    </div>

    {{-- ══ Synthèse KPIs (pattern X3 : barre grid divide-x) ═════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 lg:grid-cols-5 divide-x divide-gray-200">
        <a href="{{ route('crm.contacts.index') }}" class="p-3 text-center hover:bg-[#eef5f0]/40 transition-colors">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Contacts</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-gray-900 mt-0.5">{{ number_format($totalContacts, 0, ',', ' ') }}</p>
            <p class="text-[10px] text-gray-400">+{{ $newThisMonth }} ce mois</p>
        </a>
        <a href="{{ route('crm.opportunities.index') }}" class="p-3 text-center hover:bg-[#eef5f0]/40 transition-colors">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Opportunités ouvertes</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ number_format($openOpps, 0, ',', ' ') }}</p>
            <p class="text-[10px] text-gray-400">en cours</p>
        </a>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Pipeline brut</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ number_format($pipeline, 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
            <p class="text-[10px] text-gray-400">pondéré : {{ number_format($weightedPipeline, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Gagné ce mois</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-emerald-700 mt-0.5">{{ number_format($wonThisMonth, 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
            <p class="text-[10px] text-gray-400">{{ now()->translatedFormat('F Y') }}</p>
        </div>
        <a href="{{ route('crm.activities.index', ['status' => 'pending']) }}" class="p-3 text-center hover:bg-[#eef5f0]/40 transition-colors">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Activités en retard</p>
            <p class="text-[15px] font-bold font-mono tabular-nums {{ $overdueActivities > 0 ? 'text-red-600' : 'text-gray-800' }} mt-0.5">{{ number_format($overdueActivities, 0, ',', ' ') }}</p>
            <p class="text-[10px] {{ $overdueActivities > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $overdueActivities > 0 ? 'à traiter' : 'à jour' }}</p>
        </a>
    </div>

    {{-- ══ 1. Pipeline par étape ═════════════════════════════════════════════ --}}
    @php
        $totalOpps   = collect($stageStats)->sum('count');
        $totalAmount = collect($stageStats)->sum('amount');
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">1. Pipeline par étape</p>
            <p class="text-[11px] text-emerald-600">{{ $totalOpps }} opportunité(s)</p>
        </div>
        <table class="w-full table-fixed text-[12.5px]">
            <thead>
                <tr class="bg-[#eef5f0]/70">
                    <th class="w-[34%] px-4 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Étape</th>
                    <th class="w-[12%] px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Nb</th>
                    <th class="w-[22%] px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                    <th class="w-[12%] px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Prob. std</th>
                    <th class="w-[20%] px-4 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Part montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stageStats as $stage => $stat)
                @php $cfg = $stat['config']; $part = $totalAmount > 0 ? $stat['amount'] / $totalAmount * 100 : 0; @endphp
                <tr class="border-b border-gray-50 even:bg-gray-50/40 hover:bg-[#eef5f0]/40 transition-colors">
                    <td class="px-4 py-1.5">
                        <a href="{{ route('crm.opportunities.index') }}#stage-{{ $stage }}" class="font-medium {{ $stage === 'perdu' ? 'text-red-600' : ($stage === 'gagne' ? 'text-emerald-700' : 'text-gray-800') }} hover:underline">
                            {{ $cfg['label'] }}
                        </a>
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums font-semibold">{{ $stat['count'] }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums {{ $stage === 'perdu' ? 'text-red-600' : 'text-blue-700' }} font-semibold">{{ number_format($stat['amount'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-500">{{ $cfg['prob'] }} %</td>
                    <td class="px-4 py-1.5">
                        <div class="flex items-center gap-2 justify-end">
                            <div class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $stage === 'perdu' ? 'bg-red-400' : 'bg-emerald-600' }}" style="width: {{ round($part) }}%"></div>
                            </div>
                            <span class="font-mono tabular-nums text-[11px] text-gray-500 w-10 text-right">{{ number_format($part, 0) }} %</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- ══ 2. Activités à faire ══════════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">2. Activités à faire</p>
                <a href="{{ route('crm.activities.index', ['status' => 'pending']) }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Voir tout →</a>
            </div>
            @if($pendingActivities->isEmpty())
            <div class="px-4 py-6 text-center text-sm text-gray-400">Aucune activité en attente.</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($pendingActivities as $act)
                <li class="flex items-start gap-3 px-4 py-2 hover:bg-[#eef5f0]/40 transition-colors text-[12.5px]">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 truncate" title="{{ $act->subject }}">{{ $act->subject }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $act->typeLabel() }} · {{ $act->contact?->name ?? $act->opportunity?->title ?? '—' }}</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        @if($act->isOverdue())
                            <span class="inline-flex px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-red-100 text-red-700">En retard</span>
                        @elseif($act->due_at)
                            <span class="text-xs text-gray-400">{{ $act->due_at->format('d/m/Y') }}</span>
                        @endif
                        <form method="POST" action="{{ route('crm.activities.toggle-done', $act) }}" class="mt-0.5">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">✓ Fait</button>
                        </form>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- ══ 3. Top opportunités ═══════════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">3. Top opportunités</p>
                <a href="{{ route('crm.opportunities.index') }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Pipeline →</a>
            </div>
            @if($topOpps->isEmpty())
            <div class="px-4 py-6 text-center text-sm text-gray-400">Aucune opportunité ouverte.</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($topOpps as $opp)
                <li class="flex items-center gap-3 px-4 py-2 hover:bg-[#eef5f0]/40 transition-colors text-[12.5px]">
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('crm.opportunities.show', $opp) }}"
                           class="font-medium text-gray-800 hover:text-emerald-700 truncate block" title="{{ $opp->title }}">{{ $opp->title }}</a>
                        <p class="text-xs text-gray-500 truncate">{{ $opp->contact?->name ?? '—' }} · {{ $opp->stageLabel() }}</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <p class="font-mono tabular-nums font-semibold text-blue-700">{{ number_format($opp->amount, 0, ',', ' ') }}</p>
                        <p class="text-xs text-gray-400 font-mono tabular-nums">{{ $opp->probability }} %</p>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

    </div>

    {{-- ══ 4. Contacts récents ═══════════════════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">4. Contacts récents</p>
            <a href="{{ route('crm.contacts.index') }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Tous les contacts →</a>
        </div>
        @if($recentContacts->isEmpty())
        <div class="px-4 py-6 text-center text-sm text-gray-400">Aucun contact enregistré.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentContacts as $c)
            <div class="flex items-center gap-3 px-4 py-2 hover:bg-[#eef5f0]/40 transition-colors text-[12.5px]">
                <div class="flex-1 min-w-0">
                    <a href="{{ route('crm.contacts.show', $c) }}"
                       class="font-medium text-gray-800 hover:text-emerald-700 truncate block">{{ $c->name }}</a>
                    <p class="text-xs text-gray-500 truncate">{{ $c->company_name ?? $c->email ?? '—' }}</p>
                </div>
                <div class="flex-shrink-0 flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-gray-100 text-gray-700">{{ $c->typeLabel() }}</span>
                    <span class="text-xs text-gray-400">{{ $c->created_at->format('d/m/Y') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">CRM — Tableau de bord</strong></span>
            <span>Période : <strong class="text-white">{{ now()->translatedFormat('F Y') }}</strong></span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
