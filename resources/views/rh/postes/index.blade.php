@extends('layouts.erp')
@section('title', 'Postes & grades')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Postes & grades</span>
@endsection

@section('content')
@php $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', ' '); @endphp

<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Postes & grades</h1>
            <p class="text-sm text-gray-500">Référentiel des emplois — grade, catégorie, centre de coût, fourchette salariale.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.postes.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Nouveau poste</a>
        @endcan
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Recherche</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Intitulé, code, grade…" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]">
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Département</label>
            <select name="department_id" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous statuts</option>
                <option value="active" @selected(request('status')==='active')>Actifs</option>
                <option value="inactive" @selected(request('status')==='inactive')>Inactifs</option>
            </select>
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Code</th>
                    <th class="px-3 py-2 text-left">Intitulé</th>
                    <th class="px-3 py-2 text-left">Département</th>
                    <th class="px-3 py-2 text-left">Grade</th>
                    <th class="px-3 py-2 text-left">Catégorie</th>
                    <th class="px-3 py-2 text-right">Salaire min</th>
                    <th class="px-3 py-2 text-right">Salaire max</th>
                    <th class="px-3 py-2 text-right">Effectif</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($positions as $p)
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 font-mono text-xs"><a href="{{ route('rh.postes.show', $p) }}" class="text-blue-700 hover:underline">{{ $p->code }}</a></td>
                    <td class="px-3 py-1.5">{{ $p->name }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $p->department?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $p->grade ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $p->category ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($p->salary_min) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($p->salary_max) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $p->employees_count }}{{ $p->headcount_target ? ' / '.$p->headcount_target : '' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($p->is_active)<span class="text-emerald-700 text-xs font-semibold">● Actif</span>
                        @else<span class="text-gray-400 text-xs">○ Inactif</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Aucun poste. Créez le premier référentiel d'emploi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $positions->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Référentiel : <span class="text-white font-semibold">{{ $positions->total() }} poste(s)</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
