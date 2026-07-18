@extends('layouts.erp')
@section('title', 'CRM — Activités')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Activités</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Activités</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $activities->total() }} activité(s)</p>
        </div>
        <a href="{{ route('crm.activities.create') }}"
           class="inline-flex items-center gap-2 px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors self-start">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle activité
        </a>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <select name="type" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les types</option>
                @foreach(\App\Models\CrmActivity::TYPES as $k => $v)
                    <option value="{{ $k }}" {{ ($filters['type'] ?? '') === $k ? 'selected' : '' }}>{{ $v['icon'] }} {{ $v['label'] }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>À faire</option>
                <option value="overdue" {{ ($filters['status'] ?? '') === 'overdue' ? 'selected' : '' }}>En retard</option>
                <option value="done"    {{ ($filters['status'] ?? '') === 'done'    ? 'selected' : '' }}>Faits</option>
            </select>
            <select name="user_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les responsables</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">Filtrer</button>
                @if(array_filter($filters ?? []))
                <a href="{{ route('crm.activities.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600 w-8"></th>
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600">Activité</th>
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600">Contact / Opportunité</th>
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600">Type</th>
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600">Priorité</th>
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600">Échéance</th>
                        <th class="text-left px-3 py-1.5 font-semibold text-gray-600">Responsable</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($activities as $act)
                    <tr class="hover:bg-gray-50 transition-colors {{ $act->is_done ? 'opacity-60' : '' }}">
                        <td class="px-3 py-1.5">
                            <form method="POST" action="{{ route('crm.activities.toggle-done', $act) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors
                                               {{ $act->is_done ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 hover:border-emerald-400' }}"
                                        title="{{ $act->is_done ? 'Marquer à faire' : 'Marquer comme fait' }}">
                                    @if($act->is_done)
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="px-3 py-1.5">
                            <p class="font-medium text-gray-900 {{ $act->is_done ? 'line-through' : '' }}">{{ $act->subject }}</p>
                            @if($act->description)
                            <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $act->description }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 text-xs">
                            @if($act->contact)
                            <a href="{{ route('crm.contacts.show', $act->contact) }}"
                               class="text-emerald-700 hover:text-emerald-800 block truncate max-w-[160px]">👤 {{ $act->contact->name }}</a>
                            @endif
                            @if($act->opportunity)
                            <a href="{{ route('crm.opportunities.show', $act->opportunity) }}"
                               class="text-emerald-700 hover:text-emerald-900 block truncate max-w-[160px]">⚡ {{ Str::limit($act->opportunity->title, 25) }}</a>
                            @endif
                            @if(!$act->contact && !$act->opportunity)<span class="text-gray-300">—</span>@endif
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $act->typeColor() }}-100 text-{{ $act->typeColor() }}-700">
                                {{ $act->typeIcon() }} {{ $act->typeLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $act->priorityColor() }}-100 text-{{ $act->priorityColor() }}-700">
                                {{ $act->priorityLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5">
                            @if($act->due_at)
                            <span class="text-xs {{ $act->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                {{ $act->due_at->format('d/m/Y H:i') }}
                                @if($act->isOverdue()) ⚠️@endif
                            </span>
                            @else
                            <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $act->user?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5">
                            <a href="{{ route('crm.activities.edit', $act) }}"
                               class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors inline-flex" title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Aucune activité</span>
                                <a href="{{ route('crm.activities.create') }}" class="text-emerald-700 hover:text-emerald-800 font-medium text-xs">Créer la première →</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($activities->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $activities->links() }}</div>
        @endif
    </div>

</div>
@endsection
