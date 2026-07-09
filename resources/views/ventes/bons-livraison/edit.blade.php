@extends('layouts.erp')
@section('title', 'Modifier BL '.$deliveryNote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-livraison.index') }}" class="hover:text-gray-700">Bons de livraison</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-livraison.show', $deliveryNote) }}" class="hover:text-gray-700">{{ $deliveryNote->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
@php
    $d = $deliveryNote;
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $ro    = 'w-full h-8 px-2 border border-gray-200 rounded-[3px] text-[13px] bg-gray-50 text-gray-600';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div class="max-w-5xl">
    <form method="POST" enctype="multipart/form-data"
          action="{{ route('ventes.bons-livraison.update', $d) }}"
          x-data="{ tab: 'entete' }" class="space-y-3">
        @csrf
        @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Livraison : Création complète
                    <span class="font-mono text-emerald-700 ml-1">{{ $d->number }}</span>
                    <span class="ml-2 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-{{ $d->status_color }}-100 text-{{ $d->status_color }}-700 align-middle">{{ $d->status_label }}</span>
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('ventes.bons-livraison.show', $d) }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['entete'=>'Entête','lignes'=>'Lignes','transport'=>'Transport','docs'=>'Documents'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ ENTÊTE ═══════════ --}}
            <div x-show="tab === 'entete'" class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Identification du bon de livraison</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Numéro</label><input type="text" value="{{ $d->number }}" class="{{ $ro }} font-mono" readonly></div>
                        <div class="sm:col-span-5"><label class="{{ $lbl }}">Client</label><input type="text" value="{{ $d->client?->name }}" class="{{ $ro }}" readonly></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date</label><input type="text" value="{{ optional($d->issued_at)->format('d/m/Y') }}" class="{{ $ro }}" readonly></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Devise</label><input type="text" value="{{ $d->currency_code ?? 'XOF' }}" class="{{ $ro }} font-mono" readonly></div>

                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Commande liée</label><input type="text" value="{{ $d->order?->number ?? '—' }}" class="{{ $ro }} font-mono" readonly></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Dépôt d'expédition</label><input type="text" value="{{ $d->warehouse?->name ?? '—' }}" class="{{ $ro }}" readonly></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Total quantités</label><input type="text" value="{{ number_format((float) $d->total_quantity, 2, ',', ' ') }}" class="{{ $ro }} text-right font-mono" readonly></div>

                        <div class="sm:col-span-12"><label class="{{ $lbl }}">Notes / observations</label><textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes', $d->notes) }}</textarea></div>
                    </div>
                </section>
                <p class="text-[11.5px] text-gray-400 mt-2">Livraison partielle : réduisez les quantités dans l'onglet <strong>Lignes</strong> pour n'expédier qu'une partie de la commande. Les reliquats resteront livrables sur un prochain BL.</p>
            </div>

            {{-- ═══════════ LIGNES ═══════════ --}}
            <div x-show="tab === 'lignes'" x-cloak class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Lignes de livraison</div>
                    <table class="w-full text-[12.5px]">
                        <thead><tr class="bg-gray-50 text-gray-600 border-b border-gray-200">
                            <th class="text-left font-bold px-3 py-2">Article</th>
                            <th class="text-right font-bold px-3 py-2 w-32">Qté commandée</th>
                            <th class="text-right font-bold px-3 py-2 w-28">Déjà livrée</th>
                            <th class="text-right font-bold px-3 py-2 w-36">Qté ce BL</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($d->items as $i => $item)
                            @php
                                $orderItem    = $item->orderItem;
                                $orderedQty   = $orderItem ? (float) $orderItem->quantity          : (float) $item->quantity;
                                $deliveredQty = $orderItem ? (float) $orderItem->delivered_quantity : 0;
                                $maxQty       = max(0, $orderedQty - $deliveredQty);
                            @endphp
                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-gray-900">{{ $item->description }}</p>
                                    @if($item->product?->reference)<p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $item->product->reference }}</p>@endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ number_format($orderedQty, 2, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ $deliveredQty > 0 ? 'text-emerald-700 font-medium' : 'text-gray-400' }}">{{ number_format($deliveredQty, 2, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" name="items[{{ $i }}][quantity]"
                                           value="{{ old("items.{$i}.quantity", $item->quantity) }}"
                                           min="0" max="{{ $maxQty }}" step="0.001"
                                           class="{{ $inpR }} w-28 inline-block @error("items.{$i}.quantity") border-red-400 @enderror"
                                           {{ $maxQty <= 0 ? 'disabled' : '' }}>
                                    @if($maxQty <= 0)<p class="text-[11px] text-gray-400 mt-0.5">Déjà livré</p>@endif
                                    @error("items.{$i}.quantity")<p class="text-[11px] text-red-500 mt-0.5">{{ $message }}</p>@enderror
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            </div>

            {{-- ═══════════ TRANSPORT ═══════════ --}}
            <div x-show="tab === 'transport'" x-cloak class="p-4 space-y-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Destination</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-8"><label class="{{ $lbl }}">Adresse de livraison</label><input type="text" name="delivery_address" maxlength="500" value="{{ old('delivery_address', $d->delivery_address) }}" class="{{ $inp }}" placeholder="Adresse complète du destinataire"></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Contact destinataire</label><input type="text" name="delivery_contact" maxlength="120" value="{{ old('delivery_contact', $d->delivery_contact) }}" class="{{ $inp }}" placeholder="Nom + téléphone"></div>
                    </div>
                </section>
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Expédition</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Transporteur</label><input type="text" name="carrier" maxlength="120" value="{{ old('carrier', $d->carrier) }}" class="{{ $inp }}" placeholder="Nom du transporteur"></div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Mode d'expédition</label>
                            <div class="relative"><select name="shipping_mode" class="{{ $lk }}">
                                @php $sm = old('shipping_mode', $d->shipping_mode); @endphp
                                <option value="">—</option>
                                @foreach(['route'=>'Route','rail'=>'Rail','maritime'=>'Maritime','aerien'=>'Aérien','coursier'=>'Coursier'] as $mv => $ml)
                                <option value="{{ $mv }}" @selected($sm===$mv)>{{ $ml }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Incoterm</label>
                            <div class="relative"><select name="incoterm" class="{{ $lk }}">
                                @php $ic = old('incoterm', $d->incoterm); @endphp
                                <option value="">—</option>
                                @foreach(['EXW','FCA','FAS','FOB','CFR','CIF','CPT','CIP','DAP','DPU','DDP'] as $iv)
                                <option value="{{ $iv }}" @selected($ic===$iv)>{{ $iv }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-4"><label class="{{ $lbl }}">N° de suivi</label><input type="text" name="tracking_number" maxlength="120" value="{{ old('tracking_number', $d->tracking_number) }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Poids (kg)</label><input type="number" step="0.001" min="0" name="weight_kg" value="{{ old('weight_kg', $d->weight_kg) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Nb colis</label><input type="number" step="1" min="0" name="packages_count" value="{{ old('packages_count', $d->packages_count) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Livraison prévue le</label><input type="date" name="expected_delivery_at" value="{{ old('expected_delivery_at', optional($d->expected_delivery_at)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ DOCUMENTS ═══════════ --}}
            <div x-show="tab === 'docs'" x-cloak class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Documents / pièces jointes</div>
                    <div class="p-4 space-y-4">
                        @if($d->attachments->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-gray-50 text-gray-600">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                            </tr></thead>
                            <tbody>
                                @foreach($d->attachments as $i => $att)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-3 py-1.5 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-3 py-1.5 text-gray-700 font-mono">{{ $att->filename }}</td>
                                    <td class="px-3 py-1.5 text-gray-500">{{ $att->mime_type }}</td>
                                    <td class="px-3 py-1.5 text-gray-500 tabular-nums">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                        <div>
                            <label class="{{ $lbl }}">Ajouter des pièces jointes</label>
                            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                   class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                                          file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                                          file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                            <p class="text-[11px] text-gray-400 mt-1">Bon de transport, décharge signée, photos — max 5 Mo par fichier.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
