@extends('layouts.erp')
@section('title', 'CRM — ' . $opportunity->title)

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.opportunities.index') }}" class="hover:text-gray-700">Pipeline</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ Str::limit($opportunity->title, 40) }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">{{ $opportunity->stageConfig()['icon'] }}</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-semibold bg-{{ $opportunity->stageColor() }}-100 text-{{ $opportunity->stageColor() }}-700">
                    {{ $opportunity->stageLabel() }}
                </span>
            </div>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $opportunity->title }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ number_format($opportunity->amount, 0, ',', ' ') }} FCFA · {{ $opportunity->probability }}% de probabilité
                @if($opportunity->expected_close)
                    · Clôture : {{ $opportunity->expected_close->format('d/m/Y') }}
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('crm.activities.create', ['opportunity_id' => $opportunity->id]) }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                + Activité
            </a>

            {{-- Créer un devis ERP depuis cette opportunité --}}
            @php $clientId = $opportunity->contact?->client_id; @endphp
            @can('quotes.create')
            @if($clientId)
                {{-- Contact converti → pré-sélectionner le client dans le formulaire devis --}}
                <a href="{{ route('ventes.devis.create', ['client_id' => $clientId]) }}"
                   class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-violet-300 text-violet-700 rounded-[4px] text-sm font-medium hover:bg-violet-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Créer un devis
                </a>
            @else
                {{-- Contact non converti — inviter à convertir d'abord --}}
                @if($opportunity->contact)
                <a href="{{ route('crm.contacts.show', $opportunity->contact) }}"
                   class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-amber-300 text-amber-700 rounded-[4px] text-sm font-medium hover:bg-amber-50 transition-colors"
                   title="Convertir d'abord le contact en client pour créer un devis">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Convertir le contact d'abord
                </a>
                @endif
                <a href="{{ route('ventes.devis.create') }}"
                   class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-violet-300 text-violet-700 rounded-[4px] text-sm font-medium hover:bg-violet-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Créer un devis
                </a>
            @endif
            @endcan

            <a href="{{ route('crm.opportunities.edit', $opportunity) }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                Modifier
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Détails --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Détails</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Montant</dt>
                        <dd class="font-semibold text-gray-900">{{ number_format($opportunity->amount, 0, ',', ' ') }} FCFA</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Pondéré</dt>
                        <dd class="font-semibold text-emerald-700">{{ number_format($opportunity->weightedAmount(), 0, ',', ' ') }} FCFA</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Probabilité</dt>
                        <dd class="text-gray-800">{{ $opportunity->probability }} %</dd>
                    </div>
                    @if($opportunity->expected_close)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Clôture</dt>
                        <dd class="text-gray-800">{{ $opportunity->expected_close->format('d/m/Y') }}</dd>
                    </div>
                    @endif
                    @if($opportunity->product_service)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Produit</dt>
                        <dd class="text-gray-800">{{ $opportunity->product_service }}</dd>
                    </div>
                    @endif
                    @if($opportunity->lost_reason)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Raison perte</dt>
                        <dd class="text-red-600">{{ $opportunity->lost_reason }}</dd>
                    </div>
                    @endif
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Commercial</dt>
                        <dd class="text-gray-800">{{ $opportunity->user?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Contact</dt>
                        <dd>
                            @if($opportunity->contact)
                            <a href="{{ route('crm.contacts.show', $opportunity->contact) }}"
                               class="text-emerald-700 hover:text-emerald-800">{{ $opportunity->contact->name }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-24 flex-shrink-0 text-xs pt-0.5">Créée le</dt>
                        <dd class="text-gray-800">{{ $opportunity->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>

            @if($opportunity->notes)
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $opportunity->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Activités --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-[4px] border border-gray-300">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Activités ({{ $opportunity->activities->count() }})</h3>
                    <a href="{{ route('crm.activities.create', ['opportunity_id' => $opportunity->id]) }}"
                       class="text-xs text-emerald-700 hover:text-emerald-800 font-medium">+ Ajouter</a>
                </div>
                @if($opportunity->activities->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-gray-400">Aucune activité enregistrée</div>
                @else
                <ul class="divide-y divide-gray-50">
                    @foreach($opportunity->activities->sortByDesc('created_at') as $act)
                    <li class="flex items-start gap-3 px-5 py-3 {{ $act->is_done ? 'opacity-60' : '' }} hover:bg-gray-50 transition-colors">
                        <span class="text-lg flex-shrink-0 mt-0.5">{{ $act->typeIcon() }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 {{ $act->is_done ? 'line-through' : '' }}">{{ $act->subject }}</p>
                            @if($act->description)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $act->description }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-0.5">{{ $act->user?->name ?? '—' }}</p>
                        </div>
                        <div class="flex-shrink-0 text-right space-y-1">
                            @if($act->due_at)
                            <p class="text-xs {{ $act->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                {{ $act->due_at->format('d/m/Y H:i') }}
                            </p>
                            @endif
                            @if(!$act->is_done)
                            <form method="POST" action="{{ route('crm.activities.toggle-done', $act) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">✓ Fait</button>
                            </form>
                            @else
                            <span class="text-xs text-gray-400">✓ Fait</span>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
