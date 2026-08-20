@extends('layouts.erp')
@section('title', 'Bon de préparation '.$preparation->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.preparations.index') }}" class="hover:text-gray-700">Bons de préparation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $preparation->number }}</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 py-0 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR = $inp.' text-right font-mono tabular-nums';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $fmt  = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');

    $badge = [
        'brouillon'             => ['Brouillon',             'bg-gray-100 text-gray-700'],
        'a_preparer'            => ['À préparer',            'bg-slate-100 text-slate-700'],
        'en_preparation'        => ['En préparation',        'bg-blue-100 text-blue-700'],
        'partiellement_prepare' => ['Partiellement préparé', 'bg-amber-100 text-amber-800'],
        'prepare'               => ['Préparé',               'bg-indigo-100 text-indigo-700'],
        'controle'              => ['Contrôlé',              'bg-teal-100 text-teal-800'],
        'valide'                => ['Validé',                'bg-emerald-100 text-emerald-800'],
        'annule'                => ['Annulé',                'bg-red-100 text-red-700'],
    ];
    [$statusLabel, $statusCls] = $badge[$preparation->status] ?? [$preparation->status, 'bg-gray-100'];

    $editable   = in_array($preparation->status, ['brouillon','a_preparer','en_preparation','partiellement_prepare','controle'], true);
    $startable  = in_array($preparation->status, ['brouillon','a_preparer'], true);
    $pickable   = in_array($preparation->status, ['en_preparation','partiellement_prepare','controle'], true);
    $controlabl = in_array($preparation->status, ['prepare','partiellement_prepare'], true);
    $validable  = $preparation->status === 'controle';
    $cancelable = $preparation->isCancellable();

    $activeControl = $preparation->controls->whereNull('invalidated_at')->where('result','conforme')->first();
@endphp

<div class="space-y-3">
    <x-sales.module-nav />
    <x-validation-errors />

    {{-- En-tête + actions. Aucun bouton décoratif : chaque action affichée est
         possible dans l'état courant, les autres sont retirées, pas grisées. --}}
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <h2 class="text-[20px] font-bold text-gray-900">{{ $preparation->number }}</h2>
                <span class="inline-block px-2 py-0.5 rounded-[3px] text-[11px] font-semibold {{ $statusCls }}">{{ $statusLabel }}</span>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @if($startable)
                    @can('bon_preparations.update')
                    <form method="POST" action="{{ route('ventes.preparations.start', $preparation) }}" data-confirm="Lancer la préparation {{ addslashes($preparation->number) }} ?">
                        @csrf <x-form-guard />
                        <button type="submit" class="text-[13px] font-semibold text-white bg-blue-600 hover:bg-blue-700 px-4 py-1.5 rounded-[4px]">Lancer la préparation</button>
                    </form>
                    @endcan
                @endif

                @if($controlabl)
                    @can('bon_preparations.control')
                    <button type="button" onclick="document.getElementById('controlForm').classList.toggle('hidden')"
                            class="text-[13px] font-semibold text-teal-700 border border-teal-300 bg-white hover:bg-teal-50 px-4 py-1.5 rounded-[4px]">Contrôler</button>
                    @endcan
                @endif

                @if($validable)
                    @can('bon_preparations.validate')
                    <form method="POST" action="{{ route('ventes.preparations.validate', $preparation) }}" data-confirm="Valider {{ addslashes($preparation->number) }} ? Les quantités validées deviennent la source du bon de livraison.">
                        @csrf <x-form-guard />
                        <button type="submit" class="text-[13px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-1.5 rounded-[4px]">Valider</button>
                    </form>
                    @endcan
                @endif

                @if($preparation->status === 'valide')
                    @can('bon_preparations.validate')
                    <form method="POST" action="{{ route('ventes.preparations.delivery-note', $preparation) }}" data-confirm="Créer un bon de livraison depuis les quantités validées de {{ addslashes($preparation->number) }} ?">
                        @csrf <x-form-guard />
                        <button type="submit" class="text-[13px] font-semibold text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-1.5 rounded-[4px]">Créer le bon de livraison</button>
                    </form>
                    @endcan
                @endif

                @if($cancelable)
                    @can('bon_preparations.update')
                    <button type="button" onclick="document.getElementById('cancelForm').classList.toggle('hidden')"
                            class="text-[13px] font-semibold text-red-600 border border-red-200 bg-white hover:bg-red-50 px-4 py-1.5 rounded-[4px]">Annuler</button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="p-4 grid grid-cols-2 sm:grid-cols-5 gap-x-6 gap-y-2 text-[12px]">
            <div><span class="text-gray-500">Commande</span><br><a href="{{ route('ventes.commandes.show', $preparation->order_id) }}" class="font-mono font-semibold text-emerald-700 hover:underline">{{ $preparation->order?->number }}</a></div>
            <div><span class="text-gray-500">Client</span><br><span class="font-medium">{{ $preparation->order?->client?->name }}</span></div>
            <div><span class="text-gray-500">Dépôt</span><br><span class="font-mono">{{ $preparation->warehouse?->code ?? '—' }}</span></div>
            <div><span class="text-gray-500">Priorité</span><br>{{ ucfirst($preparation->priority) }}</div>
            <div><span class="text-gray-500">Date souhaitée</span><br>{{ $preparation->requested_date?->format('d/m/Y') ?? '—' }}</div>
        </div>

        {{-- Traçabilité des acteurs : trois personnes distinctes par construction. --}}
        <div class="px-4 pb-4 grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-2 text-[11px] text-gray-600">
            <div>Préparé par : <strong>{{ \App\Models\User::find($preparation->started_by)?->name ?? '—' }}</strong> {{ $preparation->started_at?->format('d/m/Y H:i') }}</div>
            <div>Contrôlé par : <strong>{{ \App\Models\User::find($preparation->controlled_by)?->name ?? '—' }}</strong> {{ $preparation->controlled_at?->format('d/m/Y H:i') }}</div>
            <div>Validé par : <strong>{{ \App\Models\User::find($preparation->validated_by)?->name ?? '—' }}</strong> {{ $preparation->validated_at?->format('d/m/Y H:i') }}</div>
            @if($preparation->status === 'annule')
            <div class="text-red-600">Annulé par : <strong>{{ \App\Models\User::find($preparation->cancelled_by)?->name ?? '—' }}</strong> — {{ $preparation->cancel_reason }}</div>
            @endif
        </div>
    </div>

    @if($controlabl)
    <div id="controlForm" class="hidden bg-white rounded-[4px] border border-teal-300">
        <div class="{{ $secH }}">Contrôle — le contrôleur doit être distinct du préparateur</div>
        <form method="POST" action="{{ route('ventes.preparations.control', $preparation) }}" class="p-4 space-y-3">
            @csrf <x-form-guard />
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                @foreach(['article'=>'Article','lot'=>'Lot','bobine'=>'Bobine','quantite'=>'Quantité','poids'=>'Poids','depot'=>'Dépôt','emplacement'=>'Emplacement','qualite'=>'Qualité','commande'=>'Commande','client'=>'Client'] as $k => $l)
                <label class="inline-flex items-center gap-2 text-[12px]">
                    <input type="checkbox" name="checkpoints[{{ $k }}]" value="1" class="rounded border-gray-300 text-teal-600">
                    <span>{{ $l }}</span>
                </label>
                @endforeach
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div><label class="{{ $lbl }}">Résultat</label>
                    <select name="result" class="{{ $inp }}">
                        <option value="conforme">Conforme</option>
                        <option value="ecart">Écart constaté</option>
                    </select>
                </div>
                <div class="sm:col-span-3"><label class="{{ $lbl }}">Observations</label><input type="text" name="notes" maxlength="1000" class="{{ $inp }}"></div>
            </div>
            <button type="submit" class="text-[13px] font-semibold text-white bg-teal-600 hover:bg-teal-700 px-5 py-1.5 rounded-[4px]">Enregistrer le contrôle</button>
        </form>
    </div>
    @endif

    @if($cancelable)
    <div id="cancelForm" class="hidden bg-white rounded-[4px] border border-red-300">
        <div class="px-4 py-1.5 border-b border-red-200 bg-red-50 text-[13px] font-bold text-red-800">
            Annulation — le document est conservé, jamais supprimé
        </div>
        <form method="POST" action="{{ route('ventes.preparations.cancel', $preparation) }}" class="p-4 flex items-end gap-3"
              data-confirm="Annuler {{ addslashes($preparation->number) }} ? Les réservations seront libérées et le reliquat redeviendra préparable.">
            @csrf <x-form-guard />
            <div class="flex-1"><label class="{{ $lbl }}">Motif d'annulation (obligatoire)</label><input type="text" name="reason" minlength="3" maxlength="500" required class="{{ $inp }}"></div>
            <button type="submit" class="text-[13px] font-semibold text-white bg-red-600 hover:bg-red-700 px-5 py-1.5 rounded-[4px]">Confirmer l'annulation</button>
        </form>
    </div>
    @endif

    {{-- Lignes : les neuf quantités sont montrées SÉPARÉMENT. Un écran qui
         n'afficherait qu'un « préparé » global masquerait l'écart. --}}
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="{{ $secH }}">Lignes et allocations</div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr>
                        <th class="text-left px-3 py-2 font-semibold">Article</th>
                        <th class="text-right px-3 py-2 font-semibold">Commandé</th>
                        <th class="text-right px-3 py-2 font-semibold">Déjà livré</th>
                        <th class="text-right px-3 py-2 font-semibold">À préparer</th>
                        <th class="text-right px-3 py-2 font-semibold">Alloué</th>
                        <th class="text-right px-3 py-2 font-semibold">Prélevé</th>
                        <th class="text-right px-3 py-2 font-semibold">Contrôlé</th>
                        <th class="text-right px-3 py-2 font-semibold">Validé</th>
                        <th class="text-right px-3 py-2 font-semibold">Écart</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach($preparation->items as $item)
                    <tr class="bg-white">
                        <td class="px-3 py-2 font-medium">{{ $item->orderItem?->description ?? $item->product?->name }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($item->qty_ordered) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($item->qty_previously_delivered) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ $fmt($item->qty_remaining_snapshot) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($item->qty_allocated) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($item->qty_picked) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($item->qty_controlled) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-emerald-700">{{ $fmt($item->qty_validated) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums {{ abs($item->variance_qty) > 0.0005 ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">{{ $fmt($item->variance_qty) }}</td>
                    </tr>

                    @if($item->variance_reason)
                    <tr class="bg-amber-50/60">
                        <td colspan="9" class="px-3 py-1.5 text-[11px] text-amber-800">Motif d'écart : {{ $item->variance_reason }}</td>
                    </tr>
                    @endif

                    {{-- Allocations : lot ou bobine, dépôt, coût historique figé. --}}
                    @foreach($item->allocations as $alloc)
                    <tr class="bg-gray-50/60 text-[11px]">
                        <td class="px-3 py-1.5 pl-8 text-gray-600">
                            @if($alloc->stockLot) Lot <span class="font-mono">{{ $alloc->stockLot->lot_number }}</span>
                            @elseif($alloc->coil) Bobine <span class="font-mono">{{ $alloc->coil->reference ?? $alloc->coil->lot_number }}</span>
                            @else <span class="text-red-600">source non renseignée</span> @endif
                        </td>
                        <td colspan="3" class="px-3 py-1.5 text-gray-500">
                            coût historique {{ $fmt($alloc->historical_unit_cost) }}
                            @if($alloc->stock_reservation_id) · réservation #{{ $alloc->stock_reservation_id }} @endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($alloc->quantity) }}</td>
                        <td colspan="3" class="px-3 py-1.5">
                            <span class="inline-block px-1.5 rounded-[3px] {{ $alloc->status === 'annulee' ? 'bg-red-100 text-red-700' : ($alloc->status === 'prelevee' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700') }}">{{ $alloc->status }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            @if($pickable && $alloc->status === 'allouee')
                                @can('bon_preparations.update')
                                <form method="POST" action="{{ route('ventes.preparations.pick', $preparation) }}" class="inline-flex items-center gap-1">
                                    @csrf <x-form-guard />
                                    <input type="hidden" name="allocation_id" value="{{ $alloc->id }}">
                                    <input type="number" step="0.001" min="0" name="picked_quantity" value="{{ $fmt($alloc->quantity) }}" class="h-7 w-24 px-1 border border-gray-300 rounded-[3px] text-right text-[11px] tabular-nums">
                                    <input type="text" name="variance_reason" placeholder="Motif si écart" class="h-7 w-40 px-1 border border-gray-300 rounded-[3px] text-[11px]">
                                    <button type="submit" class="h-7 px-2 text-[11px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-[3px]">Prélever</button>
                                </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    {{-- Allouer : uniquement tant que le bon est modifiable. --}}
                    @if($editable)
                        @can('bon_preparations.update')
                        <tr class="bg-white">
                            <td colspan="9" class="px-3 py-2 pl-8 border-t border-dashed border-gray-200">
                                <form method="POST" action="{{ route('ventes.preparations.allocate', $preparation) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf <x-form-guard />
                                    <input type="hidden" name="sales_picking_item_id" value="{{ $item->id }}">
                                    <input type="hidden" name="warehouse_id" value="{{ $preparation->warehouse_id }}">
                                    <div>
                                        <label class="{{ $lbl }}">Lot</label>
                                        <select name="stock_lot_id" class="h-8 py-0 px-2 border border-gray-300 rounded-[3px] text-[12px] w-56">
                                            <option value="">— Aucun —</option>
                                            @foreach($lots->where('product_id', $item->product_id) as $lot)
                                            <option value="{{ $lot->id }}">{{ $lot->lot_number }} ({{ $fmt($lot->quantity - $lot->reserved_quantity) }} dispo)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="{{ $lbl }}">Bobine</label>
                                        <select name="coil_id" class="h-8 py-0 px-2 border border-gray-300 rounded-[3px] text-[12px] w-56">
                                            <option value="">— Aucune —</option>
                                            @foreach($coils as $coil)
                                            <option value="{{ $coil->id }}">{{ $coil->reference ?? $coil->lot_number }} ({{ $fmt($coil->remaining_weight) }} kg)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="{{ $lbl }}">Quantité</label>
                                        <input type="number" step="0.001" min="0" name="quantity" class="h-8 py-0 px-2 border border-gray-300 rounded-[3px] text-right text-[12px] tabular-nums w-28">
                                    </div>
                                    <button type="submit" class="h-8 px-3 text-[12px] font-semibold text-emerald-700 border border-emerald-300 rounded-[4px] hover:bg-emerald-50">Allouer</button>
                                </form>
                            </td>
                        </tr>
                        @endcan
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Historique des contrôles, invalidés compris : la trace ne disparaît pas. --}}
    @if($preparation->controls->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="{{ $secH }}">Historique des contrôles</div>
        <table class="w-full text-[12px]">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-2 font-semibold">Date</th>
                    <th class="text-left px-4 py-2 font-semibold">Contrôleur</th>
                    <th class="text-left px-4 py-2 font-semibold">Résultat</th>
                    <th class="text-left px-4 py-2 font-semibold">Points vérifiés</th>
                    <th class="text-left px-4 py-2 font-semibold">État</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach($preparation->controls->sortByDesc('created_at') as $ctrl)
                <tr class="{{ $ctrl->invalidated_at ? 'bg-gray-50 text-gray-400' : '' }}">
                    <td class="px-4 py-2">{{ $ctrl->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2">{{ \App\Models\User::find($ctrl->controlled_by)?->name ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $ctrl->result === 'conforme' ? 'Conforme' : 'Écart' }}</td>
                    <td class="px-4 py-2 text-[11px]">{{ implode(', ', array_keys(array_filter((array) $ctrl->checkpoints))) ?: '—' }}</td>
                    <td class="px-4 py-2">
                        @if($ctrl->invalidated_at)
                            <span class="text-amber-700">Invalidé — {{ $ctrl->invalidated_reason }}</span>
                        @else
                            <span class="text-emerald-700 font-semibold">Actif</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
