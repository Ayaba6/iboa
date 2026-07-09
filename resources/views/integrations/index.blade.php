@extends('layouts.erp')
@section('title', 'Intégrations externes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Intégrations</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Intégrations externes</h1>
            <p class="text-sm text-gray-500">Connecteurs API : paiement mobile, SMS, email, banque, fiscalité</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('integrations.dashboard') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">
                Tableau de bord
            </a>
            @can('integrations.manage')
            <a href="{{ route('integrations.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-2 rounded-lg flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle intégration
            </a>
            @endcan
        </div>
    </div>

    {{-- KPI bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @php $kpis = [
            ['label' => 'Total',         'value' => $summary['total'],      'color' => 'gray'],
            ['label' => 'Actives',        'value' => $summary['active'],     'color' => 'emerald'],
            ['label' => 'En erreur',      'value' => $summary['errors'],     'color' => $summary['errors'] > 0 ? 'red' : 'gray'],
            ['label' => "Appels aujourd'hui", 'value' => $summary['logs_today'], 'color' => 'blue'],
        ]; @endphp
        @foreach($kpis as $k)
        <div class="bg-white rounded-[4px] border border-{{ $k['color'] === 'gray' ? 'gray-200' : $k['color'].'-200' }} p-4 text-center">
            <p class="text-xs font-medium text-{{ $k['color'] === 'gray' ? 'gray-500' : $k['color'].'-600' }} uppercase">{{ $k['label'] }}</p>
            <p class="text-2xl font-bold text-{{ $k['color'] === 'gray' ? 'gray-900' : $k['color'].'-700' }} mt-1">{{ $k['value'] }}</p>
        </div>
        @endforeach
    </div>


    {{-- Grid grouped by type --}}
    @if($integrations->isEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
        <div class="w-16 h-16 rounded-[4px] bg-blue-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Aucune intégration configurée</h3>
        <p class="text-sm text-gray-500 mt-1">Connectez votre ERP aux services de paiement, SMS et plus.</p>
        @can('integrations.manage')
        <a href="{{ route('integrations.create') }}" class="inline-flex items-center gap-2 mt-4 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 py-2 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Créer la première intégration
        </a>
        @endcan
    </div>
    @else

    @foreach($integrations->groupBy('type') as $type => $group)
    @php
        $typeLabel = \App\Http\Controllers\Integrations\IntegrationController::TYPES[$type] ?? ucfirst($type);
    @endphp
    <div>
        <h2 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">{{ $typeLabel }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($group as $intg)
            @php $sc = $intg->statusColor(); @endphp
            <div class="bg-white rounded-[4px] border border-gray-300 hover:border-gray-300 transition-colors overflow-hidden">

                {{-- Header --}}
                <div class="p-4 flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center text-xl flex-shrink-0">
                        {{ $intg->typeIcon() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('integrations.show', $intg) }}"
                                   class="text-sm font-semibold text-gray-900 hover:text-blue-700 truncate block">
                                    {{ $intg->name }}
                                </a>
                                <p class="text-xs text-gray-400 truncate">{{ $intg->provider }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium
                                    @if($sc === 'emerald') bg-emerald-100 text-emerald-700
                                    @elseif($sc === 'red')  bg-red-100 text-red-700
                                    @elseif($sc === 'amber') bg-amber-100 text-amber-700
                                    @else bg-gray-100 text-gray-500 @endif">
                                    <span class="w-1.5 h-1.5 rounded-full
                                        @if($sc === 'emerald') bg-emerald-500 {{ $intg->is_active ? 'animate-pulse' : '' }}
                                        @elseif($sc === 'red') bg-red-500
                                        @elseif($sc === 'amber') bg-amber-400
                                        @else bg-gray-400 @endif inline-block"></span>
                                    {{ $intg->statusLabel() }}
                                </span>
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded
                                    @if($intg->isProduction()) bg-emerald-50 text-emerald-700
                                    @else bg-amber-50 text-amber-600 @endif">
                                    {{ $intg->isProduction() ? 'PROD' : 'SANDBOX' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stats bar --}}
                <div class="px-4 pb-3 flex items-center gap-4 text-xs text-gray-400 border-t border-gray-100 pt-3">
                    <span>{{ $intg->logs_count ?? 0 }} appels</span>
                    <span>{{ $intg->external_transactions_count ?? 0 }} tx</span>
                    @if($intg->last_success_at)
                    <span>✓ {{ $intg->last_success_at->diffForHumans() }}</span>
                    @elseif($intg->last_error_at)
                    <span class="text-red-400">⚠ {{ $intg->last_error_at->diffForHumans() }}</span>
                    @endif
                    @if(! $intg->is_active)
                    <span class="text-gray-400 ml-auto">Inactif</span>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="bg-gray-50 border-t border-gray-100 px-4 py-2.5 flex items-center gap-2">
                    <a href="{{ route('integrations.show', $intg) }}"
                       class="text-xs text-gray-600 hover:text-gray-900 font-medium">Détails</a>
                    @can('integrations.manage')
                    <span class="text-gray-200">|</span>
                    <a href="{{ route('integrations.edit', $intg) }}"
                       class="text-xs text-gray-600 hover:text-gray-900 font-medium">Modifier</a>
                    <span class="text-gray-200">|</span>
                    <form method="POST" action="{{ route('integrations.test', $intg) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Tester</button>
                    </form>
                    @if($intg->isSandbox())
                    <span class="text-gray-200">|</span>
                    <a href="{{ route('integrations.simulate', $intg) }}"
                       class="text-xs text-violet-600 hover:text-violet-800 font-medium">Simuler</a>
                    @endif
                    <div class="ml-auto">
                        <form method="POST" action="{{ route('integrations.toggle', $intg) }}" class="inline">
                            @csrf
                            <button type="submit"
                                class="text-xs font-medium {{ $intg->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }}">
                                {{ $intg->is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                        </form>
                    </div>
                    @endcan
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    @endif

</div>
@endsection
