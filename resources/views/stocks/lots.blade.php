@extends('layouts.erp')
@section('title', 'Lots & Traçabilité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Lots & Traçabilité</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Lots &amp; Traçabilité</h1>
            <p class="text-[11.5px] text-gray-400">{{ $lots->total() }} lot(s) trouvé(s)</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-1.5">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Lot, série, article…"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 lg:col-span-2">

            <select name="warehouse_id"
                    class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les entrepôts</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>

            <select name="status"
                    class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="disponible" {{ ($filters['status'] ?? 'disponible') === 'disponible' ? 'selected' : '' }}>Disponible</option>
                <option value="reserve"    {{ ($filters['status'] ?? '') === 'reserve'    ? 'selected' : '' }}>Réservé</option>
                <option value="expire"     {{ ($filters['status'] ?? '') === 'expire'     ? 'selected' : '' }}>Expiré</option>
                <option value="consomme"   {{ ($filters['status'] ?? '') === 'consomme'   ? 'selected' : '' }}>Consommé</option>
                <option value=""           {{ ($filters['status'] ?? '') === ''           ? 'selected' : '' }}>Tous</option>
            </select>

            <label class="flex items-center gap-1.5 text-[12px] text-gray-700 cursor-pointer px-1">
                <input type="checkbox" name="expiring_soon" value="1"
                       {{ !empty($filters['expiring_soon']) ? 'checked' : '' }}
                       class="w-3.5 h-3.5 text-orange-500 rounded">
                <span>Expire bientôt (30j)</span>
            </label>
        </div>
        <div class="flex gap-1.5 mt-1.5">
            <button type="submit"
                    class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['search', 'warehouse_id', 'status', 'expiring_soon']))
            <a href="{{ route('stocks.lots') }}"
               class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">
                Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">N° Lot</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">N° Série</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Entrepôt</th>
                        <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Quantité</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Péremption</th>
                        <th class="px-3 py-1.5 text-center text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lots as $lot)
                    @php
                        $daysLeft = $lot->daysUntilExpiry();
                        $rowClass = 'odd:bg-white even:bg-gray-50/40';
                        if ($lot->status === 'expire' || ($daysLeft !== null && $daysLeft < 0)) {
                            $rowClass = 'bg-red-50';
                        } elseif ($daysLeft !== null && $daysLeft <= 30) {
                            $rowClass = 'bg-orange-50';
                        }
                        $statusClasses = [
                            'disponible' => 'bg-green-100 text-green-700',
                            'reserve'    => 'bg-blue-100 text-blue-700',
                            'expire'     => 'bg-red-100 text-red-700',
                            'consomme'   => 'bg-gray-100 text-gray-500',
                        ];
                    @endphp
                    <tr class="hover:bg-emerald-50/50 transition-colors {{ $rowClass }}">
                        <td class="px-3 py-1">
                            <span class="font-medium text-gray-900">{{ $lot->product?->name ?? '—' }}</span>
                            @if($lot->product?->reference)
                            <span class="text-[10.5px] text-gray-400 font-mono">· {{ $lot->product->reference }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 font-mono text-emerald-800 font-semibold">{{ $lot->lot_number }}</td>
                        <td class="px-3 py-1 font-mono text-gray-500 text-[11px] hidden md:table-cell">{{ $lot->serial_number ?? '—' }}</td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell">{{ $lot->warehouse?->name ?? '—' }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold text-gray-900">
                            {{ number_format((float)$lot->quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1">
                            @if($lot->expiry_date)
                                <span class="{{ $daysLeft !== null && $daysLeft <= 0 ? 'text-red-600 font-semibold' : ($daysLeft !== null && $daysLeft <= 30 ? 'text-orange-600 font-medium' : 'text-gray-700') }}">
                                    {{ $lot->expiry_date->format('d/m/Y') }}
                                </span>
                                @if($daysLeft !== null && $daysLeft > 0 && $daysLeft <= 30)
                                <span class="text-[10.5px] text-orange-500">· {{ $daysLeft }}j</span>
                                @elseif($daysLeft !== null && $daysLeft <= 0)
                                <span class="text-[10.5px] text-red-500">· Expiré</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $statusClasses[$lot->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $lot->statusLabel() }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-[12.5px]">
                            Aucun lot trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lots->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">
            {{ $lots->withQueryString()->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
