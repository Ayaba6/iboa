{{-- [SAGE parité] Panneau gauche « Familles » : sélection rapide des fiches --}}
<aside class="hidden xl:block w-72 shrink-0"
       x-data="{ q: '' }">
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden sticky top-4">
        <div class="px-3 py-2 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
            Familles
        </div>
        <div class="p-2 border-b border-gray-100">
            <input type="text" x-model="q" placeholder="Filtrer code / intitulé…"
                   class="w-full h-7 px-2 border border-[#c3d3c9] rounded-[3px] text-[12px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
        </div>
        <div class="max-h-[540px] overflow-y-auto">
            <table class="w-full text-[11.5px]">
                <thead><tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Code famille</th>
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Intitulé</th>
                    <th class="text-left font-bold px-2 py-1.5 uppercase">Niveau</th>
                </tr></thead>
                <tbody>
                    @foreach(($selectorFamilies ?? collect()) as $sf)
                    <tr class="border-b border-gray-100 hover:bg-emerald-50/60 cursor-pointer {{ isset($family) && $family->exists && $family->id === $sf->id ? 'bg-[#eef5f0]' : '' }}"
                        x-show="q === '' || '{{ mb_strtolower(($sf->code ?? '') . ' ' . $sf->name) }}'.includes(q.toLowerCase())"
                        onclick="window.location='{{ route('product-families.edit', $sf) }}'">
                        <td class="px-2 py-1 font-mono text-emerald-800 whitespace-nowrap">{{ $sf->code ?: '—' }}</td>
                        <td class="px-2 py-1 text-gray-700 truncate max-w-[110px]">{{ $sf->name }}</td>
                        <td class="px-2 py-1 text-gray-500 truncate max-w-[80px]">{{ $sf->parent_id ? 'Sous-famille' : 'Famille' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-1.5 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            {{ ($selectorFamilies ?? collect())->count() }} Résultats
        </div>
    </div>
</aside>
