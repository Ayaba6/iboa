@extends('layouts.erp')
@section('title', 'Famille ' . ($family->code ?: $family->name))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('product-families.index') }}" class="hover:text-gray-700">Familles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $family->code ?: $family->name }}</span>
@endsection

@section('content')
<div class="flex items-start gap-4">
    @include('product-families._selector')
    <div class="flex-1 min-w-0 space-y-3" x-data="{ tab: 'general' }">

    {{-- ═══ Bandeau + onglets SAGE X3 (même squelette que le form article) ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">
                    Familles : Fiche
                    <span class="font-mono text-emerald-700 text-[18px] ml-1">{{ $family->code ?: $family->name }}</span>
                    @unless($family->is_active)<span class="text-[13px] font-semibold text-gray-400 italic ml-2">(inactive)</span>@endunless
                </h2>
                <p class="text-[11.5px] text-gray-400">Classement commercial et statistique — la gestion relève de la <a href="{{ route('articles.categories.index') }}" class="underline">catégorie d'article</a>.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('product-families.edit', $family) }}"
                   class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">
                    Modifier
                </a>
                <a href="{{ route('product-families.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">
                    Fermer
                </a>
                <a href="{{ route('product-families.create') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">
                    Créer +
                </a>
            </div>
        </div>

        <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
            @foreach([
                'general'      => 'Général',
                'sousfamilles' => 'Sous-familles (' . $family->children->count() . ')',
                'articles'     => 'Articles (' . $articlesCount . ')',
                'stats'        => 'Statistiques',
            ] as $key => $label)
            {{-- [SAGE X3] Onglet = ancre : sections toutes visibles, clic = scroll --}}
            <button type="button"
                    @click="tab = '{{ $key }}'; document.getElementById('fam-sec-{{ $key }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">
                {{ $label }}
            </button>
            @endforeach
        </nav>

        <div class="p-4 space-y-4">
            {{-- ══ GÉNÉRAL (lecture) ══ --}}
            <section id="fam-sec-general" class="border border-gray-200 rounded-[4px] overflow-hidden scroll-mt-28">
                <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase">Général</div>
                <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3">
                    @foreach([
                        'Code'            => $family->code,
                        'Intitulé'        => $family->name,
                        'Famille parente' => $family->parent?->name,
                        'Statut'          => $family->is_active ? 'Active' : 'Inactive',
                        'Ordre'           => $family->sort_order,
                        'Description'     => $family->description,
                    ] as $l => $v)
                    <div><dt class="text-[10.5px] font-bold text-gray-500 uppercase">{{ $l }}</dt><dd class="text-[13px] text-gray-900">{{ ($v !== null && $v !== '') ? $v : '—' }}</dd></div>
                    @endforeach
                </div>
            </section>

            {{-- ══ SOUS-FAMILLES ══ --}}
            <section id="fam-sec-sousfamilles" class="border border-gray-200 rounded-[4px] overflow-hidden scroll-mt-28">
                <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase">Sous-familles ({{ $family->children->count() }})</div>
                <div class="p-4">
                    @forelse($family->children as $sf)
                    <div class="flex items-center justify-between border-b border-gray-100 py-1.5 text-[12.5px]">
                        <span><a href="{{ route('product-families.show', $sf) }}" class="font-mono text-emerald-800 hover:underline">{{ $sf->code }}</a> — {{ $sf->name }}</span>
                        <span class="text-gray-500">{{ $sf->sub_products_count }} article(s) · {{ $sf->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    @empty
                    <p class="text-gray-400 text-[12.5px]">{{ $family->parent_id ? 'Une sous-famille ne porte pas de sous-familles.' : 'Aucune sous-famille.' }}</p>
                    @endforelse
                </div>
            </section>

            {{-- ══ ARTICLES ══ --}}
            <section id="fam-sec-articles" class="border border-gray-200 rounded-[4px] overflow-hidden scroll-mt-28">
                <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase">Articles ({{ $articlesCount }})</div>
                <div class="p-4">
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
            </section>

            {{-- ══ STATISTIQUES ══ --}}
            <section id="fam-sec-stats" class="border border-gray-200 rounded-[4px] overflow-hidden scroll-mt-28">
                <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase">Statistiques</div>
                <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
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
            </section>
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fiche : <span class="text-white font-semibold">Famille article</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    </div>
</div>
@endsection
