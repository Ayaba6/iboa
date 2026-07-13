@extends('layouts.erp')
@section('title', 'Recrutement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Recrutement</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Recrutement &amp; onboarding</h1>
            <p class="text-sm text-gray-500">Besoins de recrutement, pipeline candidats, embauche automatique de la fiche salarié.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.recrutement.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Nouveau besoin</a>
        @endcan
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white border border-blue-200 rounded-[4px] p-4"><p class="text-xs text-blue-600 uppercase">Ouverts</p><p class="text-2xl font-bold leading-none tabular-nums text-blue-700 mt-1">{{ $stats['ouvert'] }}</p></div>
        <div class="bg-white border border-amber-200 rounded-[4px] p-4"><p class="text-xs text-amber-600 uppercase">En cours</p><p class="text-2xl font-bold leading-none tabular-nums text-amber-700 mt-1">{{ $stats['en_cours'] }}</p></div>
        <div class="bg-white border border-emerald-200 rounded-[4px] p-4"><p class="text-xs text-emerald-600 uppercase">Pourvus</p><p class="text-2xl font-bold leading-none tabular-nums text-emerald-700 mt-1">{{ $stats['pourvu'] }}</p></div>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Recherche</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Intitulé du poste…" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]">
        </div>
        <div class="flex items-end gap-2 sm:col-span-2">
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous statuts</option>
                @foreach(\App\Models\Recruitment::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Réf.</th>
                    <th class="px-3 py-2 text-left">Poste</th>
                    <th class="px-3 py-2 text-left">Département</th>
                    <th class="px-3 py-2 text-left">Contrat</th>
                    <th class="px-3 py-2 text-right">Postes</th>
                    <th class="px-3 py-2 text-right">Candidats</th>
                    <th class="px-3 py-2 text-right">Embauchés</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recruitments as $r)
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 font-mono text-xs">{{ $r->reference ?? '#'.$r->id }}</td>
                    <td class="px-3 py-1.5"><a href="{{ route('rh.recrutement.show', $r) }}" class="text-blue-700 hover:underline">{{ $r->title }}</a></td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $r->department?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ \App\Models\Recruitment::CONTRACT_TYPES[$r->contract_type] ?? $r->contract_type }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $r->positions_count }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $r->candidates_count }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $r->hired_count }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @php $c = ['ouvert'=>'text-blue-700','en_cours'=>'text-amber-700','pourvu'=>'text-emerald-700','annule'=>'text-gray-500'][$r->status] ?? 'text-gray-500'; @endphp
                        <span class="{{ $c }} text-xs font-semibold">{{ $r->statusLabel() }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucun besoin de recrutement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $recruitments->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Besoins : <span class="text-white font-semibold">{{ $recruitments->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
