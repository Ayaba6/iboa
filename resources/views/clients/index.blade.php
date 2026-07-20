@extends('layouts.erp')
@section('title', 'Clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Clients</span>
@endsection

@section('content')
@php
    $kpi = 'bg-white rounded-[4px] border border-gray-300 px-3 py-1.5';
    $kpL = 'text-[10px] font-bold text-gray-400 uppercase tracking-wide';
    $kpV = 'text-[15px] font-bold tabular-nums';
    $inp = 'h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500';
    $btn = 'inline-flex items-center gap-1.5 px-3 py-1 text-[12px] font-semibold rounded-[4px] transition-colors';
@endphp
<div class="space-y-3">

    {{-- KPI summary bar (dense SAGE) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Total clients</p><p class="{{ $kpV }} text-gray-900">{{ $summary['total'] }}</p></div>
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Actifs</p><p class="{{ $kpV }} text-emerald-600">{{ $summary['active'] }}</p></div>
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Entreprises</p><p class="{{ $kpV }} text-emerald-700">{{ $summary['entreprise'] }}</p></div>
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Particuliers</p><p class="{{ $kpV }} text-blue-600">{{ $summary['particulier'] }}</p></div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Clients</h1>
            <p class="text-[11.5px] text-gray-400">{{ $clients->total() }} client(s)</p>
        </div>
        <div class="flex items-center gap-1.5 self-start flex-wrap">
            <a href="{{ route('exports.clients', request()->query()) }}" class="{{ $btn }} border border-emerald-600 text-emerald-700 hover:bg-emerald-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Excel
            </a>
            <a href="{{ route('exports.clients-pdf', request()->query()) }}" class="{{ $btn }} border border-red-600 text-red-700 hover:bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('import.index', ['type' => 'clients']) }}" class="{{ $btn }} bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4 0l4-4m0 0l4 4m-4-4V4"/></svg>
                Importer
            </a>
            <a href="{{ route('clients.create') }}" class="{{ $btn }} bg-emerald-700 text-white hover:bg-emerald-800">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouveau client
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-2.5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, code, téléphone, email..." class="{{ $inp }}">
            <select name="type" class="{{ $inp }}">
                <option value="">Tous les types</option>
                <option value="particulier" {{ ($filters['type'] ?? '') === 'particulier' ? 'selected' : '' }}>Particulier</option>
                <option value="entreprise"  {{ ($filters['type'] ?? '') === 'entreprise'  ? 'selected' : '' }}>Entreprise</option>
            </select>
            <select name="is_active" class="{{ $inp }}">
                <option value="">Tous les statuts</option>
                <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>Actif</option>
                <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>Inactif</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12.5px] font-semibold px-4 h-8 rounded-[4px] transition-colors">Filtrer</button>
                @if(array_filter($filters ?? []))
                <a href="{{ route('clients.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12.5px] px-3 h-8 inline-flex items-center rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="overflow-x-auto">
            <table data-dt="simple" data-col-filter class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th data-sortable class="text-left font-bold px-3 py-1 uppercase tracking-wide w-28">Code client</th>
                        <th data-sortable class="text-left font-bold px-3 py-1 uppercase tracking-wide">Raison sociale</th>
                        <th data-sortable class="text-left font-bold px-3 py-1 uppercase tracking-wide hidden sm:table-cell w-28">Type</th>
                        <th data-sortable class="text-left font-bold px-3 py-1 uppercase tracking-wide hidden md:table-cell">Email</th>
                        <th class="text-left font-bold px-3 py-1 uppercase tracking-wide hidden lg:table-cell w-36">Téléphone</th>
                        <th data-sortable class="text-right font-bold px-3 py-1 uppercase tracking-wide hidden lg:table-cell w-32">Solde dû</th>
                        <th data-sortable class="text-center font-bold px-3 py-1 uppercase tracking-wide w-24">Statut</th>
                        <th class="px-3 py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('clients.show', $client) }}" class="hover:underline">{{ $client->code }}</a>
                        </td>
                        <td class="px-3 py-1">
                            <a href="{{ route('clients.show', $client) }}" class="font-medium text-gray-900 hover:text-emerald-700 transition-colors">{{ $client->displayName() }}</a>
                            @if($client->trade_name && $client->trade_name !== $client->name)
                                <p class="text-[11px] text-gray-400">{{ $client->name }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-1 hidden sm:table-cell">
                            @if($client->type === 'entreprise')
                                <span class="inline-flex px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-bold bg-purple-100 text-purple-700">Entreprise</span>
                            @else
                                <span class="inline-flex px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-bold bg-blue-100 text-blue-700">Particulier</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell">{{ $client->email ?: '—' }}</td>
                        <td class="px-3 py-1 text-gray-600 hidden lg:table-cell whitespace-nowrap">{{ $client->phone ?: ($client->mobile ?: '—') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums hidden lg:table-cell whitespace-nowrap {{ $client->balance > 0 ? 'text-red-600 font-semibold' : 'text-gray-300' }}">
                            {{ $client->balance > 0 ? number_format($client->balance, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1 text-center">
                            @if($client->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-50 text-green-700 border border-green-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Actif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactif
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('clients.show', $client) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                   title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('clients.edit', $client) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors"
                                   title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('clients.destroy', $client) }}" method="POST"
                                      onsubmit="return confirm('Archiver {{ addslashes($client->displayName()) }} ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                            title="Archiver">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p class="text-sm font-medium">Aucun client trouvé</p>
                                @if(array_filter($filters ?? []))
                                    <a href="{{ route('clients.index') }}" class="text-emerald-600 hover:text-emerald-700 text-sm">Effacer les filtres</a>
                                @else
                                    <a href="{{ route('clients.create') }}" class="text-emerald-600 hover:text-emerald-700 text-sm">Créer le premier client</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $clients->total() }} client(s)</span>
            @if($clients->hasPages())<div>{{ $clients->withQueryString()->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
