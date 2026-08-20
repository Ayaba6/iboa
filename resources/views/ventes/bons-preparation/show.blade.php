@extends('layouts.erp')
@section('title', 'Bon de préparation ' . $bonPreparation->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-preparation.index') }}" class="hover:text-gray-700">Bons de préparation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $bonPreparation->number }}</span>
@endsection

@section('content')
@php $order = $bonPreparation->order; @endphp
<div class="space-y-3 max-w-4xl">

    @if(session('success'))
        <div class="rounded-[4px] bg-green-50 border border-green-200 px-3 py-1.5 text-[13px] text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-[4px] bg-red-50 border border-red-200 px-3 py-1.5 text-[13px] text-red-800">{{ session('error') }}</div>
    @endif

    {{-- En-tête --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-[15px] font-bold text-gray-900">{{ $bonPreparation->number }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">Créé le {{ $bonPreparation->created_at->format('d/m/Y à H:i') }}</p>
            </div>
            @php
                $statusStyles = [
                    'en_attente' => 'bg-amber-100 text-amber-800',
                    'en_cours'   => 'bg-blue-100 text-blue-800',
                    'charge'     => 'bg-green-100 text-green-800',
                    'annule'     => 'bg-red-100 text-red-800',
                ];
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[13px] font-semibold {{ $statusStyles[$bonPreparation->status] ?? 'bg-gray-100 text-gray-800' }}">
                {{ $bonPreparation->status_label }}
            </span>
        </div>

        <div class="mt-5 grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Commande</p>
                <a href="{{ route('ventes.commandes.show', $order->id) }}"
                   class="font-semibold text-emerald-800 hover:underline">{{ $order->number }}</a>
            </div>
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Client</p>
                <p class="font-medium text-gray-900">{{ $order->client?->displayName() }}</p>
            </div>
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Mode de règlement</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[12px] font-medium
                    {{ $bonPreparation->payment_mode === 'cash' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                    {{ $bonPreparation->payment_mode_label }}
                </span>
            </div>
            @if($bonPreparation->payment_mode === 'cash')
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Montant réglé</p>
                <p class="font-semibold text-gray-900">{{ number_format((int)$bonPreparation->payment_amount, 0, ',', ' ') }} FCFA</p>
            </div>
            @if($bonPreparation->payment_reference)
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Référence caisse</p>
                <p class="font-mono text-gray-700">{{ $bonPreparation->payment_reference }}</p>
            </div>
            @endif
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Enregistré par</p>
                <p class="text-gray-700">{{ $bonPreparation->paymentRecordedBy?->name ?? '—' }}</p>
            </div>
            @else
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Validé par</p>
                <p class="text-gray-700">{{ $bonPreparation->validatedBy?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Date validation</p>
                <p class="text-gray-700">{{ $bonPreparation->validated_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            @endif
            @if($bonPreparation->loaded_by)
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Chargé par</p>
                <p class="text-gray-700">{{ $bonPreparation->loadedBy?->name }}</p>
            </div>
            <div>
                <p class="text-[12px] text-gray-400 uppercase">Chargé le</p>
                <p class="text-gray-700">{{ $bonPreparation->loaded_at?->format('d/m/Y H:i') }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Articles à préparer --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Articles à charger</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Quantité</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prix unitaire</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($order->items as $item)
                <tr>
                    <td class="px-3 py-1.5 text-gray-900 font-medium">{{ $item->description }}</td>
                    <td class="px-3 py-1.5 text-gray-500 font-mono text-[12px]">{{ $item->product?->reference ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format((float)$item->quantity, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format((int)$item->unit_price, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ number_format((int)$item->line_total_ht, 0, ',', ' ') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="4" class="px-3 py-1.5 text-right text-[13px] font-semibold text-gray-700">Total HT</td>
                    <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format((int)$order->subtotal_ht, 0, ',', ' ') }} FCFA</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Actions --}}
    @can('bon_preparations.update')
    <div class="flex gap-3">
        @if($bonPreparation->status === 'en_attente')
        <form method="POST" action="{{ route('ventes.bons-preparation.start-loading', $bonPreparation) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 text-white text-[13px] font-semibold rounded-[4px] hover:bg-emerald-800 transition">
                Démarrer le chargement
            </button>
        </form>
        @elseif($bonPreparation->status === 'en_cours')
        <form method="POST" action="{{ route('ventes.bons-preparation.finish-loading', $bonPreparation) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 text-white text-[13px] font-semibold rounded-[4px] hover:bg-green-700 transition">
                Terminer le chargement
            </button>
        </form>
        @elseif($bonPreparation->status === 'charge')
        <div class="flex items-center gap-2 px-5 py-2.5 bg-green-50 text-green-700 text-[13px] font-semibold rounded-[4px] border border-green-200">
            Chargement terminé — créer le bon de livraison dans la commande
        </div>
        @elseif($bonPreparation->status === 'annule')
        <div class="flex flex-col gap-0.5 px-5 py-2.5 bg-red-50 text-red-700 text-[13px] rounded-[4px] border border-red-200">
            <span class="font-semibold">Bon annulé
                @if($bonPreparation->cancelled_at) le {{ $bonPreparation->cancelled_at->format('d/m/Y à H:i') }} @endif
                @if($bonPreparation->cancelledBy) par {{ $bonPreparation->cancelledBy->name }} @endif
            </span>
            @if($bonPreparation->cancellation_reason)
            <span class="text-[12px] font-normal">Motif : {{ $bonPreparation->cancellation_reason }}</span>
            @endif
        </div>
        @endif

        <a href="{{ route('ventes.commandes.show', $order->id) }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 text-[13px] font-medium rounded-[4px] hover:bg-gray-50 transition">
            Voir la commande
        </a>

        {{-- [Ventes] Impression du bon de préparation --}}
        <a href="{{ route('ventes.bons-preparation.pdf', $bonPreparation) }}?stream=1" target="_blank"
           class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 text-[13px] font-medium rounded-[4px] hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Imprimer
        </a>
    </div>
    @endcan

    {{-- [Ventes §17] Annulation motivée. Bloc séparé du précédent : la permission
         `bon_preparations.cancel` est distincte de `update`, pour que le magasinier
         qui exécute le chargement ne puisse pas écarter le document qui l'autorise. --}}
    @can('bon_preparations.cancel')
    @if($bonPreparation->isCancellable())
    <div class="mt-4 pt-4 border-t border-gray-200" x-data="{ open: false }">
        <button type="button" @click="open = true"
                class="inline-flex items-center gap-2 px-5 py-2.5 border border-red-300 text-red-600 text-[13px] font-semibold rounded-[4px] hover:bg-red-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Annuler ce bon
        </button>
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                <h3 class="font-semibold text-gray-900 mb-1">Annuler le bon {{ $bonPreparation->number }}</h3>
                <p class="text-[12px] text-gray-500 mb-4">Le document reste consultable avec son numéro. Le motif, votre nom et la date sont enregistrés.</p>
                <form method="POST" action="{{ route('ventes.bons-preparation.cancel', $bonPreparation) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Motif <span class="text-red-500">*</span></label>
                        <textarea name="motif" rows="3" required minlength="5" maxlength="500"
                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-red-500"
                                  placeholder="Ex. : commande annulée, bon créé en double, client absent…"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="open = false"
                                class="px-4 py-2 border border-gray-300 text-gray-700 text-[13px] font-medium rounded-[4px] hover:bg-gray-50">Fermer</button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white text-[13px] font-semibold rounded-[4px] hover:bg-red-700">Confirmer l'annulation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endcan

</div>
@endsection
