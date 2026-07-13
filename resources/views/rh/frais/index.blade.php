@extends('layouts.erp')
@section('title', 'Notes de frais')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Notes de frais</span>
@endsection

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F'; @endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Notes de frais</h1>
            <p class="text-sm text-gray-500">Dépenses professionnelles des salariés — soumission, approbation, remboursement.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.frais.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Nouvelle note</a>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-2 gap-4 max-w-md">
        <div class="bg-white border border-amber-200 rounded-[4px] p-4"><p class="text-xs text-amber-600 uppercase">À approuver</p><p class="text-2xl font-bold text-amber-700 tabular-nums">{{ $stats['a_approuver'] }}</p></div>
        <div class="bg-white border border-blue-200 rounded-[4px] p-4"><p class="text-xs text-blue-600 uppercase">À rembourser</p><p class="text-xl font-bold text-blue-700 tabular-nums mt-1">{{ $fmt($stats['a_rembourser']) }}</p></div>
    </div>

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
                @foreach(\App\Models\ExpenseReport::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end"><button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button></div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Salarié</th>
                    <th class="px-3 py-2 text-left">Objet</th>
                    <th class="px-3 py-2 text-right">Montant</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($reports as $r)
                @php $sc = ['brouillon'=>'text-gray-500','soumise'=>'text-amber-600','approuvee'=>'text-blue-700','rejetee'=>'text-red-600','remboursee'=>'text-emerald-700'][$r->status] ?? 'text-gray-500'; @endphp
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 tabular-nums">{{ optional($r->report_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ $r->employee?->full_name ?? '—' }}</td>
                    <td class="px-3 py-1.5"><a href="{{ route('rh.frais.show', $r) }}" class="text-blue-700 hover:underline">{{ $r->title }}</a></td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($r->total_amount) }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="{{ $sc }} text-xs font-semibold">{{ $r->statusLabel() }}</span></td>
                    <td class="px-3 py-1.5 text-right"><a href="{{ route('rh.frais.show', $r) }}" class="text-blue-700 hover:underline text-xs font-semibold">Ouvrir</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune note de frais.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $reports->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Notes : <span class="text-white font-semibold">{{ $reports->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
