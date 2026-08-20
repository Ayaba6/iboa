@extends('layouts.erp')
@section('title', 'Nouveau bon de préparation')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.preparations.index') }}" class="hover:text-gray-700">Bons de préparation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 py-0 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR = $inp.' text-right font-mono tabular-nums';
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
@endphp

<div class="max-w-6xl space-y-3">
    <x-sales.module-nav />
    <x-validation-errors />

    {{-- Étape 1 : choisir la commande. Le reliquat n'est connu qu'après. --}}
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="px-4 py-2.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">
            1. Commande à préparer
        </div>
        <form method="GET" class="p-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[22rem]">
                <label class="{{ $lbl }}">Commande confirmée</label>
                <select name="order_id" class="{{ $inp }}" onchange="this.form.submit()">
                    <option value="">— Choisir —</option>
                    @foreach($orders as $o)
                    <option value="{{ $o->id }}" @selected($order && $order->id === $o->id)>
                        {{ $o->number }} — {{ $o->client?->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-8 px-4 text-[12.5px] font-semibold text-emerald-700 border border-emerald-300 rounded-[4px] hover:bg-emerald-50">Charger</button>
        </form>
    </div>

    @if($order)
    <form method="POST" action="{{ route('ventes.preparations.store') }}">
        @csrf
        <x-form-guard />
        <input type="hidden" name="order_id" value="{{ $order->id }}">
        {{-- Clé durable : un double envoi ne crée qu'un seul bon. --}}
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', 'PICK-'.\Illuminate\Support\Str::uuid()) }}">

        <div class="bg-white rounded-[4px] border border-gray-300 mb-3">
            <div class="px-4 py-2.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">
                2. En-tête
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="{{ $lbl }}">Dépôt de prélèvement</label>
                    <select name="warehouse_id" class="{{ $inp }}">
                        <option value="">— Aucun —</option>
                        @foreach(\App\Models\Warehouse::orderBy('code')->get() as $w)
                        <option value="{{ $w->id }}" @selected(old('warehouse_id', $order->delivery_warehouse_id) == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Priorité</label>
                    <select name="priority" class="{{ $inp }}">
                        @foreach(['normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('priority') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Date souhaitée</label>
                    <input type="date" name="requested_date" value="{{ old('requested_date') }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Notes</label>
                    <input type="text" name="notes" maxlength="1000" value="{{ old('notes') }}" class="{{ $inp }}">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300">
            <div class="px-4 py-2.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">
                3. Lignes — quantités à préparer
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-2 font-semibold">Article</th>
                            <th class="text-right px-4 py-2 font-semibold">Commandé</th>
                            <th class="text-right px-4 py-2 font-semibold">Déjà livré</th>
                            <th class="text-right px-4 py-2 font-semibold">Reliquat préparable</th>
                            <th class="text-right px-4 py-2 font-semibold w-40">À préparer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($order->items as $i => $item)
                        @php $rem = $remaining[$item->id] ?? 0; @endphp
                        <tr class="{{ $rem <= 0 ? 'bg-gray-50 text-gray-400' : '' }}">
                            <td class="px-4 py-2">{{ $item->description }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($item->quantity) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($item->delivered_quantity) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold">{{ $fmt($rem) }}</td>
                            <td class="px-4 py-2 text-right">
                                @if($rem > 0)
                                    <input type="hidden" name="lines[{{ $i }}][order_item_id]" value="{{ $item->id }}">
                                    {{-- `max` reflète le reliquat, mais la garde qui fait foi
                                         est celle du service : l'attribut HTML n'est qu'un confort. --}}
                                    <input type="number" step="0.001" min="0" max="{{ $rem }}"
                                           name="lines[{{ $i }}][quantity]"
                                           value="{{ old("lines.$i.quantity", $rem) }}" class="{{ $inpR }}">
                                @else
                                    <span class="text-[11.5px]">Rien à préparer</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-end gap-2">
                <a href="{{ route('ventes.preparations.index') }}"
                   class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 px-4 py-1.5 rounded-[4px]">Abandon</a>
                <button type="submit"
                        class="text-[13px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-1.5 rounded-[4px]">
                    Créer le bon
                </button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
