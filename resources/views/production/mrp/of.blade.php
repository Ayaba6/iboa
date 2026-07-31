@extends('layouts.erp')
@section('title', 'MRP — propositions d’ordre de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Propositions d’OF</span>
@endsection

@section('content')
<form method="POST" action="{{ route('production.mrp.of.generate') }}" class="space-y-3">
    @csrf

    {{-- ═══ Bandeau SAGE X3 ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">MRP — propositions d’ordre de fabrication</h2>
                <p class="text-[11.5px] text-gray-400">
                    Articles fabriqués pour le stock dont le besoin net est positif et la nomenclature active.
                    Même calcul que la planification MTS.
                </p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('production.mrp') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Calcul des besoins</a>
                <a href="{{ route('production.orders.mts') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Planification MTS</a>
                @can('production.create')
                    @if($stats['count'] > 0)
                    <button type="submit"
                            class="text-[14px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-5 py-2 rounded-[4px] transition-colors">
                        Générer les OF retenus
                    </button>
                    @endif
                @endcan
            </div>
        </div>
        <div class="px-4 py-2 border-t border-gray-200 flex flex-wrap gap-x-8 gap-y-1 text-[12px] text-gray-600">
            <span>Propositions : <span class="font-semibold text-gray-900 tabular-nums">{{ $stats['count'] }}</span></span>
            <span>Besoin total : <span class="font-semibold text-gray-900 tabular-nums">{{ number_format($stats['besoin'], 0, ',', ' ') }}</span></span>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 w-8"></th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Disponible</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Demande client</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Attendu</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Cible</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">OF planifiés</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Quantité proposée</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($proposals as $r)
                <tr class="hover:bg-[#eef5f0]/40">
                    <td class="px-3 py-1.5">
                        {{-- Coché par défaut : le planificateur écarte ce qu'il refuse,
                             plutôt que de re-sélectionner tout ce que le calcul propose. --}}
                        <input type="checkbox" name="product_ids[]" value="{{ $r['p']->id }}" checked
                               class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600">
                    </td>
                    <td class="px-3 py-1.5">
                        <span class="font-medium text-gray-900">{{ $r['p']->name }}</span>
                        <span class="text-[11px] text-gray-400 font-mono ml-1">{{ $r['p']->reference }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $r['dispo'] <= 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($r['dispo'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $r['client'] > 0 ? 'text-gray-900' : 'text-gray-400' }}">{{ number_format($r['client'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $r['recu'] > 0 ? 'text-indigo-700' : 'text-gray-400' }}">{{ number_format($r['recu'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($r['cible'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-blue-700">{{ number_format($r['plan'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-amber-700">{{ number_format($r['besoin'], 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-3 py-6 text-center text-gray-400 text-[12.5px]">
                        Aucune proposition : soit les stocks couvrent les cibles, soit les articles
                        concernés n’ont pas de seuil défini ou pas de nomenclature active.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Module : <span class="text-white font-semibold">production — MRP / propositions d’OF</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</form>
@endsection
