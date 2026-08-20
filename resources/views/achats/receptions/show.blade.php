@extends('layouts.erp')
@section('title', 'Réception ' . $reception->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.receptions.index') }}" class="hover:text-gray-700">Réceptions</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $reception->number }}</span>
@endsection

@section('content')
<div class="space-y-3" x-data="receptionApp()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-[15px] font-bold text-gray-900">{{ $reception->number }}</h1>
                @php
                    [$badgeClass, $badgeLabel] = match($reception->status) {
                        'valide'  => ['bg-emerald-100 text-emerald-700 border border-emerald-200', 'Validé'],
                        'annule'  => ['bg-red-100 text-red-700 border border-red-200', 'Annulé'],
                        default   => ['bg-amber-100 text-amber-700 border border-amber-200', 'Brouillon'],
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold {{ $badgeClass }}">
                    {{ $badgeLabel }}
                </span>
            </div>
            <p class="text-[13px] text-gray-500 mt-0.5">
                Fournisseur : <strong>{{ $reception->supplier?->name }}</strong>
                @if($reception->purchaseOrder)
                    · BC : <a href="{{ route('achats.commandes.show', $reception->purchaseOrder) }}"
                               class="text-emerald-700 hover:underline font-mono">{{ $reception->purchaseOrder->number }}</a>
                @endif
            </p>
        </div>

        @if($reception->status === 'brouillon')
            <button @click="showValidateModal = true"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-medium px-5 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Valider & Entrer en stock
            </button>
        @endif
    </div>

    {{-- Info cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-[12px] text-gray-500 uppercase tracking-wide mb-1">Date réception</p>
            <p class="font-semibold text-gray-900">{{ $reception->received_at?->format('d/m/Y') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-[12px] text-gray-500 uppercase tracking-wide mb-1">Type</p>
            <p class="font-semibold text-gray-900">{{ ucfirst($reception->type ?? 'totale') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-[12px] text-gray-500 uppercase tracking-wide mb-1">Articles</p>
            <p class="font-semibold text-gray-900">{{ $reception->items->count() }} ligne(s)</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-[12px] text-gray-500 uppercase tracking-wide mb-1">Créé par</p>
            <p class="font-semibold text-gray-900 truncate">{{ $reception->createdBy?->name ?? '—' }}</p>
        </div>
    </div>

    @if($reception->status === 'valide')
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] px-3 py-1.5 flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-[13px] text-emerald-800">
                Validé le <strong>{{ $reception->validated_at?->format('d/m/Y à H:i') }}</strong>
                par <strong>{{ $reception->validatedBy?->name ?? '—' }}</strong>
                · Entrepôt : <strong>{{ $warehouses->find($reception->warehouse_id)?->name ?? '—' }}</strong>
            </div>
        </div>

        {{-- Production : générer les bobines matière depuis cette réception --}}
        @can('production.create')
        @php $coilsGenerated = \App\Modules\Production\Models\Coil::where('reception_id', $reception->id)->exists(); @endphp
        <div class="bg-orange-50 border border-orange-200 rounded-[4px] px-3 py-1.5 flex items-center justify-between gap-3">
            <div class="text-[13px] text-orange-800">
                <strong>Production</strong> — créer les bobines (matières premières) en stock à partir des articles reçus.
            </div>
            @can('production.update')
                <a href="{{ route('qualite.inspections.create', ['type' => 'reception', 'reception_id' => $reception->id]) }}" class="text-[12px] text-emerald-700 hover:underline font-medium mr-3">+ Contrôle qualité</a>
            @endcan
            @if($coilsGenerated)
                <span class="text-[12px] text-emerald-700 font-medium">✓ Bobines déjà générées</span>
            @else
                <form method="POST" action="{{ route('production.receptions.coils', $reception) }}">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Générer les bobines
                    </button>
                </form>
            @endif
        </div>
        @endcan
    @endif

    {{-- Items table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Articles reçus</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté attendue</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté reçue</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">P.U. achat</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">N° lot</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Péremption</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qualité</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($reception->items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5">
                                <div class="font-medium text-gray-900">{{ $item->product?->name ?? $item->description }}</div>
                                @if($item->product?->reference)
                                    <div class="text-[12px] text-gray-400 font-mono">{{ $item->product->reference }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right text-gray-700">
                                {{ number_format($item->expected_quantity, 2, ',', ' ') }}
                                <span class="text-gray-400 text-[12px]">{{ $item->unit?->abbreviation }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right font-semibold
                                {{ (float)$item->received_quantity < (float)$item->expected_quantity ? 'text-amber-600' : 'text-emerald-700' }}">
                                {{ number_format($item->received_quantity, 2, ',', ' ') }}
                                <span class="text-gray-400 font-normal text-[12px]">{{ $item->unit?->abbreviation }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right text-gray-700 hidden md:table-cell">
                                {{ number_format($item->unit_cost, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-3 py-1.5 text-gray-500 font-mono text-[12px] hidden lg:table-cell">
                                {{ $item->lot_number ?? '—' }}
                            </td>
                            <td class="px-3 py-1.5 text-gray-500 text-[12px] hidden lg:table-cell">
                                {{ $item->expiry_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                @php
                                    $qBadge = match($item->quality_status ?? 'accepte') {
                                        'accepte' => 'bg-emerald-100 text-emerald-700',
                                        'rejete'  => 'bg-red-100 text-red-700',
                                        default   => 'bg-gray-100 text-gray-600',
                                    };
                                    $qLabel = match($item->quality_status ?? 'accepte') {
                                        'accepte' => 'Accepté',
                                        'rejete'  => 'Rejeté',
                                        default   => $item->quality_status,
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $qBadge }}">{{ $qLabel }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Validate Modal --}}
    @if($reception->status === 'brouillon')
    <div x-show="showValidateModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showValidateModal = false"></div>

        <div class="relative bg-white rounded-[4px] shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100">

            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-100">
                <h3 class="font-semibold text-[15px] text-gray-900">Valider la réception & entrée en stock</h3>
                <button @click="showValidateModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('achats.receptions.validate', $reception) }}" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
                @csrf

                <div class="flex-1 overflow-y-auto px-3 py-1.5 space-y-4">

                    {{-- Warehouse selector --}}
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">
                            Entrepôt de destination <span class="text-red-500">*</span>
                        </label>
                        <select name="warehouse_id" required
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">-- Choisir un entrepôt --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Items table --}}
                    <div class="border border-gray-200 rounded-[4px] overflow-hidden">
                        <table class="w-full text-[13px]">
                            <thead class="bg-[#eef5f0] border-b border-gray-300">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produit</th>
                                    <th class="px-3 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Attendu</th>
                                    <th class="px-3 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Reçu</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden sm:table-cell">N° lot</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden sm:table-cell">Péremption</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($reception->items as $item)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-800 text-[12px]">{{ $item->product?->name ?? $item->description }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-600 text-[12px]">
                                            {{ number_format($item->expected_quantity, 2, ',', ' ') }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" name="items[{{ $item->id }}][received_quantity]"
                                                   value="{{ $item->expected_quantity }}"
                                                   min="0" step="0.01" required
                                                   class="w-24 border border-gray-300 rounded px-2 py-1 text-[13px] text-right focus:ring-1 focus:ring-emerald-500">
                                        </td>
                                        <td class="px-3 py-2 hidden sm:table-cell">
                                            @if($item->product?->has_lot_number)
                                                <input type="text" name="items[{{ $item->id }}][lot_number]"
                                                       value="{{ $item->lot_number }}"
                                                       placeholder="N° lot"
                                                       class="w-28 border border-gray-300 rounded px-2 py-1 text-[12px] focus:ring-1 focus:ring-emerald-500">
                                            @else
                                                <span class="text-gray-400 text-[12px]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 hidden sm:table-cell">
                                            @if($item->product?->has_expiry_date)
                                                <input type="date" name="items[{{ $item->id }}][expiry_date]"
                                                       value="{{ $item->expiry_date?->format('Y-m-d') }}"
                                                       class="border border-gray-300 rounded px-2 py-1 text-[12px] focus:ring-1 focus:ring-emerald-500">
                                            @else
                                                <span class="text-gray-400 text-[12px]">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- [SAGE parité] Pièces jointes à la validation --}}
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Pièces jointes (BL signé, photos…)</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-gray-300 rounded-[4px] px-3 py-2 cursor-pointer
                                      file:mr-3 file:py-1 file:px-2.5 file:border-0 file:bg-emerald-50 file:text-emerald-700
                                      file:rounded file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                        <p class="text-[12px] text-gray-400 mt-1">PDF, images, Word, Excel — max 5 Mo par fichier.</p>
                    </div>

                </div>

                <div class="flex justify-end gap-3 px-3 py-1.5 border-t border-gray-100">
                    <button type="button" @click="showValidateModal = false"
                            class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[13px] px-3 py-1.5 rounded-[4px] transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                        Confirmer l'entrée en stock
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Impact stock réel : visible sur les réceptions validées --}}
    @if($reception->status === 'valide')
        @php
            $stockMovements = \App\Models\StockMovement::where('reference_type', 'reception')
                ->where('reference_id', $reception->id)
                ->with('product:id,reference,name', 'warehouse:id,code,name')
                ->orderBy('id')
                ->get();
            $linesWithProduct = $reception->items->where('product_id', '!=', null)->count();
            $linesWithoutProduct = $reception->items->whereNull('product_id')->count();
        @endphp
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Impact stock
                </h2>
                <span class="text-[12px] text-gray-500">
                    {{ $stockMovements->count() }} mouvement(s) généré(s)
                </span>
            </div>

            @if($linesWithoutProduct > 0)
            <div class="px-5 py-3 bg-amber-50 border-b border-amber-100 text-[12px] text-amber-800 flex items-start gap-2">
                <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <strong>{{ $linesWithoutProduct }} ligne(s) sans produit catalogué</strong> — aucun mouvement de stock créé pour ces lignes
                    (description libre ou service). Pour qu'une ligne impacte le stock, elle doit être liée à un article du catalogue
                    lors de la création du bon de commande.
                </div>
            </div>
            @endif

            @if($stockMovements->isEmpty())
                <div class="px-5 py-8 text-center text-[13px] text-gray-400">
                    Aucun mouvement de stock généré par cette réception.
                </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-100 text-[13px]">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Entrepôt</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Quantité</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Coût unit.</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Lot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($stockMovements as $m)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-3 py-2.5">
                                <span class="font-mono text-[12px] text-gray-500">{{ $m->product?->reference ?? '—' }}</span>
                                <div class="text-gray-900">{{ $m->product?->name ?? '—' }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-gray-700">{{ $m->warehouse?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[12px] font-bold">
                                    + {{ number_format($m->quantity, 2, ',', ' ') }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">
                                {{ number_format($m->unit_cost ?? 0, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 text-[12px]">{{ $m->occurred_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-gray-500 text-[12px] font-mono">{{ $m->lot_number ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    @endif

    {{-- [SAGE parité] Documents / pièces jointes --}}
    @if($reception->attachments->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Documents / pièces jointes</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-10">#</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Fichier</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                    <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Taille</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($reception->attachments as $i => $att)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                    <td class="px-3 py-2.5 text-gray-700 font-mono text-[12px]">{{ $att->filename }}</td>
                    <td class="px-3 py-2.5 text-gray-500 text-[12px]">{{ $att->mime_type }}</td>
                    <td class="px-3 py-2.5 text-right text-gray-500 text-[12px] tabular-nums">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Audit timeline --}}
    <x-audit.timeline :model="\App\Models\Reception::class" :id="$reception->id"
                      title="Historique de la réception" />

</div>

@push('scripts')
<script>
function receptionApp() {
    return {
        showValidateModal: false,
    };
}
</script>
@endpush
@endsection
