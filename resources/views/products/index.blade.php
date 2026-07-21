@extends('layouts.erp')
@section('title', 'Articles')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Articles</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Total articles</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Actifs</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $summary['active'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Vendables</p>
            <p class="text-[16px] font-bold text-blue-600 tabular-nums">{{ $summary['sellable'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Achetables</p>
            <p class="text-[16px] font-bold text-emerald-700 tabular-nums">{{ $summary['purchasable'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Articles</h1>
            <p class="text-[12.5px] text-gray-500">{{ $products->total() }} article(s) trouvé(s) — référentiel produits</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('exports.products', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('exports.products-pdf', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('import.index', ['type' => 'products']) }}"
               class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4 0l4-4m0 0l4 4m-4-4V4"/>
                </svg>
                Importer
            </a>
            <a href="{{ route('products.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvel article
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        @php
            $lblX = 'block text-[11px] font-bold text-gray-700 mb-1';
            $inpX = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
            $lkX  = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
            $carX = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
            {{-- Recherche texte (prend 2 cols sur xl) --}}
            <div class="sm:col-span-2 xl:col-span-2">
                <label class="{{ $lblX }}">Rechercher</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Désignation, référence, code-barres…" class="{{ $inpX }}">
            </div>

            <div>
                <label class="{{ $lblX }}">Famille</label>
                <div class="relative"><select name="family_id" class="{{ $lkX }}">
                    <option value="">Toutes les familles</option>
                    @foreach($families as $family)
                        <option value="{{ $family->id }}" {{ request('family_id') == $family->id ? 'selected' : '' }}>{{ $family->name }}</option>
                        @foreach($family->children as $child)
                        <option value="{{ $child->id }}" {{ request('family_id') == $child->id ? 'selected' : '' }}>&nbsp;&nbsp;└ {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>{!! $carX !!}</div>
            </div>

            <div>
                <label class="{{ $lblX }}">Type</label>
                <div class="relative"><select name="type" class="{{ $lkX }}">
                    <option value="">Tous les types</option>
                    <option value="simple"  {{ request('type') === 'simple'  ? 'selected' : '' }}>Simple</option>
                    <option value="service" {{ request('type') === 'service' ? 'selected' : '' }}>Service</option>
                    <option value="compose" {{ request('type') === 'compose' ? 'selected' : '' }}>Composé</option>
                </select>{!! $carX !!}</div>
            </div>

            <div>
                <label class="{{ $lblX }}">Actif / inactif</label>
                <div class="relative"><select name="is_active" class="{{ $lkX }}">
                    <option value="">Tous</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Actifs</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactifs</option>
                </select>{!! $carX !!}</div>
            </div>
        </div>

        {{-- [PHASE E] Filtres avancés : code article, statut, familles 3 niveaux --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mt-3">
            <div>
                <label class="{{ $lblX }}">Code article</label>
                <input type="text" name="code_article" value="{{ request('code_article') }}" placeholder="ART…" class="{{ $inpX }} font-mono">
            </div>
            <div>
                <label class="{{ $lblX }}">Statut</label>
                <div class="relative"><select name="statut" class="{{ $lkX }}">
                    <option value="">Tous les statuts</option>
                    <option value="actif"    {{ request('statut') === 'actif'    ? 'selected' : '' }}>Actif</option>
                    <option value="inactif"  {{ request('statut') === 'inactif'  ? 'selected' : '' }}>Inactif</option>
                    <option value="bloque"   {{ request('statut') === 'bloque'   ? 'selected' : '' }}>Bloqué</option>
                </select>{!! $carX !!}</div>
            </div>
            @foreach(['famille1_id' => 'Famille 1', 'famille2_id' => 'Famille 2', 'famille3_id' => 'Famille 3'] as $fname => $flabel)
            <div>
                <label class="{{ $lblX }}">{{ $flabel }}</label>
                <div class="relative"><select name="{{ $fname }}" class="{{ $lkX }}">
                    <option value="">—</option>
                    @foreach(($familiesFlat ?? collect()) as $f)
                        <option value="{{ $f->id }}" {{ request($fname) == $f->id ? 'selected' : '' }}>{{ $f->code }} — {{ $f->name }}</option>
                    @endforeach
                </select>{!! $carX !!}</div>
            </div>
            @endforeach
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['search', 'family_id', 'brand_id', 'type', 'is_active', 'code_article', 'statut', 'famille1_id', 'famille2_id', 'famille3_id']))
            <a href="{{ route('products.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-1.5 rounded-[4px] transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono, flux Achat/Vente/Fabrication --}}
    @php $typeArticleLabels = \App\Models\Product::typeArticleOptions(); @endphp
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="tbl-scroll">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#3b4248] text-white text-[11px]">
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap w-32">Code article</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide">Désignation</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide hidden md:table-cell">Famille</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide hidden md:table-cell w-28">Catégorie</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-32">Type</th>
                        <th class="text-center font-semibold px-3 py-1.5 uppercase tracking-wide w-24">Flux</th>
                        <th class="text-center font-semibold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-16">UM</th>
                        <th class="text-right font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden lg:table-cell w-24">Poids net (kg)</th>
                        <th class="text-right font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden lg:table-cell w-28">Stock à terme</th>
                        <th class="text-center font-semibold px-3 py-1.5 uppercase tracking-wide w-24">Statut</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden xl:table-cell w-32">Créé le / par</th>
                        <th class="px-3 py-1.5 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    @php
                        $stockATerme = $product->stockATerme();
                        $statutStyles = [
                            'actif'   => 'bg-green-50 text-green-700 border-green-200',
                            'inactif' => 'bg-gray-100 text-gray-500 border-gray-200',
                            'bloque'  => 'bg-red-50 text-red-700 border-red-200',
                        ];
                        $statutLabels = ['actif' => 'Actif', 'inactif' => 'Inactif', 'bloque' => 'Bloqué'];
                        $statut = $product->statut ?? ($product->is_active ? 'actif' : 'inactif');
                    @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('products.show', $product) }}" class="hover:underline">{{ $product->code_article ?: $product->reference ?: '—' }}</a>
                        </td>
                        <td class="px-3 py-1">
                            <a href="{{ route('products.show', $product) }}" class="font-medium text-gray-900 hover:text-emerald-700 block truncate max-w-md">{{ $product->name }}</a>
                        </td>
                        <td class="px-3 py-1 hidden md:table-cell text-gray-600">
                            <span class="block truncate max-w-[180px]" title="{{ $product->family?->name }}">{{ $product->family?->name ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-1 hidden md:table-cell">
                            @if($product->itemCategory)
                            <a href="{{ route('articles.categories.show', $product->itemCategory) }}" class="font-mono text-[11px] text-emerald-800 hover:underline" title="{{ $product->itemCategory->name }}">{{ $product->itemCategory->code }}</a>
                            @else — @endif
                        </td>
                        <td class="px-3 py-1 hidden lg:table-cell text-gray-600">
                            {{ $typeArticleLabels[$product->type_article] ?? ($product->type_article ?: '—') }}
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-center gap-0.5">
                                <span title="Acheté" class="inline-flex items-center justify-center w-5 h-5 rounded-[3px] text-[10px] font-bold {{ $product->is_purchasable ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-300' }}">A</span>
                                <span title="Vendu" class="inline-flex items-center justify-center w-5 h-5 rounded-[3px] text-[10px] font-bold {{ $product->is_sellable ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-300' }}">V</span>
                                <span title="Fabriqué" class="inline-flex items-center justify-center w-5 h-5 rounded-[3px] text-[10px] font-bold {{ $product->is_manufacturable ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-300' }}">F</span>
                            </div>
                        </td>
                        <td class="px-3 py-1 text-center hidden lg:table-cell text-gray-500 font-mono">
                            {{ $product->unit?->abbreviation ?? $product->unit?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums hidden lg:table-cell whitespace-nowrap text-gray-600">
                            {{ $product->net_weight_per_us > 0 ? rtrim(rtrim(number_format($product->net_weight_per_us, 3, ',', ' '), '0'), ',') : '—' }}
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums hidden lg:table-cell whitespace-nowrap">
                            <span class="{{ $stockATerme < 0 ? 'text-red-600 font-semibold' : ($stockATerme > 0 ? 'text-gray-900' : 'text-gray-400') }}">{{ number_format($stockATerme, 0, ',', ' ') }}</span>
                        </td>
                        <td class="px-3 py-1 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $statutStyles[$statut] ?? 'bg-gray-100 text-gray-500 border-gray-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statut === 'actif' ? 'bg-green-500' : ($statut === 'bloque' ? 'bg-red-500' : 'bg-gray-400') }}"></span>
                                {{ $statutLabels[$statut] ?? $statut }}
                            </span>
                        </td>
                        <td class="px-3 py-1 hidden xl:table-cell text-[11.5px] text-gray-500 whitespace-nowrap">
                            {{ $product->created_at?->format('d/m/Y') }}
                            @if($product->createdBy)<span class="block text-gray-400">{{ Str::limit($product->createdBy->name, 14) }}</span>@endif
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('products.show', $product) }}" class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('products.edit', $product) }}" class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Supprimer « {{ addslashes($product->name) }} » ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Aucun article trouvé</p>
                                    @if(request()->hasAny(['search', 'family_id', 'brand_id', 'type', 'is_active', 'code_article', 'statut', 'famille1_id', 'famille2_id', 'famille3_id']))
                                        <a href="{{ route('products.index') }}" class="text-sm text-emerald-600 hover:text-emerald-700 mt-1 inline-block">Effacer les filtres</a>
                                    @else
                                        <a href="{{ route('products.create') }}" class="text-sm text-emerald-600 hover:text-emerald-700 mt-1 inline-block">Créer le premier article</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <div class="flex items-center gap-3">
                <span>{{ $products->total() }} article(s)</span>
                <span class="hidden sm:inline text-gray-300">|</span>
                <span class="hidden sm:inline">Flux : <b class="text-emerald-800">A</b>chat · <b class="text-blue-700">V</b>ente · <b class="text-amber-700">F</b>abrication</span>
            </div>
            @if($products->hasPages())<div>{{ $products->withQueryString()->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Référentiel : <span class="text-white font-semibold">{{ $summary['total'] }} articles ({{ $summary['active'] }} actifs)</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
