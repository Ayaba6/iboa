@extends('layouts.erp')
@section('title', 'Articles sans nomenclature')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.reports') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Articles sans nomenclature</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Articles fabriqués sans nomenclature</h1>
            <p class="text-[11.5px] text-gray-400">{{ $products->total() }} article(s) fabricable(s) sans BOM active — non lançables en production tant qu'une nomenclature n'est pas créée.</p>
        </div>
    </div>

    @if($products->isEmpty())
        <div class="bg-white border border-gray-300 rounded-[4px] px-4 py-10 text-center text-[13px] text-emerald-700">
            ✓ Tous les articles fabriqués disposent d'une nomenclature active.
        </div>
    @else
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-2.5 text-[12px] text-amber-800">
            Ces articles sont marqués <b>fabricables</b> ou <b>MTO</b> mais n'ont pas de nomenclature : la génération d'OF est bloquée. Créez une nomenclature pour chacun.
        </div>

        <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide w-32">Code</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide">Désignation</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide">Famille</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide w-28">Mode</th>
                        <th class="px-3 py-1.5 w-28"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($products as $p)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1 font-mono text-emerald-800">{{ $p->reference }}</td>
                        <td class="px-3 py-1">
                            <a href="{{ route('products.show', $p) }}" class="text-gray-900 hover:text-emerald-700">{{ $p->name }}</a>
                        </td>
                        <td class="px-3 py-1 text-gray-600">{{ $p->family?->name ?? '—' }}</td>
                        <td class="px-3 py-1">
                            <span class="inline-flex px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-bold bg-purple-100 text-purple-700">{{ $p->production_mode ?: ($p->is_manufacturable ? 'fabriqué' : '—') }}</span>
                        </td>
                        <td class="px-3 py-1 text-right">
                            <a href="{{ route('production.bom.create', ['product_id' => $p->id]) }}" class="text-[11px] font-semibold text-emerald-700 hover:underline">+ Nomenclature</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
                <span>{{ $products->total() }} article(s)</span>
                @if($products->hasPages())<div>{{ $products->links() }}</div>@endif
            </div>
        </div>
    @endif

</div>
@endsection
