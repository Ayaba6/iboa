@extends('layouts.erp')
@section('title', 'Propagation — ' . $category->code)

@section('breadcrumb')
    <a href="{{ route('articles.categories.index') }}" class="hover:text-gray-700">Catégories</a>
    <span class="mx-1">/</span>
    <a href="{{ route('articles.categories.show', $category) }}" class="hover:text-gray-700">{{ $category->code }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Propagation</span>
@endsection

@section('content')
<div class="space-y-3 max-w-4xl">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Aperçu de la propagation — {{ $category->code }}</h1>
        <p class="text-[11.5px] text-gray-400">Rien n'a encore été modifié. Champs demandés : {{ implode(', ', $preview['fields']) ?: '—' }}.</p>
    </div>

    @if($preview['count'] === 0)
    <div class="rounded-[4px] bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">Tous les articles sont déjà conformes aux valeurs de la catégorie — rien à propager.</div>
    <a href="{{ route('articles.categories.show', $category) }}" class="inline-block text-[12.5px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-[4px]">Retour</a>
    @else
    <div class="rounded-[4px] bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800"><strong>{{ $preview['count'] }}</strong> article(s) seront modifiés. Les valeurs personnalisées listées ci-dessous seront remplacées.</div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300"><tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase">Article</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase">Champ</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase">Valeur actuelle</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase">Nouvelle valeur</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($preview['articles'] as $a)
                    @foreach($a['diff'] as $field => $d)
                    <tr>
                        <td class="px-3 py-1.5 text-gray-900">{{ $a['name'] }}</td>
                        <td class="px-3 py-1.5 font-mono text-[12px]">{{ $field }}</td>
                        <td class="px-3 py-1.5 text-red-700">{{ var_export($d['de'], true) }}</td>
                        <td class="px-3 py-1.5 text-emerald-700 font-semibold">{{ var_export($d['vers'], true) }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('articles.categories.propagate', $category) }}"
          onsubmit="return confirm('Propager ces valeurs vers {{ $preview['count'] }} article(s) ? Action journalisée.')" class="flex gap-2">
        @csrf
        @foreach($preview['fields'] as $f)<input type="hidden" name="fields[]" value="{{ $f }}">@endforeach
        <button class="text-[12.5px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-2 rounded-[4px]">Confirmer la propagation</button>
        <a href="{{ route('articles.categories.show', $category) }}" class="text-[12.5px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-2 rounded-[4px]">Annuler</a>
    </form>
    @endif
</div>
@endsection
