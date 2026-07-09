@extends('layouts.erp')
@section('title', 'CRM — Contacts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Contacts</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <x-ui.page-header
        title="Contacts & Prospects"
        subtitle="{{ $contacts->total() }} contact(s)"
        icon="👤"
        :backUrl="false">
        <x-slot:actions>
            <x-ui.btn href="{{ route('crm.contacts.create') }}" variant="primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau contact
            </x-ui.btn>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filtres --}}
    <x-ui.card :padding="false">
        <form method="GET" class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Nom, société, email, tél..."
                       class="input lg:col-span-2">
                <select name="type" class="select">
                    <option value="">Tous les types</option>
                    @foreach(\App\Models\CrmContact::TYPES as $k => $v)
                        <option value="{{ $k }}" {{ ($filters['type'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                <select name="status" class="select">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Models\CrmContact::STATUSES as $k => $v)
                        <option value="{{ $k }}" {{ ($filters['status'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary flex-1">Filtrer</button>
                    @if(array_filter($filters ?? []))
                    <a href="{{ route('crm.contacts.index') }}" class="btn btn-secondary">✕</a>
                    @endif
                </div>
            </div>
        </form>
    </x-ui.card>

    {{-- Table --}}
    <x-ui.card :padding="false">
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Contact</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Société</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Statut</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Source</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Responsable</th>
                        <th class="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Créé le</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contacts as $c)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-{{ $c->typeColor() }}-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-{{ $c->typeColor() }}-700">{{ $c->initials() }}</span>
                                </div>
                                <div>
                                    <a href="{{ route('crm.contacts.show', $c) }}"
                                       class="font-medium text-gray-900 hover:text-emerald-700 transition-colors">{{ $c->name }}</a>
                                    @if($c->job_title)
                                    <p class="text-xs text-gray-400">{{ $c->job_title }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-sm text-gray-700">{{ $c->company_name ?? '—' }}</td>
                        <td class="px-3 py-1.5">
                            <x-ui.badge :color="$c->typeColor()" dot="false">{{ $c->typeLabel() }}</x-ui.badge>
                        </td>
                        <td class="px-3 py-1.5">
                            <x-ui.badge :color="$c->statusColor()" dot="false">{{ $c->statusLabel() }}</x-ui.badge>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $c->sourceLabel() }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $c->user?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $c->created_at->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center gap-1 justify-end">
                                <a href="{{ route('crm.contacts.show', $c) }}"
                                   class="p-1.5 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-[4px] transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('crm.contacts.edit', $c) }}"
                                   class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-[4px] transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <x-ui.empty
                                icon="👤"
                                title="Aucun contact trouvé"
                                message="Commencez par ajouter vos premiers contacts et prospects."
                                action="Nouveau contact"
                                href="{{ route('crm.contacts.create') }}" />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($contacts->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $contacts->links() }}
        </div>
        @endif
    </x-ui.card>
</div>
@endsection
