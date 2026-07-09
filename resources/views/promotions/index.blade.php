@extends('layouts.erp')
@section('title', 'Promotions')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Promotions</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Promotions</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $promotions->total() }} promotion(s)</p>
        </div>
        <a href="{{ route('promotions.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle promotion
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3">
        {{-- Status tabs --}}
        <div class="flex bg-gray-100 rounded-[4px] p-1 gap-1">
            @foreach(['all' => 'Toutes', 'active' => 'Actives', 'upcoming' => 'À venir', 'expired' => 'Expirées'] as $val => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $val]) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                      {{ $status === $val ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        {{-- Type filter --}}
        <select name="type" onchange="this.form.submit()"
                class="px-3 py-2 border border-gray-300 rounded-[4px] text-sm bg-white focus:outline-none focus:ring-1 focus:ring-purple-400">
            <option value="">Tous les types</option>
            <option value="pourcentage" {{ request('type') === 'pourcentage' ? 'selected' : '' }}>Pourcentage</option>
            <option value="montant_fixe" {{ request('type') === 'montant_fixe' ? 'selected' : '' }}>Montant fixe</option>
            <option value="prix_special" {{ request('type') === 'prix_special' ? 'selected' : '' }}>Prix spécial</option>
        </select>

        {{-- Product filter --}}
        <select name="product_id" onchange="this.form.submit()"
                class="px-3 py-2 border border-gray-300 rounded-[4px] text-sm bg-white focus:outline-none focus:ring-1 focus:ring-purple-400">
            <option value="">Tous les articles</option>
            @foreach($products as $product)
            <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                {{ $product->name }}
            </option>
            @endforeach
        </select>

        @if(request('type') || request('product_id'))
        <a href="{{ route('promotions.index', ['status' => $status]) }}"
           class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 rounded-[4px] hover:bg-gray-100 transition-colors">
            Réinitialiser
        </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @if($promotions->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            <p class="text-sm font-medium">Aucune promotion trouvée</p>
        </div>
        @else
        <div class="overflow-x-auto">
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nom</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                    <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Valeur</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Période</th>
                    <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article / Famille</th>
                    <th class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($promotions as $promo)
                @php
                    $today = \Carbon\Carbon::today();
                    $isActive  = $promo->is_active && $promo->starts_at <= $today && $promo->ends_at >= $today;
                    $isExpired = $promo->ends_at < $today;
                    $isUpcoming = $promo->starts_at > $today;
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $promo->name }}</td>
                    <td class="px-5 py-3">
                        @if($promo->type === 'pourcentage')
                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            % Pourcentage
                        </span>
                        @elseif($promo->type === 'montant_fixe')
                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            FCFA Montant fixe
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            ★ Prix spécial
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-mono font-medium text-gray-800">
                        @if($promo->type === 'pourcentage')
                            {{ number_format($promo->value, 0) }}%
                        @else
                            {{ number_format($promo->value, 0, ',', ' ') }} FCFA
                        @endif
                    </td>
                    <td class="px-5 py-3 text-gray-600 text-xs">
                        <div>{{ $promo->starts_at ? $promo->starts_at->format('d/m/Y') : '—' }}</div>
                        <div class="text-gray-400">→ {{ $promo->ends_at ? $promo->ends_at->format('d/m/Y') : '—' }}</div>
                    </td>
                    <td class="px-5 py-3 text-gray-600 text-xs">
                        @if($promo->product)
                        <span class="font-medium text-gray-800">{{ $promo->product->name }}</span>
                        @elseif($promo->family)
                        <span class="text-blue-600">{{ $promo->family->name }}</span>
                        @else
                        <span class="text-gray-400 italic">Tous articles</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($isActive)
                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>Active
                        </span>
                        @elseif($isUpcoming)
                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-blue-400 rounded-full"></span>À venir
                        </span>
                        @elseif($isExpired)
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Expirée
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Inactive
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('promotions.edit', $promo) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-purple-600 hover:text-purple-800 bg-purple-50 hover:bg-purple-100 px-2.5 py-1 rounded-md transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Modifier
                            </a>
                            <form method="POST" action="{{ route('promotions.destroy', $promo) }}"
                                  onsubmit="return confirm('Supprimer la promotion « {{ addslashes($promo->name) }} » ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1 rounded-md transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- Pagination --}}
    @if($promotions->hasPages())
    <div class="flex justify-center">
        {{ $promotions->links() }}
    </div>
    @endif

</div>
@endsection
