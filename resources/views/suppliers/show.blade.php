@extends('layouts.erp')
@section('title', $supplier->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $supplier->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ================================================================
         Header
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-orange-700 font-bold text-xl">
                        {{ strtoupper(substr($supplier->name, 0, 2)) }}
                    </span>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-[16px] font-bold text-gray-900">{{ $supplier->name }}</h1>
                        @if($supplier->code)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-orange-100 text-orange-700 font-mono">
                                {{ $supplier->code }}
                            </span>
                        @endif
                        @if($supplier->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                        @if($supplier->type)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold bg-blue-100 text-blue-700">
                                {{ ucfirst($supplier->type) }}
                            </span>
                        @endif
                    </div>
                    @if($supplier->city || $supplier->country)
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ implode(', ', array_filter([$supplier->city, $supplier->country])) }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('suppliers.edit', $supplier) }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                <a href="{{ route('suppliers.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================
         2-column layout
    ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Informations card --}}
        <div class="lg:col-span-2 space-y-3">

            {{-- Coordonnées --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Coordonnées
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    @if($supplier->email)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</dt>
                        <dd class="mt-0.5">
                            <a href="mailto:{{ $supplier->email }}" class="text-emerald-700 hover:underline">{{ $supplier->email }}</a>
                        </dd>
                    </div>
                    @endif

                    @if($supplier->phone)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $supplier->phone }}</dd>
                    </div>
                    @endif

                    @if($supplier->phone2)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile / Tél. 2</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $supplier->phone2 }}</dd>
                    </div>
                    @endif

                    @if($supplier->website)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Site web</dt>
                        <dd class="mt-0.5">
                            <a href="{{ $supplier->website }}" target="_blank" rel="noopener noreferrer"
                               class="text-emerald-700 hover:underline truncate block max-w-xs">
                                {{ $supplier->website }}
                            </a>
                        </dd>
                    </div>
                    @endif

                    @if($supplier->address)
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse</dt>
                        <dd class="mt-0.5 text-gray-900">
                            {{ $supplier->address }}
                            @if($supplier->city || $supplier->country)
                                <br>{{ implode(', ', array_filter([$supplier->city, $supplier->country])) }}
                            @endif
                        </dd>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Informations légales --}}
            @if($supplier->ifu || $supplier->rccm)
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Informations légales
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    @if($supplier->ifu)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">IFU</dt>
                        <dd class="mt-0.5 font-mono text-gray-900">{{ $supplier->ifu }}</dd>
                    </div>
                    @endif

                    @if($supplier->rccm)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">RCCM</dt>
                        <dd class="mt-0.5 font-mono text-gray-900">{{ $supplier->rccm }}</dd>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Contacts --}}
            @if($supplier->contacts->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Contacts
                    <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold">
                        {{ $supplier->contacts->count() }}
                    </span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($supplier->contacts as $contact)
                    <div class="border border-gray-200 rounded-[4px] p-3 relative">
                        @if($contact->is_primary)
                            <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Principal
                            </span>
                        @endif
                        <div class="pr-16">
                            <p class="font-medium text-gray-900 text-sm">
                                @if($contact->civility) {{ $contact->civility }} @endif
                                {{ trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: '—' }}
                            </p>
                            @if($contact->job_title)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $contact->job_title }}</p>
                            @endif
                        </div>
                        <div class="mt-2 space-y-1 text-xs text-gray-600">
                            @if($contact->phone)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    {{ $contact->phone }}
                                </div>
                            @endif
                            @if($contact->mobile)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    {{ $contact->mobile }}
                                </div>
                            @endif
                            @if($contact->email)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <a href="mailto:{{ $contact->email }}" class="text-emerald-700 hover:underline truncate">{{ $contact->email }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Adresses --}}
            @if($supplier->addresses->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Adresses
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($supplier->addresses as $addr)
                    <div class="border border-gray-200 rounded-[4px] p-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-semibold uppercase tracking-wider
                                @if($addr->type === 'siege') text-purple-700 bg-purple-100
                                @elseif($addr->type === 'livraison') text-blue-700 bg-blue-100
                                @else text-orange-700 bg-orange-100
                                @endif
                                px-1.5 py-0.5 rounded">
                                {{ $addr->type === 'siege' ? 'Siège' : ucfirst($addr->type ?? '') }}
                            </span>
                            @if($addr->is_default)
                                <span class="text-xs text-gray-500">Par défaut</span>
                            @endif
                        </div>
                        @if($addr->label)
                            <p class="text-xs font-medium text-gray-700">{{ $addr->label }}</p>
                        @endif
                        <p class="text-sm text-gray-900">{{ $addr->address }}</p>
                        @if($addr->city || $addr->country)
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ implode(', ', array_filter([$addr->city, $addr->country])) }}
                            </p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Conditions d'achat --}}
            @if($supplier->purchaseConditions->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Conditions d'achat
                    <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold">
                        {{ $supplier->purchaseConditions->count() }}
                    </span>
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Réf. fournisseur</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prix d'achat</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Remise</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Délai</th>
                                <th class="pb-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Préféré</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($supplier->purchaseConditions as $cond)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-2.5 text-gray-900">
                                    {{ $cond->product->name ?? '—' }}
                                </td>
                                <td class="py-2.5 font-mono text-xs text-gray-500">
                                    {{ $cond->supplier_reference ?: '—' }}
                                </td>
                                <td class="py-2.5 text-right font-medium tabular-nums">
                                    {{ $cond->purchase_price ? number_format($cond->purchase_price, 0, ',', ' ').' FCFA' : '—' }}
                                </td>
                                <td class="py-2.5 text-right tabular-nums text-gray-600">
                                    {{ $cond->discount_percent ? $cond->discount_percent.'%' : '—' }}
                                </td>
                                <td class="py-2.5 text-right text-gray-600">
                                    {{ $cond->lead_time_days ? $cond->lead_time_days.' j' : '—' }}
                                </td>
                                <td class="py-2.5 text-center">
                                    @if($cond->is_preferred)
                                        <svg class="w-4 h-4 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>

        {{-- Right: Stats & recent orders --}}
        <div class="space-y-3">

            {{-- Stats card --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Statistiques
                </h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500">Nb contacts</dt>
                        <dd class="font-semibold text-gray-900">{{ $supplier->contacts->count() }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500">Nb adresses</dt>
                        <dd class="font-semibold text-gray-900">{{ $supplier->addresses->count() }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500">Conditions d'achat</dt>
                        <dd class="font-semibold text-gray-900">{{ $supplier->purchaseConditions->count() }}</dd>
                    </div>
                    @if($supplier->rating)
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500">Note</dt>
                        <dd class="flex items-center gap-1">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= floor($supplier->rating))
                                    <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endif
                            @endfor
                            <span class="text-xs text-gray-500 ml-1">{{ $supplier->rating }}/5</span>
                        </dd>
                    </div>
                    @endif
                    @if($supplier->avg_delivery_days)
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500">Délai moyen livraison</dt>
                        <dd class="font-semibold text-gray-900">{{ $supplier->avg_delivery_days }} j</dd>
                    </div>
                    @endif
                    <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                        <dt class="text-gray-500">Solde dû</dt>
                        <dd class="font-semibold tabular-nums {{ ($supplier->balance ?? 0) > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ ($supplier->balance ?? 0) > 0 ? number_format($supplier->balance, 0, ',', ' ').' FCFA' : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Recent purchase orders --}}
            @if($supplier->purchaseOrders->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    Dernières commandes
                </h2>

                <div class="space-y-2">
                    @foreach($supplier->purchaseOrders as $order)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <div>
                            <a href="{{ route('achats.commandes.show', $order) }}"
                               class="font-medium text-emerald-700 hover:text-emerald-900 font-mono text-xs hover:underline">
                                {{ $order->number ?? $order->reference ?? '#'.$order->id }}
                            </a>
                            <p class="text-xs text-gray-500">
                                {{ isset($order->order_date) ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : (isset($order->created_at) ? $order->created_at->format('d/m/Y') : '—') }}
                            </p>
                        </div>
                        <div class="text-right">
                            @if(isset($order->total_ttc) || isset($order->total_amount))
                            <p class="font-medium tabular-nums text-gray-900 text-xs">
                                {{ number_format($order->total_ttc ?? $order->total_amount ?? 0, 0, ',', ' ') }} FCFA
                            </p>
                            @endif
                            @if(isset($order->status))
                            @php
                                $statusColors = [
                                    'brouillon'          => 'bg-gray-100 text-gray-600',
                                    'envoye'             => 'bg-purple-100 text-purple-700',
                                    'confirme'           => 'bg-blue-100 text-blue-700',
                                    'partiellement_recu' => 'bg-yellow-100 text-yellow-700',
                                    'recu'               => 'bg-green-100 text-green-700',
                                    'facture'            => 'bg-emerald-100 text-emerald-800',
                                    'annule'             => 'bg-red-100 text-red-700',
                                ];
                                $statusLabels = [
                                    'brouillon'          => 'Brouillon',
                                    'envoye'             => 'Envoyée',
                                    'confirme'           => 'Confirmée',
                                    'partiellement_recu' => 'Partiel.',
                                    'recu'               => 'Reçue',
                                    'facture'            => 'Facturée',
                                    'annule'             => 'Annulée',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                            </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Notes --}}
            @if($supplier->notes)
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Notes internes
                </h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $supplier->notes }}</p>
            </div>
            @endif

            {{-- Delete zone --}}
            <div class="bg-white rounded-[4px] border border-red-200 p-5">
                <h2 class="text-base font-semibold text-red-700 mb-2">Zone dangereuse</h2>
                <p class="text-sm text-gray-500 mb-3">Cette action est irréversible.</p>
                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST"
                      onsubmit="return confirm('Supprimer définitivement le fournisseur « {{ addslashes($supplier->name) }} » ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer ce fournisseur
                    </button>
                </form>
            </div>

        </div>
    </div>

    {{-- Pièces jointes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <x-attachments.manager model="Supplier" :id="$supplier->id" />
    </div>

</div>
@endsection
