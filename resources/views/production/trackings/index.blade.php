@extends('layouts.erp')
@section('title', 'Suivis de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Suivi de fabrication</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Suivi de fabrication</h1>
            <p class="text-sm text-gray-500 mt-0.5">Journal des suivis — opérations, déclarations de production, matière.</p>
        </div>
        @can('production.update')
        <a href="{{ route('production.trackings.create') }}"
           class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded-[4px] transition-colors">
            + Nouveau suivi
        </a>
        @endcan
    </div>

    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-3 flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="N° suivi ou N° OF…"
               class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] w-64 focus:ring-1 focus:ring-emerald-500">
        <button class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Filtrer</button>
        @if(request('q'))<a href="{{ route('production.trackings.index') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">✕</a>@endif
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[14px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Numéro suivi</th>
                        <th class="px-3 py-1.5 text-left">Site prod</th>
                        <th class="px-3 py-1.5 text-left">No ordre</th>
                        <th class="px-3 py-1.5 text-left">Date suivi</th>
                        <th class="px-3 py-1.5 text-left">Suivis effectués</th>
                        <th class="px-3 py-1.5 text-left">Observations</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($trackings as $t)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-[12px] text-emerald-700">{{ $t->number }}</td>
                        <td class="px-3 py-1 text-gray-600">{{ $t->site ?? '—' }}</td>
                        <td class="px-3 py-1">
                            <a href="{{ route('production.orders.show', $t->production_order_id) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $t->productionOrder?->number ?? '—' }}</a>
                        </td>
                        <td class="px-3 py-1 tabular-nums text-gray-600">{{ $t->tracking_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1 text-gray-700">{{ $t->tracksLabel() }}</td>
                        <td class="px-3 py-1 text-gray-500 truncate max-w-[280px]">{{ $t->notes ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 text-[13px]">Aucun suivi enregistré. Créez un suivi depuis « + Nouveau suivi ».</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($trackings->hasPages())
        <div class="px-3 py-2 border-t border-gray-200">{{ $trackings->links() }}</div>
        @endif
    </div>

</div>
@endsection
