{{-- [X3 parité] Panneau gauche « Ordre de fabrication » : sélection rapide des OF --}}
<aside class="hidden xl:block w-64 shrink-0" x-data="{ q: '' }">
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden sticky top-4">
        <div class="px-3 py-2 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
            Ordre de fabrication
        </div>
        <div class="p-2 border-b border-gray-100">
            <input type="text" x-model="q" placeholder="Filtrer N° / statut…"
                   class="w-full h-7 px-2 border border-[#c3d3c9] rounded-[3px] text-[12px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
        </div>
        <div class="max-h-[560px] overflow-y-auto">
            <table class="w-full text-[11.5px]">
                <thead><tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Site</th>
                    <th class="text-left font-bold px-2 py-1.5 uppercase">N° ordre</th>
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Statut</th>
                </tr></thead>
                <tbody>
                    @foreach(($selectorOrders ?? collect()) as $so)
                    <tr class="border-b border-gray-100 hover:bg-emerald-50/60 cursor-pointer {{ isset($order) && $order->exists && $order->id === $so->id ? 'bg-[#eef5f0]' : '' }}"
                        x-show="q === '' || '{{ mb_strtolower(($so->number ?? '') . ' ' . $so->statusLabel()) }}'.includes(q.toLowerCase())"
                        onclick="window.location='{{ route('production.orders.show', $so) }}'">
                        <td class="px-2 py-1 text-gray-500 whitespace-nowrap font-mono">{{ $so->site_production ?? '01' }}</td>
                        <td class="px-2 py-1 font-mono text-emerald-800 whitespace-nowrap">{{ $so->number }}</td>
                        <td class="px-2 py-1 text-gray-600 whitespace-nowrap">{{ $so->statusLabel() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-1.5 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            {{ ($selectorOrders ?? collect())->count() }} OF récents
        </div>
    </div>
</aside>
