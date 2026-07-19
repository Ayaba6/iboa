@extends('layouts.erp')
@section('title', 'CRM — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">CRM</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ══ Barre titre + actions (design system X3) ═════════════════════════ --}}
    <x-x3.title-bar title="CRM — Tableau de bord"
                    subtitle="Pipeline commercial, activités et contacts — {{ now()->translatedFormat('F Y') }}">
        <x-x3.btn variant="primary" :href="route('crm.opportunities.create')">+ Nouvelle opportunité</x-x3.btn>
        <x-x3.btn :href="route('crm.contacts.create')">Nouveau contact</x-x3.btn>
    </x-x3.title-bar>

    {{-- ══ Synthèse KPIs ═════════════════════════════════════════════════════ --}}
    <x-x3.synthesis cols="5">
        <x-x3.stat label="Contacts" :value="number_format($totalContacts, 0, ',', ' ')"
                   sub="+{{ $newThisMonth }} ce mois" :href="route('crm.contacts.index')" />
        <x-x3.stat label="Opportunités ouvertes" color="blue" :value="number_format($openOpps, 0, ',', ' ')"
                   sub="en cours" :href="route('crm.opportunities.index')" />
        <x-x3.stat label="Pipeline brut" color="blue" :value="number_format($pipeline, 0, ',', ' ')" unit="FCFA"
                   sub="pondéré : {{ number_format($weightedPipeline, 0, ',', ' ') }} FCFA" />
        <x-x3.stat label="Gagné ce mois" color="emerald" :value="number_format($wonThisMonth, 0, ',', ' ')" unit="FCFA"
                   :sub="now()->translatedFormat('F Y')" />
        <x-x3.stat label="Activités en retard" :color="$overdueActivities > 0 ? 'red' : 'gray'"
                   :value="number_format($overdueActivities, 0, ',', ' ')"
                   :sub="$overdueActivities > 0 ? 'à traiter' : 'à jour'"
                   :href="route('crm.activities.index', ['status' => 'pending'])" />
    </x-x3.synthesis>

    {{-- ══ 1. Pipeline par étape ═════════════════════════════════════════════ --}}
    @php
        $totalOpps   = collect($stageStats)->sum('count');
        $totalAmount = collect($stageStats)->sum('amount');
    @endphp
    <x-x3.section number="1" title="Pipeline par étape" flush>
        <x-slot:meta>{{ $totalOpps }} opportunité(s)</x-slot:meta>
        <table class="w-full table-fixed text-[12.5px]">
            <thead>
                <tr class="bg-band/70">
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
                <tr class="border-b border-gray-50 even:bg-gray-50/40 hover:bg-band/40 transition-colors">
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
    </x-x3.section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- ══ 2. Activités à faire ══════════════════════════════════════════ --}}
        <x-x3.section number="2" title="Activités à faire" flush>
            <x-slot:meta>
                <a href="{{ route('crm.activities.index', ['status' => 'pending']) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Voir tout →</a>
            </x-slot:meta>
            @if($pendingActivities->isEmpty())
            <div class="px-4 py-6 text-center text-sm text-gray-400">Aucune activité en attente.</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($pendingActivities as $act)
                <li class="flex items-start gap-3 px-4 py-2 hover:bg-band/40 transition-colors text-[12.5px]">
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
        </x-x3.section>

        {{-- ══ 3. Top opportunités ═══════════════════════════════════════════ --}}
        <x-x3.section number="3" title="Top opportunités" flush>
            <x-slot:meta>
                <a href="{{ route('crm.opportunities.index') }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Pipeline →</a>
            </x-slot:meta>
            @if($topOpps->isEmpty())
            <div class="px-4 py-6 text-center text-sm text-gray-400">Aucune opportunité ouverte.</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($topOpps as $opp)
                <li class="flex items-center gap-3 px-4 py-2 hover:bg-band/40 transition-colors text-[12.5px]">
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
        </x-x3.section>

    </div>

    {{-- ══ 4. Contacts récents ═══════════════════════════════════════════════ --}}
    <x-x3.section number="4" title="Contacts récents" flush>
        <x-slot:meta>
            <a href="{{ route('crm.contacts.index') }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Tous les contacts →</a>
        </x-slot:meta>
        @if($recentContacts->isEmpty())
        <div class="px-4 py-6 text-center text-sm text-gray-400">Aucun contact enregistré.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentContacts as $c)
            <div class="flex items-center gap-3 px-4 py-2 hover:bg-band/40 transition-colors text-[12.5px]">
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
    </x-x3.section>

    {{-- ══ Footer contexte ═══════════════════════════════════════════════════ --}}
    <x-x3.footer module="CRM — Tableau de bord">
        <span>Période : <strong class="text-white">{{ now()->translatedFormat('F Y') }}</strong></span>
    </x-x3.footer>

</div>
@endsection
