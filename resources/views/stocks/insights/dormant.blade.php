@extends('layouts.erp')
@section('title', 'Articles dormants')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Articles dormants</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
    $totalImmo = collect($products->items())->sum('immobilized_value');
@endphp

<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">💤 Articles dormants</h1>
            <p class="text-sm text-gray-500">
                {{ $products->total() }} article(s) sans mouvement depuis {{ $days }} jour(s) ·
                <span class="font-medium">{{ $fmt($totalImmo) }} FCFA</span> immobilisé (page courante).
            </p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Inactif depuis (jours)</label>
                <select name="days" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    @foreach([30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" {{ $days == $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($products->isEmpty())
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-6 text-center text-emerald-700 text-sm">
            ✓ Aucun article dormant ces {{ $days }} derniers jours.
        </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-left">Dépôt</th>
                    <th class="px-3 py-1.5 text-right">Quantité</th>
                    <th class="px-3 py-1.5 text-right">CMP</th>
                    <th class="px-3 py-1.5 text-right">Valeur immobilisée</th>
                    <th class="px-3 py-1.5 text-right">Inactif depuis</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($products as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-xs text-blue-700 hover:underline">{{ $row->reference }}</a>
                        <p class="text-sm text-gray-900">{{ $row->name }}</p>
                    </td>
                    <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row->warehouse_name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($row->quantity, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($row->avg_cost) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ $fmt($row->immobilized_value) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $row->days_idle > 180 ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                        {{ (int) $row->days_idle }} j
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Quoi en faire ?</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700 text-xs">
            <li>Articles &gt; 180 jours sans mouvement : <strong>candidat au déstockage</strong> (promo, soldes).</li>
            <li>Articles non revendus malgré stock : envisager de retirer de l'offre commerciale.</li>
            <li>La valeur immobilisée gèle votre trésorerie — chaque mois sans rotation, c'est un coût d'opportunité.</li>
        </ul>
    </div>
</div>
@endsection
