@extends('layouts.erp')
@section('title', 'DLC & lots périmés')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">DLC & lots</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">🗓 DLC proches & lots périmés</h1>
            <p class="text-sm text-gray-500">Suivi de la péremption pour limiter le gaspillage.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Fenêtre d'alerte (jours)</label>
                <select name="window" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    @foreach([7, 14, 30, 60, 90] as $w)
                    <option value="{{ $w }}" {{ $window == $w ? 'selected' : '' }}>{{ $w }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- Lots PÉRIMÉS --}}
    @if($expired->isNotEmpty())
    <div class="bg-white rounded-[4px] border-2 border-red-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-red-200 bg-red-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-red-800">🛑 {{ count($expired) }} lot(s) périmé(s) — à isoler / détruire</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-left">Lot</th>
                    <th class="px-3 py-1.5 text-left">Dépôt</th>
                    <th class="px-3 py-1.5 text-right">Qté</th>
                    <th class="px-3 py-1.5 text-right">Valeur</th>
                    <th class="px-3 py-1.5 text-right">DLC</th>
                    <th class="px-3 py-1.5 text-right">Périmé depuis</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($expired as $lot)
                <tr class="hover:bg-red-50/40">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('stocks.show', $lot->product_id) }}" class="font-mono text-xs text-blue-700 hover:underline">{{ $lot->reference }}</a>
                        <p class="text-sm text-gray-900">{{ $lot->name }}</p>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-xs">{{ $lot->lot_number ?? $lot->serial_number ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-xs text-gray-700">{{ $lot->warehouse_name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($lot->quantity, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-red-700 font-semibold">{{ $fmt($lot->quantity * $lot->unit_cost) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-red-700">{{ \Carbon\Carbon::parse($lot->expiry_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-red-700 font-medium">{{ (int) $lot->days_expired }} j</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Lots qui vont périmer dans la fenêtre --}}
    <div class="bg-white rounded-[4px] border border-rose-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-rose-100 bg-rose-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-rose-800">⏳ {{ $expiring->total() }} lot(s) à péremption sous {{ $window }} jour(s)</h2>
        </div>
        @if($expiring->isEmpty())
            <div class="p-6 text-center text-emerald-700 text-sm">✓ Aucun lot ne va expirer dans la fenêtre.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-left">Lot</th>
                    <th class="px-3 py-1.5 text-left">Dépôt</th>
                    <th class="px-3 py-1.5 text-right">Qté</th>
                    <th class="px-3 py-1.5 text-right">Valeur</th>
                    <th class="px-3 py-1.5 text-right">DLC</th>
                    <th class="px-3 py-1.5 text-right">Restant</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($expiring as $lot)
                @php $bg = $lot->days_left <= 7 ? 'bg-rose-50/60' : ''; @endphp
                <tr class="hover:bg-rose-50/30 {{ $bg }}">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('stocks.show', $lot->product_id) }}" class="font-mono text-xs text-blue-700 hover:underline">{{ $lot->reference }}</a>
                        <p class="text-sm text-gray-900">{{ $lot->name }}</p>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-xs">{{ $lot->lot_number ?? $lot->serial_number ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-xs text-gray-700">{{ $lot->warehouse_name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($lot->quantity, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ $fmt($lot->quantity * $lot->unit_cost) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ \Carbon\Carbon::parse($lot->expiry_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $lot->days_left <= 7 ? 'text-rose-700 font-semibold' : 'text-rose-600' }}">
                        {{ (int) $lot->days_left }} j
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-3 py-1.5">{{ $expiring->links() }}</div>
        @endif
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Bonnes pratiques</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700 text-xs">
            <li>Méthode <strong>FEFO</strong> (First Expired, First Out) : sortez en priorité les lots à DLC proche.</li>
            <li>Promos / soldes pour les lots &lt; 14 j si écoulement possible.</li>
            <li>Les lots périmés doivent être isolés physiquement et faire l'objet d'un mouvement de sortie type « ajustement ».</li>
        </ul>
    </div>
</div>
@endsection
