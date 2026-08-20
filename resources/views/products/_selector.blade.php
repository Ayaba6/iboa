{{-- [SAGE parité] Panneau gauche « Articles » : sélection rapide des fiches --}}
<aside class="hidden xl:block w-72 shrink-0"
       x-data="{ q: '' }">
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden sticky top-4">
        <div class="px-3 py-2 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
            Articles
        </div>
        <div class="p-2 border-b border-gray-100">
            <input type="text" x-model="q" placeholder="Filtrer code / désignation…"
                   class="w-full h-7 px-2 border border-[#c3d3c9] rounded-[3px] text-[12px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
        </div>
        <div class="max-h-[540px] overflow-y-auto">
            <table class="w-full text-[11.5px]">
                <thead><tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Code article</th>
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Catégorie</th>
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Désignation</th>
                </tr></thead>
                <tbody>
                    @foreach(($selectorProducts ?? collect()) as $sp)
                    <tr class="border-b border-gray-100 hover:bg-emerald-50/60 cursor-pointer {{ isset($product) && $product->exists && $product->id === $sp->id ? 'bg-[#eef5f0]' : '' }}"
                        x-show="q === '' || '{{ mb_strtolower(($sp->code_article ?? '') . ' ' . $sp->name) }}'.includes(q.toLowerCase())"
                        onclick="window.location='{{ route('products.edit', $sp) }}'">
                        <td class="px-2 py-1 font-mono text-emerald-800 whitespace-nowrap">{{ $sp->code_article ?: $sp->reference }}</td>
                        <td class="px-2 py-1 text-gray-500 whitespace-nowrap">{{ $sp->family?->code ?? '—' }}</td>
                        <td class="px-2 py-1 text-gray-700 truncate max-w-[120px]">{{ $sp->name }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Le filtre ci-dessus est LOCAL : il ne cherche que dans les lignes
             chargées. Le dire évite qu'un article absent du panneau soit pris
             pour un article inexistant, et renvoie là où la recherche est réelle. --}}
        @php $chargees = ($selectorProducts ?? collect())->count(); @endphp
        <div class="px-3 py-1.5 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            @if(isset($selectorProductsTotal) && $selectorProductsTotal > $chargees)
                {{ $chargees }} article(s) sur {{ $selectorProductsTotal }} —
                <a href="{{ route('products.index') }}" class="underline hover:text-gray-700">rechercher dans tous</a>
            @else
                {{ $chargees }} article(s)
            @endif
        </div>
    </div>
</aside>
