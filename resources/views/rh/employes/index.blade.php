@extends('layouts.erp')
@section('title', 'Employés')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">RH – Employés</span>
@endsection

@section('content')
{{-- KPI summary bar --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">Total employés</p>
        <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">Actifs</p>
        <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $summary['actif'] }}</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">Suspendus</p>
        <p class="text-[16px] font-bold {{ $summary['suspendu'] > 0 ? 'text-amber-600' : 'text-gray-400' }} tabular-nums">{{ $summary['suspendu'] }}</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        <p class="text-xs text-gray-500">Quittés</p>
        <p class="text-[16px] font-bold text-gray-500 tabular-nums">{{ $summary['quitte'] }}</p>
    </div>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Gestion des Employés</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $employees->total() }} employé(s) trouvé(s)</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('rh.departments.index') }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            Départements
        </a>
        <a href="{{ route('rh.employes.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvel employé
        </a>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4 mb-5 grid grid-cols-2 md:grid-cols-4 gap-3">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
           placeholder="Nom, matricule, CNSS…"
           class="col-span-2 md:col-span-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">

    <select name="department_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les départements</option>
        @foreach($departments as $dep)
            <option value="{{ $dep->id }}" @selected(($filters['department_id'] ?? '') == $dep->id)>{{ $dep->name }}</option>
        @endforeach
    </select>

    <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <option value="">Tous les statuts</option>
        @foreach(['actif' => 'Actif', 'suspendu' => 'Suspendu', 'licencie' => 'Licencié', 'demissionne' => 'Démissionné'] as $v => $l)
            <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>
        @endforeach
    </select>

    <div class="flex gap-2">
        <button type="submit" class="flex-1 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">Filtrer</button>
        <a href="{{ route('rh.employes.index') }}" class="px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
    </div>
</form>

{{-- Table --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
            <tr>
                <th class="px-3 py-1.5 text-left">Matricule</th>
                <th class="px-3 py-1.5 text-left">Nom & Prénom</th>
                <th class="px-3 py-1.5 text-left">Département</th>
                <th class="px-3 py-1.5 text-left">Poste</th>
                <th class="px-3 py-1.5 text-right">Salaire base</th>
                <th class="px-3 py-1.5 text-center">Statut</th>
                <th class="px-3 py-1.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($employees as $emp)
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5 font-mono text-xs text-gray-600">{{ $emp->matricule }}</td>
                <td class="px-3 py-1.5">
                    <div class="font-medium text-gray-900">{{ $emp->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $emp->category_label }}</div>
                </td>
                <td class="px-3 py-1.5 text-gray-600">{{ $emp->department?->name ?? '—' }}</td>
                <td class="px-3 py-1.5 text-gray-600">{{ $emp->job_title ?? '—' }}</td>
                <td class="px-3 py-1.5 text-right font-mono">
                    {{ $emp->activeContract ? number_format($emp->activeContract->base_salary, 0, ',', ' ') . ' F' : '—' }}
                </td>
                <td class="px-3 py-1.5 text-center">
                    @php $color = $emp->status_color @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                        {{ $emp->status_label }}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-right">
                    <a href="{{ route('rh.employes.show', $emp) }}" class="text-blue-600 hover:underline text-xs">Voir</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun employé trouvé.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($employees->hasPages())
    <div class="px-3 py-1.5 border-t border-gray-200">{{ $employees->links() }}</div>
    @endif
</div>
@endsection
