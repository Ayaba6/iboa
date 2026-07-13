@extends('layouts.erp')
@section('title', 'Évaluations & performance')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Évaluations</span>
@endsection

@section('content')
@php $rc = ['insuffisant'=>'text-red-600','a_ameliorer'=>'text-amber-600','satisfaisant'=>'text-blue-700','bon'=>'text-emerald-700','excellent'=>'text-emerald-800']; @endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Évaluations &amp; performance</h1>
            <p class="text-sm text-gray-500">Campagnes, objectifs, auto-évaluation, évaluation manager, note globale et prime liée.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.evaluations.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Nouvelle évaluation</a>
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
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut</label>
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\Appraisal::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Année</label>
            <input type="number" name="year" value="{{ request('year') }}" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]" placeholder="2026">
        </div>
        <div class="flex items-end gap-2">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Campagne</th>
                    <th class="px-3 py-2 text-left">Salarié</th>
                    <th class="px-3 py-2 text-right">Année</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                    <th class="px-3 py-2 text-right">Note /5</th>
                    <th class="px-3 py-2 text-left">Appréciation</th>
                    <th class="px-3 py-2 text-right">Prime</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($appraisals as $a)
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5">{{ $a->campaign }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ $a->employee?->full_name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $a->period_year }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="text-xs {{ $a->status === 'finalisee' ? 'text-emerald-700 font-semibold' : 'text-gray-500' }}">{{ $a->statusLabel() }}</span></td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $a->overall_score !== null ? number_format((float) $a->overall_score, 2, ',', ' ') : '—' }}</td>
                    <td class="px-3 py-1.5 {{ $rc[$a->rating] ?? 'text-gray-500' }} text-xs font-semibold">{{ $a->ratingLabel() ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $a->bonus_amount !== null ? number_format((float) $a->bonus_amount, 0, ',', ' ').' F' : '—' }}</td>
                    <td class="px-3 py-1.5 text-right"><a href="{{ route('rh.evaluations.show', $a) }}" class="text-blue-700 hover:underline text-xs font-semibold">Ouvrir</a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucune évaluation.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $appraisals->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Évaluations : <span class="text-white font-semibold">{{ $appraisals->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
