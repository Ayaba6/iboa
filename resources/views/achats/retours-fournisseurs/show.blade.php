@extends('layouts.erp')
@section('title', 'Retour ' . $return->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.retours-fournisseurs.index') }}" class="hover:text-gray-700">Retours fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $return->number }}</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $return->number }}</h1>
                @php $color = $return->statusColor(); @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                    {{ $return->statusLabel() }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Date de retour : {{ $return->returned_at?->format('d/m/Y') ?? '—' }}
                @if($return->reason) · Motif : {{ $return->reason }} @endif
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- PDF Avoir --}}
            <a href="{{ route('achats.retours-fournisseurs.pdf', $return) }}"
               class="bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Avoir PDF
            </a>

            {{-- Edit --}}
            @if($return->isEditable())
            @can('supplier_returns.create')
            <a href="{{ route('achats.retours-fournisseurs.edit', $return) }}"
               class="bg-amber-50 border border-amber-300 text-amber-700 hover:bg-amber-100 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Modifier
            </a>
            @endcan
            @endif

            {{-- Validate --}}
            @if($return->status === 'brouillon')
            @can('supplier_returns.validate')
            <form action="{{ route('achats.retours-fournisseurs.validate', $return) }}" method="POST"
                  onsubmit="return confirm('Valider ce retour ? Le stock sera ajusté.')">
                @csrf
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Valider
                </button>
            </form>
            @endcan
            {{-- Delete --}}
            <form action="{{ route('achats.retours-fournisseurs.destroy', $return) }}" method="POST"
                  onsubmit="return confirm('Supprimer ce retour ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="border border-gray-300 text-gray-600 hover:bg-red-50 hover:border-red-300 hover:text-red-600 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Supprimer
                </button>
            </form>
            @endif

            <a href="{{ route('achats.retours-fournisseurs.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                ← Retour
            </a>
        </div>
    </div>

    {{-- Main content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Left: details + items --}}
        <div class="lg:col-span-2 space-y-3">

            {{-- Supplier info --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Fournisseur</h2>
                <p class="text-lg font-semibold text-gray-900">{{ $return->supplier?->name ?? '—' }}</p>
                @if($return->supplierInvoice)
                <p class="text-sm text-gray-500 mt-1">
                    Facture liée :
                    <a href="{{ route('achats.factures-fournisseurs.show', $return->supplierInvoice) }}"
                       class="text-amber-600 hover:underline font-mono">
                        {{ $return->supplierInvoice->number }}
                    </a>
                </p>
                @endif
                @if($return->purchaseOrder)
                <p class="text-sm text-gray-500 mt-1">
                    Commande liée :
                    <a href="{{ route('achats.commandes.show', $return->purchaseOrder) }}"
                       class="text-amber-600 hover:underline font-mono">
                        {{ $return->purchaseOrder->number }}
                    </a>
                </p>
                @endif
            </div>

            {{-- Items table --}}
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Articles retournés</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-[#eef5f0] border-b border-gray-300">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                                <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                                <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">P.U. HT</th>
                                <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Rem.</th>
                                <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($return->items as $item)
                            <tr>
                                <td class="px-3 py-1.5">
                                    @if($item->product)
                                    <p class="font-medium text-gray-900">{{ $item->product->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $item->product->reference }}</p>
                                    @else
                                    <p class="text-gray-700">{{ $item->description ?: '—' }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">
                                    {{ number_format($item->quantity, 0, ',', ' ') }}
                                    @if($item->unit) <span class="text-gray-400 text-xs">{{ $item->unit->abbreviation }}</span> @endif
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">
                                    {{ number_format($item->unit_price, 0, ',', ' ') }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">
                                    {{ $item->discount_percent > 0 ? number_format($item->discount_percent, 1).'%' : '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">
                                    {{ number_format($item->line_total_ht, 0, ',', ' ') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Totals --}}
                <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <div class="w-72 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Sous-total HT</span>
                            <span class="font-medium tabular-nums">{{ number_format($return->subtotal_ht, 0, ',', ' ') }} FCFA</span>
                        </div>
                        @if($return->total_tax > 0)
                        <div class="flex justify-between text-gray-600">
                            <span>Taxes</span>
                            <span class="tabular-nums">{{ number_format($return->total_tax, 0, ',', ' ') }} FCFA</span>
                        </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 pt-2 font-bold text-gray-900 text-base">
                            <span>Total TTC</span>
                            <span class="tabular-nums">{{ number_format($return->total_ttc, 0, ',', ' ') }} FCFA</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right: metadata --}}
        <div class="space-y-3">

            {{-- Status card --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Statut</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Statut actuel</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                            {{ $return->statusLabel() }}
                        </span>
                    </div>
                    @if($return->validated_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Validé le</span>
                        <span class="text-gray-700">{{ $return->validated_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                    @if($return->validatedBy)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Par</span>
                        <span class="text-gray-700">{{ $return->validatedBy->name }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Notes --}}
            @if($return->notes)
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Notes</h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $return->notes }}</p>
            </div>
            @endif

            {{-- Meta --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Informations</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Créé par</span>
                        <span class="text-gray-700">{{ $return->createdBy?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Créé le</span>
                        <span class="text-gray-700">{{ $return->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
