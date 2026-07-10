@extends('layouts.erp')
@section('title', 'CRM — ' . $contact->name)

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.contacts.index') }}" class="hover:text-gray-700">Contacts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $contact->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-{{ $contact->typeColor() }}-100 flex items-center justify-center flex-shrink-0">
                <span class="text-[17px] font-bold text-{{ $contact->typeColor() }}-700">{{ $contact->initials() }}</span>
            </div>
            <div>
                <h1 class="text-[16px] font-bold text-gray-900">{{ $contact->name }}</h1>
                @if($contact->job_title || $contact->company_name)
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $contact->job_title }}{{ ($contact->job_title && $contact->company_name) ? ' · ' : '' }}{{ $contact->company_name }}
                </p>
                @endif
                <div class="flex items-center gap-2 mt-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $contact->typeColor() }}-100 text-{{ $contact->typeColor() }}-700">{{ $contact->typeLabel() }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $contact->statusColor() }}-100 text-{{ $contact->statusColor() }}-700">{{ $contact->statusLabel() }}</span>
                    @if($contact->score > 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-700">Score : {{ $contact->score }}/100</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('crm.activities.create', ['contact_id' => $contact->id]) }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Ajouter activité
            </a>
            <a href="{{ route('crm.opportunities.create', ['contact_id' => $contact->id]) }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Nouvelle opportunité
            </a>

            {{-- Conversion → Client ERP --}}
            @if($contact->client_id)
                <a href="{{ route('clients.show', $contact->client_id) }}"
                   class="inline-flex items-center gap-2 px-3 py-2.5 bg-emerald-50 border border-emerald-300 text-emerald-700 rounded-[4px] text-sm font-medium hover:bg-emerald-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Voir le client
                </a>
            @else
                <form method="POST" action="{{ route('crm.contacts.convert', $contact) }}"
                      onsubmit="return confirm('Convertir « {{ $contact->name }} » en client ERP ?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2.5 bg-white border border-emerald-400 text-emerald-700 rounded-[4px] text-sm font-medium hover:bg-emerald-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Convertir en client
                    </button>
                </form>
            @endif

            <a href="{{ route('crm.contacts.edit', $contact) }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                Modifier
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Infos principales --}}
        <div class="lg:col-span-1 space-y-4">

            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Coordonnées</h3>
                <dl class="space-y-2.5 text-sm">
                    @if($contact->email)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-14 flex-shrink-0 text-xs pt-0.5">Email</dt>
                        <dd><a href="mailto:{{ $contact->email }}" class="text-emerald-700 hover:text-emerald-800">{{ $contact->email }}</a></dd>
                    </div>
                    @endif
                    @if($contact->phone)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-14 flex-shrink-0 text-xs pt-0.5">Tél</dt>
                        <dd class="text-gray-800">{{ $contact->phone }}</dd>
                    </div>
                    @endif
                    @if($contact->mobile)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-14 flex-shrink-0 text-xs pt-0.5">Mobile</dt>
                        <dd class="text-gray-800">{{ $contact->mobile }}</dd>
                    </div>
                    @endif
                    @if($contact->website)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-14 flex-shrink-0 text-xs pt-0.5">Web</dt>
                        <dd><a href="{{ $contact->website }}" target="_blank" rel="noopener" class="text-emerald-700 hover:text-emerald-800 truncate block max-w-[180px]">{{ $contact->website }}</a></dd>
                    </div>
                    @endif
                    @if($contact->city || $contact->address)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-14 flex-shrink-0 text-xs pt-0.5">Adresse</dt>
                        <dd class="text-gray-800">{{ implode(', ', array_filter([$contact->address, $contact->city, $contact->country])) }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">CRM</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-20 flex-shrink-0 text-xs pt-0.5">Source</dt>
                        <dd class="text-gray-800">{{ $contact->sourceLabel() }}</dd>
                    </div>
                    @if($contact->sector)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-20 flex-shrink-0 text-xs pt-0.5">Secteur</dt>
                        <dd class="text-gray-800">{{ $contact->sector }}</dd>
                    </div>
                    @endif
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-20 flex-shrink-0 text-xs pt-0.5">Responsable</dt>
                        <dd class="text-gray-800">{{ $contact->user?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-20 flex-shrink-0 text-xs pt-0.5">Créé le</dt>
                        <dd class="text-gray-800">{{ $contact->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
                @if(!empty($contact->tags))
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($contact->tags as $tag)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            @if($contact->notes)
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $contact->notes }}</p>
            </div>
            @endif

        </div>

        {{-- Opportunités + Activités --}}
        <div class="lg:col-span-2 space-y-3">

            {{-- Opportunités --}}
            <div class="bg-white rounded-[4px] border border-gray-300">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Opportunités ({{ $opps->count() }})</h3>
                    <a href="{{ route('crm.opportunities.create', ['contact_id' => $contact->id]) }}"
                       class="text-xs text-emerald-700 hover:text-emerald-800 font-medium">+ Ajouter</a>
                </div>
                @if($opps->isEmpty())
                <div class="px-5 py-6 text-center text-sm text-gray-400">Aucune opportunité</div>
                @else
                <ul class="divide-y divide-gray-50">
                    @foreach($opps as $opp)
                    <li class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="w-2 h-2 rounded-full bg-{{ $opp->stageColor() }}-400 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('crm.opportunities.show', $opp) }}"
                               class="text-sm font-medium text-gray-800 hover:text-emerald-700 block truncate">{{ $opp->title }}</a>
                            <p class="text-xs text-gray-400">{{ $opp->stageLabel() }} · {{ $opp->probability }}%</p>
                        </div>
                        <div class="text-sm font-semibold text-gray-900 flex-shrink-0">
                            {{ number_format($opp->amount, 0, ',', ' ') }} F
                        </div>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>

            {{-- Activités --}}
            <div class="bg-white rounded-[4px] border border-gray-300">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Activités ({{ $activities->count() }})</h3>
                    <a href="{{ route('crm.activities.create', ['contact_id' => $contact->id]) }}"
                       class="text-xs text-emerald-700 hover:text-emerald-800 font-medium">+ Ajouter</a>
                </div>
                @if($activities->isEmpty())
                <div class="px-5 py-6 text-center text-sm text-gray-400">Aucune activité enregistrée</div>
                @else
                <ul class="divide-y divide-gray-50">
                    @foreach($activities as $act)
                    <li class="flex items-start gap-3 px-5 py-3 {{ $act->is_done ? 'opacity-60' : '' }} hover:bg-gray-50 transition-colors">
                        <span class="text-lg flex-shrink-0 mt-0.5">{{ $act->typeIcon() }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 {{ $act->is_done ? 'line-through' : '' }} truncate">{{ $act->subject }}</p>
                            @if($act->description)
                            <p class="text-xs text-gray-400 truncate">{{ $act->description }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-0.5">{{ $act->user?->name ?? '—' }}</p>
                        </div>
                        <div class="flex-shrink-0 text-right space-y-1">
                            @if($act->due_at)
                            <p class="text-xs {{ $act->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                {{ $act->due_at->format('d/m/Y') }}
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
