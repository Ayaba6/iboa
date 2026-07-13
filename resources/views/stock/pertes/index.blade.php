@extends('layouts.erp')
@section('title', 'Pertes & casses')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Pertes & casses</span>
@endsection

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F'; @endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Pertes &amp; casses</h1>
            <p class="text-sm text-gray-500">Déclaration, cause, photo, responsabilité, valorisation et sortie de stock à la validation.</p>
        </div>
        @can('stocks.view')
        <a href="{{ route('stocks.pertes.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Déclarer une perte</a>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-2 gap-4 max-w-md">
        <div class="bg-white border border-amber-200 rounded-[4px] p-4"><p class="text-xs text-amber-600 uppercase">À valider</p><p class="text-2xl font-bold text-amber-700 tabular-nums">{{ $stats['a_valider'] }}</p></div>
        <div class="bg-white border border-red-200 rounded-[4px] p-4"><p class="text-xs text-red-600 uppercase">Pertes {{ now()->year }}</p><p class="text-xl font-bold text-red-700 tabular-nums mt-1">{{ $fmt($stats['valeur_annee']) }}</p></div>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type</label>
            <select name="type" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\StockLoss::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut</label>
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\StockLoss::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end"><button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button></div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Réf.</th>
                    <th class="px-3 py-2 text-left">Article</th>
                    <th class="px-3 py-2 text-left">Dépôt</th>
                    <th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-right">Qté</th>
                    <th class="px-3 py-2 text-right">Valeur</th>
                    <th class="px-3 py-2 text-left">Responsable</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($losses as $l)
                @php $sc = ['declaree'=>'text-amber-600','validee'=>'text-emerald-700','rejetee'=>'text-red-600'][$l->status] ?? 'text-gray-500'; @endphp
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 font-mono text-xs"><a href="{{ route('stocks.pertes.show', $l) }}" class="text-blue-700 hover:underline">{{ $l->reference ?? '#'.$l->id }}</a></td>
                    <td class="px-3 py-1.5">{{ $l->product?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $l->warehouse?->code ?? $l->warehouse?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $l->typeLabel() }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $l->quantity, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($l->estimated_value) }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $l->responsible?->full_name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="{{ $sc }} text-xs font-semibold">{{ $l->statusLabel() }}</span></td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucune perte déclarée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $losses->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Pertes : <span class="text-white font-semibold">{{ $losses->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
