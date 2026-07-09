@extends('layouts.erp')
@section('title', 'Synchronisations')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Synchronisations</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $badges = [
        'success'  => 'bg-emerald-100 text-emerald-800',
        'failed'   => 'bg-red-100 text-red-700',
        'pending'  => 'bg-amber-100 text-amber-700',
        'skipped'  => 'bg-gray-100 text-gray-500',
        'retrying' => 'bg-blue-100 text-blue-700',
    ];
    $statusLabels = ['success' => 'Succès', 'failed' => 'Échec', 'pending' => 'En cours', 'skipped' => 'Ignorée', 'retrying' => 'Relance'];
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Synchronisations inter-modules</h1>
            <p class="text-[11.5px] text-gray-500">
                Journal des flux automatiques —
                <span class="text-emerald-700 font-semibold">{{ $stats['success'] ?? 0 }} succès</span> ·
                <span class="{{ ($stats['failed'] ?? 0) ? 'text-red-600 font-semibold' : '' }}">{{ $stats['failed'] ?? 0 }} échec(s)</span> ·
                {{ $stats['skipped'] ?? 0 }} ignorée(s)
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-[4px] px-3 py-2.5 text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-[4px] px-3 py-2.5 text-[13px]">{{ session('error') }}</div>
    @endif

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-3 flex flex-wrap items-center gap-2">
        <select name="status" class="h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white">
            <option value="">— Tous statuts —</option>
            @foreach($statusLabels as $val => $label)
            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="module" class="h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white">
            <option value="">— Tous modules —</option>
            @foreach($modules as $m)
            <option value="{{ $m }}" @selected(request('module') === $m)>{{ ucfirst($m) }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Événement, action, message…"
               class="flex-1 min-w-[200px] h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white">
        <button type="submit" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">Filtrer</button>
        @if(request()->hasAny(['status', 'module', 'search']))
        <a href="{{ route('sync-logs.index') }}" class="text-[13px] text-gray-500 hover:text-gray-700 px-2">✕ Réinitialiser</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left w-14">#</th>
                <th class="{{ $th }} text-left">Flux</th>
                <th class="{{ $th }} text-left">Événement / Action</th>
                <th class="{{ $th }} text-left">Document source</th>
                <th class="{{ $th }} text-center w-20">Statut</th>
                <th class="{{ $th }} text-left">Message</th>
                <th class="{{ $th }} text-left w-32">Date</th>
                <th class="{{ $th }} text-left w-28">Utilisateur</th>
                <th class="{{ $th }} text-right w-24">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 {{ $log->status === 'failed' ? '!bg-red-50/50' : '' }}">
                    <td class="px-3 py-1.5 text-gray-400 tabular-nums">{{ $log->id }}</td>
                    <td class="px-3 py-1.5 whitespace-nowrap">
                        <span class="font-semibold text-gray-700">{{ ucfirst($log->source_module) }}</span>
                        <span class="text-gray-400 mx-0.5">→</span>
                        <span class="font-semibold text-emerald-800">{{ ucfirst($log->target_module) }}</span>
                    </td>
                    <td class="px-3 py-1.5">
                        <p class="font-mono text-[11.5px] text-gray-700">{{ $log->event_name }}</p>
                        <p class="font-mono text-[10.5px] text-gray-400">{{ $log->action }}</p>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[11px] text-gray-500">{{ class_basename($log->source_type) }} #{{ $log->source_id }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badges[$log->status] ?? 'bg-gray-100 text-gray-500' }}">{{ $statusLabels[$log->status] ?? $log->status }}</span>
                        @if($log->attempts > 1)<span class="block text-[10px] text-gray-400 mt-0.5">{{ $log->attempts }} tentatives</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-gray-600 max-w-[280px]">
                        <span class="line-clamp-2 {{ $log->status === 'failed' ? 'text-red-600' : '' }}" title="{{ $log->message }}">{{ $log->message ?? '—' }}</span>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[11px] tabular-nums text-gray-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-1.5 text-gray-500 truncate max-w-[110px]">{{ $log->creator?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right">
                        @if($log->isRetryable())
                        <form method="POST" action="{{ route('sync-logs.retry', $log) }}" class="inline"
                              onsubmit="return confirm('Relancer cette synchronisation ?')">
                            @csrf
                            <button type="submit" class="text-[12px] font-semibold text-emerald-700 hover:underline">⟳ Relancer</button>
                        </form>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-5 py-16 text-center text-gray-400">Aucune synchronisation journalisée pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($logs->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $logs->links() }}</div>
        @endif
    </div>

    <p class="text-[11.5px] text-gray-400 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Une synchronisation « Ignorée » signifie qu'elle avait déjà réussi (idempotence). Les échecs relançables ont un bouton ⟳ — la relance est elle-même journalisée.
    </p>

</div>
@endsection
