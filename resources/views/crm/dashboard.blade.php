@extends('layouts.erp')
@section('title', 'CRM — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">CRM</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">CRM — Tableau de bord</h1>
            <p class="text-sm text-gray-500 mt-0.5">Vue d'ensemble de votre pipeline commercial</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('crm.contacts.create') }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Nouveau contact
            </a>
            <a href="{{ route('crm.opportunities.create') }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle opportunité
            </a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <x-ui.stat
            label="Contacts"
            value="{{ number_format($totalContacts) }}"
            sub="+{{ $newThisMonth }} ce mois"
            icon="👥"
            color="blue"
            href="{{ route('crm.contacts.index') }}" />

        <x-ui.stat
            label="Opportunités ouvertes"
            value="{{ number_format($openOpps) }}"
            sub="en cours"
            icon="💡"
            color="violet"
            href="{{ route('crm.opportunities.index') }}" />

        <x-ui.stat
            label="Pipeline total"
            value="{{ number_format($pipeline, 0, ',', ' ') }} F"
            sub="valeur pondérée"
            icon="📊"
            color="indigo" />

        <x-ui.stat
            label="Gagné ce mois"
            value="{{ number_format($wonThisMonth, 0, ',', ' ') }} F"
            sub="{{ now()->translatedFormat('F Y') }}"
            icon="🏆"
            color="emerald" />

        <x-ui.stat
            label="Activités en retard"
            value="{{ number_format($overdueActivities) }}"
            sub="{{ $overdueActivities > 0 ? 'à traiter' : 'Tout est à jour ✓' }}"
            icon="⏰"
            color="{{ $overdueActivities > 0 ? 'red' : 'gray' }}" />
    </div>

    {{-- Pipeline par stage --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Pipeline par étape</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($stageStats as $stage => $stat)
            @php $cfg = $stat['config']; @endphp
            <a href="{{ route('crm.opportunities.index') }}#stage-{{ $stage }}"
               class="flex flex-col items-center p-3 rounded-[4px] border border-{{ $cfg['color'] }}-100 bg-{{ $cfg['color'] }}-50 hover:bg-{{ $cfg['color'] }}-100 transition-colors text-center group">
                <span class="text-2xl mb-1">{{ $cfg['icon'] }}</span>
                <span class="text-xs font-medium text-{{ $cfg['color'] }}-700 truncate w-full">{{ $cfg['label'] }}</span>
                <span class="text-[16px] font-bold text-{{ $cfg['color'] }}-800 mt-1">{{ $stat['count'] }}</span>
                <span class="text-xs text-{{ $cfg['color'] }}-600 mt-0.5">{{ number_format($stat['amount'], 0, ',', ' ') }} F</span>
            </a>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Activités à faire --}}
        <div class="bg-white rounded-[4px] border border-gray-300">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Activités à faire</h2>
                <a href="{{ route('crm.activities.index', ['status' => 'pending']) }}"
                   class="text-xs text-emerald-700 hover:text-emerald-800 font-medium">Voir tout →</a>
            </div>
            @if($pendingActivities->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-400">Aucune activité en attente 🎉</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($pendingActivities as $act)
                <li class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                    <span class="text-lg flex-shrink-0 mt-0.5">{{ $act->typeIcon() }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $act->subject }}</p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ $act->contact?->name ?? $act->opportunity?->title ?? '—' }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        @if($act->isOverdue())
                            <span class="text-xs font-medium text-red-600">En retard</span>
                        @elseif($act->due_at)
                            <span class="text-xs text-gray-400">{{ $act->due_at->diffForHumans() }}</span>
                        @endif
                        <form method="POST" action="{{ route('crm.activities.toggle-done', $act) }}" class="mt-1">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">✓ Fait</button>
                        </form>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- Top opportunités --}}
        <div class="bg-white rounded-[4px] border border-gray-300">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Top opportunités</h2>
                <a href="{{ route('crm.opportunities.index') }}"
                   class="text-xs text-emerald-700 hover:text-emerald-800 font-medium">Pipeline →</a>
            </div>
            @if($topOpps->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-400">Aucune opportunité ouverte</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($topOpps as $opp)
                <li class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="w-2 h-2 rounded-full bg-{{ $opp->stageColor() }}-400 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('crm.opportunities.show', $opp) }}"
                           class="text-sm font-medium text-gray-800 hover:text-emerald-700 truncate block">{{ $opp->title }}</a>
                        <p class="text-xs text-gray-500 truncate">{{ $opp->contact?->name ?? '—' }} · {{ $opp->stageLabel() }}</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <p class="text-sm font-semibold text-gray-900">{{ number_format($opp->amount, 0, ',', ' ') }} F</p>
                        <p class="text-xs text-gray-400">{{ $opp->probability }} %</p>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

    </div>

    {{-- Derniers contacts --}}
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Contacts récents</h2>
            <a href="{{ route('crm.contacts.index') }}"
               class="text-xs text-emerald-700 hover:text-emerald-800 font-medium">Tous les contacts →</a>
        </div>
        @if($recentContacts->isEmpty())
        <div class="px-5 py-8 text-center text-sm text-gray-400">Aucun contact enregistré</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentContacts as $c)
            <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                <div class="w-9 h-9 rounded-full bg-{{ $c->typeColor() }}-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-{{ $c->typeColor() }}-700">{{ $c->initials() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('crm.contacts.show', $c) }}"
                       class="text-sm font-medium text-gray-800 hover:text-emerald-700 truncate block">{{ $c->name }}</a>
                    <p class="text-xs text-gray-500 truncate">{{ $c->company_name ?? $c->email ?? '—' }}</p>
                </div>
                <div class="flex-shrink-0 flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $c->typeColor() }}-100 text-{{ $c->typeColor() }}-700">
                        {{ $c->typeLabel() }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $c->created_at->diffForHumans() }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
