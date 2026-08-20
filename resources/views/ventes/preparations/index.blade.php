@extends('layouts.erp')
@section('title', 'Bons de préparation')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Ventes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bons de préparation</span>
@endsection

@section('content')
@php
    $inp = 'h-8 py-0 border border-gray-300 rounded-[4px] px-2.5 text-[12px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400';
    // Un statut = une couleur, jamais un badge décoratif : la couleur porte
    // l'étape réelle du document dans la machine d'états.
    $badge = [
        'brouillon'             => ['Brouillon',              'bg-gray-100 text-gray-700'],
        'a_preparer'            => ['À préparer',             'bg-slate-100 text-slate-700'],
        'en_preparation'        => ['En préparation',         'bg-blue-100 text-blue-700'],
        'partiellement_prepare' => ['Partiellement préparé',  'bg-amber-100 text-amber-800'],
        'prepare'               => ['Préparé',                'bg-indigo-100 text-indigo-700'],
        'controle'              => ['Contrôlé',               'bg-teal-100 text-teal-800'],
        'valide'                => ['Validé',                 'bg-emerald-100 text-emerald-800'],
        'annule'                => ['Annulé',                 'bg-red-100 text-red-700'],
    ];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
@endphp

<div class="space-y-3">

    <x-sales.module-nav />

    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
        @foreach([
            ['Total', $summary['total'], 'text-gray-900'],
            ['À préparer', $summary['a_preparer'], 'text-slate-600'],
            ['En cours', $summary['en_cours'], 'text-blue-600'],
            ['À contrôler', $summary['a_controler'], 'text-indigo-600'],
            ['À valider', $summary['a_valider'], 'text-teal-600'],
            ['Validés', $summary['valides'], 'text-emerald-600'],
        ] as [$label, $value, $color])
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">{{ $label }}</p>
            <p class="text-[15px] font-bold {{ $color }} tabular-nums">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 flex-wrap gap-2">
            <h2 class="text-[15px] font-bold text-gray-900">Bons de préparation quantifiés</h2>
            @can('bon_preparations.update')
            <a href="{{ route('ventes.preparations.create') }}"
               class="text-[13px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-1.5 rounded-[4px] transition-colors">
                Nouveau bon
            </a>
            @endcan
        </div>

        <form method="GET" class="flex flex-wrap gap-2 px-4 py-2.5 border-b border-gray-200 bg-gray-50">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° bon, commande, client…" class="{{ $inp }} w-64">
            <select name="status" class="{{ $inp }}">
                <option value="">Tous les statuts</option>
                @foreach($badge as $key => [$label, $cls])
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="h-8 px-4 text-[12px] font-semibold text-emerald-700 border border-emerald-300 rounded-[4px] hover:bg-emerald-50">Filtrer</button>
            <a href="{{ route('ventes.preparations.index') }}" class="h-8 px-4 inline-flex items-center text-[12px] text-gray-500 hover:text-gray-700 border border-gray-300 rounded-[4px]">Réinitialiser</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-2 font-semibold">N° bon</th>
                        <th class="text-left px-4 py-2 font-semibold">Commande</th>
                        <th class="text-left px-4 py-2 font-semibold">Client</th>
                        <th class="text-left px-4 py-2 font-semibold">Dépôt</th>
                        <th class="text-right px-4 py-2 font-semibold">À préparer</th>
                        <th class="text-right px-4 py-2 font-semibold">Prélevé</th>
                        <th class="text-right px-4 py-2 font-semibold">Écart</th>
                        <th class="text-left px-4 py-2 font-semibold">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($pickings as $p)
                    @php
                        $toPrepare = $p->items->sum('qty_remaining_snapshot');
                        $picked    = $p->items->sum('qty_picked');
                        $variance  = $p->items->sum('variance_qty');
                        [$label, $cls] = $badge[$p->status] ?? [$p->status, 'bg-gray-100 text-gray-700'];
                    @endphp
                    <tr class="hover:bg-emerald-50/40">
                        <td class="px-4 py-2">
                            <a href="{{ route('ventes.preparations.show', $p) }}" class="font-mono font-semibold text-emerald-700 hover:underline">{{ $p->number }}</a>
                        </td>
                        <td class="px-4 py-2 font-mono text-gray-600">{{ $p->order?->number ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $p->order?->client?->name ?? '—' }}</td>
                        <td class="px-4 py-2 font-mono text-gray-500">{{ $p->warehouse?->code ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($toPrepare) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($picked) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums {{ abs($variance) > 0.0005 ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">
                            {{ $fmt($variance) }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-block px-2 py-0.5 rounded-[3px] text-[11px] font-semibold {{ $cls }}">{{ $label }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            Aucun bon de préparation quantifié.
                            <span class="block text-[11px] text-gray-400 mt-1">
                                Les bons de chargement historiques restent consultables dans « Préparations (historique) ».
                            </span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-2.5 border-t border-gray-200">{{ $pickings->links() }}</div>
    </div>
</div>
@endsection
