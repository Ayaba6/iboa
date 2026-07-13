@extends('layouts.erp')
@section('title', $position->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.postes.index') }}" class="hover:text-gray-700">Postes & grades</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $position->name }}</span>
@endsection

@section('content')
@php
    $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', ' ') . ' FCFA';
    $lbl = 'text-[10px] font-bold text-gray-400 uppercase tracking-wide';
    $val = 'text-[13px] text-gray-800 font-medium';
@endphp

<div class="w-full space-y-3">

    <div class="bg-white rounded-[4px] border border-gray-300 p-5 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $position->name }}
                @if($position->is_active)<span class="ml-2 bg-green-50 text-green-700 text-[11px] font-semibold px-2 py-0.5 rounded-full align-middle">Actif</span>
                @else<span class="ml-2 bg-gray-100 text-gray-500 text-[11px] font-semibold px-2 py-0.5 rounded-full align-middle">Inactif</span>@endif
            </h1>
            <p class="text-[12px] text-gray-500 mt-0.5 font-mono">{{ $position->code }} · {{ $position->department?->name ?? 'Sans département' }}</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.postes.edit', $position) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px]">Modifier</a>
        @endcan
    </div>

    <div class="bg-white rounded-[4px] border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div><p class="{{ $lbl }}">Grade</p><p class="{{ $val }}">{{ $position->grade ?? '—' }}</p></div>
        <div><p class="{{ $lbl }}">Catégorie</p><p class="{{ $val }}">{{ $position->category ?? '—' }}</p></div>
        <div><p class="{{ $lbl }}">Centre de coût</p><p class="{{ $val }}">{{ $position->cost_center ?? '—' }}</p></div>
        <div><p class="{{ $lbl }}">Effectif</p><p class="{{ $val }}">{{ $position->employees_count ?? $position->employees->count() }}{{ $position->headcount_target ? ' / '.$position->headcount_target.' cible' : '' }}</p></div>
        <div><p class="{{ $lbl }}">Salaire min</p><p class="{{ $val }} tabular-nums">{{ $fmt($position->salary_min) }}</p></div>
        <div><p class="{{ $lbl }}">Salaire max</p><p class="{{ $val }} tabular-nums">{{ $fmt($position->salary_max) }}</p></div>
    </div>

    @if($position->description || $position->missions)
    <div class="bg-white rounded-[4px] border border-gray-200 p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div><p class="{{ $lbl }} mb-1">Description</p><p class="text-[13px] text-gray-700 whitespace-pre-line">{{ $position->description ?: '—' }}</p></div>
        <div><p class="{{ $lbl }} mb-1">Missions</p><p class="text-[13px] text-gray-700 whitespace-pre-line">{{ $position->missions ?: '—' }}</p></div>
    </div>
    @endif

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]"><h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Salariés sur ce poste</h2></div>
        @if($position->employees->isEmpty())
            <div class="p-6 text-center text-gray-400 text-sm">Aucun salarié affecté à ce poste.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr><th class="px-3 py-1.5 text-left">Matricule</th><th class="px-3 py-1.5 text-left">Nom</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($position->employees as $e)
                <tr>
                    <td class="px-3 py-1.5 font-mono text-xs">{{ $e->matricule }}</td>
                    <td class="px-3 py-1.5"><a href="{{ route('rh.employes.show', $e) }}" class="text-blue-700 hover:underline">{{ $e->full_name ?? $e->matricule }}</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endsection
