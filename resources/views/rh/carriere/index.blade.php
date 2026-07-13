@extends('layouts.erp')
@section('title', 'Mouvements & carrière')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Mouvements & carrière</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide'; @endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Mouvements &amp; carrière</h1>
            <p class="text-sm text-gray-500">Affectations, mutations, promotions, changements de poste/grade — historique à date.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.carriere.create', ['employee_id' => request('employee_id')]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Nouveau mouvement</a>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Salarié</label>
            <select name="employee_id" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id')==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type</label>
            <select name="type" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\CareerEvent::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button>
            @if(request()->hasAny(['employee_id','type']))<a href="{{ route('rh.carriere.index') }}" class="border border-gray-300 text-gray-600 text-sm px-4 h-8 rounded-[3px] flex items-center">Réinit.</a>@endif
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Date d'effet</th>
                    <th class="px-3 py-2 text-left">Salarié</th>
                    <th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-left">Nouveau poste</th>
                    <th class="px-3 py-2 text-left">Département</th>
                    <th class="px-3 py-2 text-left">Grade</th>
                    <th class="px-3 py-2 text-right">Salaire</th>
                    <th class="px-3 py-2 text-center">Appliqué</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($events as $ev)
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 tabular-nums">{{ optional($ev->effective_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ $ev->employee?->full_name ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ $ev->typeLabel() }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $ev->toJobPosition?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $ev->toDepartment?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $ev->grade ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $ev->salary !== null ? number_format((float) $ev->salary, 0, ',', ' ').' F' : '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($ev->applied)<span class="text-emerald-700 text-xs font-semibold">● Appliqué</span>
                        @else<span class="text-amber-600 text-xs">○ À venir</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucun mouvement de carrière.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $events->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Mouvements : <span class="text-white font-semibold">{{ $events->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
