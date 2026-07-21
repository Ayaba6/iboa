@extends('layouts.erp')
@section('title', 'Famille ' . ($family->code ?: $family->name))

@section('breadcrumb')
    <a href="{{ route('product-families.index') }}" class="hover:text-gray-700">Familles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $family->code ?: $family->name }}</span>
@endsection

@section('content')
<div class="space-y-3" x-data="{ tab: 'general' }">

    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900"><span class="font-mono text-emerald-700">{{ $family->code }}</span> — {{ $family->name }}</h1>
            <p class="text-[11.5px] text-gray-400">Famille = classement commercial et statistique (la gestion relève de la catégorie).</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('product-families.edit', $family) }}" class="text-[12.5px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-3 py-1.5 rounded-[4px]">Modifier</a>
            <a href="{{ route('product-families.index') }}" class="text-[12.5px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-[4px]">✕ Fermer</a>
        </div>
    </div>

    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="flex border-b border-gray-200 bg-gray-50 overflow-x-auto">
            @foreach(['general'=>'Général','sousfamilles'=>'Sous-familles ('.$family->children->count().')','articles'=>'Articles ('.$articlesCount.')','stats'=>'Statistiques'] as $key => $label)
            <button type="button" @click="tab='{{ $key }}'"
                    class="px-4 py-2 text-[12.5px] font-semibold border-b-2 whitespace-nowrap"
                    :class="tab==='{{ $key }}' ? 'border-emerald-600 text-emerald-800 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab==='general'" class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
            @foreach(['Code'=>$family->code,'Intitulé'=>$family->name,'Famille parente'=>$family->parent?->name,'Statut'=>$family->is_active?'Active':'Inactive','Description'=>$family->description] as $l=>$v)
            <div><dt class="text-[10.5px] font-bold text-gray-500 uppercase">{{ $l }}</dt><dd class="text-[13px] text-gray-900">{{ $v ?: '—' }}</dd></div>
            @endforeach
        </div>

        <div x-show="tab==='sousfamilles'" x-cloak class="p-4">
            @forelse($family->children as $sf)
            <div class="flex items-center justify-between border-b border-gray-100 py-1.5 text-[12.5px]">
                <span><span class="font-mono text-emerald-800">{{ $sf->code }}</span> — {{ $sf->name }}</span>
                <span class="text-gray-500">{{ $sf->sub_products_count }} article(s) · {{ $sf->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            @empty
            <p class="text-gray-400 text-[12.5px]">Aucune sous-famille.</p>
            @endforelse
        </div>

        <div x-show="tab==='articles'" x-cloak class="p-4">
            @if($articles->isEmpty())
            <p class="text-gray-400 text-[12.5px]">Aucun article dans cette famille.</p>
            @else
            <table class="min-w-full text-[12.5px]">
                <thead><tr class="text-left text-[10.5px] font-bold text-gray-500 uppercase">
                    <th class="py-1 pr-4">Référence</th><th class="py-1 pr-4">Désignation</th><th class="py-1 pr-4">Catégorie</th><th class="py-1">Statut</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($articles as $a)
                    <tr>
                        <td class="py-1 pr-4 font-mono"><a href="{{ route('products.show', $a) }}" class="text-emerald-800 hover:underline">{{ $a->reference }}</a></td>
                        <td class="py-1 pr-4">{{ $a->name }}</td>
                        <td class="py-1 pr-4 font-mono text-gray-500">{{ $a->itemCategory?->code ?? '—' }}</td>
                        <td class="py-1">{{ $a->is_active ? 'Actif' : 'Inactif' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div x-show="tab==='stats'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="border border-gray-200 rounded-[4px] p-3 text-center">
                <div class="text-[10.5px] font-bold text-gray-500 uppercase">Articles</div>
                <div class="text-[18px] font-bold text-gray-900 tabular-nums">{{ $articlesCount }}</div>
            </div>
            <div class="border border-gray-200 rounded-[4px] p-3 text-center">
                <div class="text-[10.5px] font-bold text-gray-500 uppercase">Sous-familles</div>
                <div class="text-[18px] font-bold text-gray-900 tabular-nums">{{ $family->children->count() }}</div>
            </div>
            <div class="border border-gray-200 rounded-[4px] p-3 text-center col-span-2">
                <div class="text-[10.5px] font-bold text-gray-500 uppercase">CA facturé HT {{ now()->year }}</div>
                <div class="text-[18px] font-bold text-blue-700 tabular-nums">{{ number_format($caYtd, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>
</div>
@endsection
