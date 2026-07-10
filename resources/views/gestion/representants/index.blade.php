@extends('layouts.erp')
@section('title', 'Représentants commerciaux')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Représentants commerciaux</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Représentants commerciaux</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $reps->total() }} représentant(s)</p>
        </div>
        @can('create', \App\Models\SalesRep::class)
        <a href="{{ route('representants.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-2.5 rounded-[4px] flex items-center gap-2 transition-colors self-start">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau représentant
        </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Nom du représentant…"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <select name="is_active" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Actifs</option>
                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactifs</option>
            </select>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-2 rounded-[4px] transition-colors">Filtrer</button>
            @if(request()->hasAny(['search', 'is_active']))
            <a href="{{ route('representants.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-1.5 rounded-[4px] transition-colors">Réinitialiser</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Représentant</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Contact</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Taux</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden sm:table-cell">Clients</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Commissions totales</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($reps as $rep)
                <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('representants.show', $rep) }}" class="font-medium text-gray-900 hover:text-emerald-700 transition-colors">
                            {{ $rep->name }}
                        </a>
                        @if($rep->code)
                        <p class="text-[11px] text-emerald-800 font-mono">{{ $rep->code }}</p>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 hidden md:table-cell text-gray-600">
                        <div>{{ $rep->email ?: '—' }}</div>
                        <div class="text-xs text-gray-400">{{ $rep->phone ?: '' }}</div>
                    </td>
                    <td class="px-3 py-1.5 text-right font-semibold tabular-nums">
                        {{ number_format($rep->commission_rate, 2, ',', ' ') }} %
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden sm:table-cell">
                        {{ $rep->clients_count }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums hidden lg:table-cell">
                        {{ number_format($rep->commissions_total ?? 0, 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($rep->is_active)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-50 text-green-700 border border-green-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Actif
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactif
                        </span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('representants.show', $rep) }}"
                               class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            @can('update', $rep)
                            <a href="{{ route('representants.edit', $rep) }}"
                               class="p-1.5 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-16 text-center">
                        <div class="flex flex-col items-center gap-3 text-gray-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-600">Aucun représentant trouvé</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $reps->total() }} représentant(s)</span>
            @if($reps->hasPages())<div>{{ $reps->withQueryString()->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
