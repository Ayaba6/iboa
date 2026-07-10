@extends('layouts.erp')
@section('title', 'Inventaires')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Inventaires</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Inventaires</h1>
            <p class="text-[11.5px] text-gray-400">{{ $sessions->total() }} session(s) d'inventaire</p>
        </div>
        <a href="{{ route('stocks.inventaires.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 self-start transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvel inventaire
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1.5">
            <select name="warehouse_id"
                    class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les entrepôts</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>

            <select name="status"
                    class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="ouvert"   {{ ($filters['status'] ?? '') === 'ouvert'   ? 'selected' : '' }}>Ouvert</option>
                <option value="en_cours" {{ ($filters['status'] ?? '') === 'en_cours' ? 'selected' : '' }}>En cours</option>
                <option value="valide"   {{ ($filters['status'] ?? '') === 'valide'   ? 'selected' : '' }}>Validé</option>
                <option value="annule"   {{ ($filters['status'] ?? '') === 'annule'   ? 'selected' : '' }}>Annulé</option>
            </select>

            <div class="flex gap-1.5 lg:col-span-2">
                <button type="submit"
                        class="flex-1 h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['warehouse_id', 'status']))
                <a href="{{ route('stocks.inventaires.index') }}"
                   class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">N°</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Entrepôt</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Date début</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Validé le</th>
                        <th class="px-3 py-1.5 text-center text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Nb articles</th>
                        <th class="px-3 py-1.5 w-px"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sessions as $session)
                        @php
                            $statusConfig = match($session->status) {
                                'ouvert'   => ['label' => 'Ouvert',   'class' => 'bg-gray-100 text-gray-700'],
                                'en_cours' => ['label' => 'En cours', 'class' => 'bg-blue-100 text-blue-700'],
                                'valide'   => ['label' => 'Validé',   'class' => 'bg-emerald-100 text-emerald-700'],
                                'annule'   => ['label' => 'Annulé',   'class' => 'bg-red-100 text-red-700'],
                                default    => ['label' => $session->status, 'class' => 'bg-gray-100 text-gray-600'],
                            };
                        @endphp
                        <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                            <td class="px-3 py-1">
                                <span class="font-mono font-semibold text-emerald-800">{{ $session->number ?? '#'.$session->id }}</span>
                                @if($session->type)
                                <span class="ml-1 text-[10.5px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded-[3px]">{{ $session->typeLabel() }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-1 font-medium text-gray-900">
                                {{ $session->warehouse?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-1 text-gray-600 hidden md:table-cell whitespace-nowrap">
                                {{ $session->started_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-1 text-gray-600 hidden lg:table-cell whitespace-nowrap">
                                {{ $session->validated_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-1 text-center">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </td>
                            <td class="px-3 py-1 text-right tabular-nums text-gray-600 hidden md:table-cell">
                                {{ $session->items_count ?? 0 }}
                            </td>
                            <td class="px-3 py-1">
                                <div class="flex items-center justify-end gap-0.5">
                                    {{-- Voir --}}
                                    <a href="{{ route('stocks.inventaires.show', $session) }}"
                                       class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors"
                                       title="Voir / Compter">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    {{-- Valider --}}
                                    @if($session->status === 'en_cours')
                                    <form action="{{ route('stocks.inventaires.validate', $session) }}" method="POST"
                                          onsubmit="return confirm('Valider cet inventaire ? Le stock sera mis à jour.')">
                                        @csrf
                                        <button type="submit"
                                                class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                                title="Valider">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-[12.5px]">
                                Aucune session d'inventaire trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sessions->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">
            {{ $sessions->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
