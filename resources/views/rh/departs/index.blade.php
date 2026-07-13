@extends('layouts.erp')
@section('title', 'Départs & solde de tout compte')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Départs</span>
@endsection

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F'; @endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Départs &amp; solde de tout compte</h1>
            <p class="text-sm text-gray-500">Démission, fin de contrat, licenciement, retraite — préavis, indemnités, congés soldés, STC.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.departs.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Déclarer un départ</a>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type</label>
            <select name="type" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\EmployeeDeparture::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut</label>
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\EmployeeDeparture::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end"><button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button></div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Date départ</th>
                    <th class="px-3 py-2 text-left">Salarié</th>
                    <th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-right">STC</th>
                    <th class="px-3 py-2 text-center">Restitution</th>
                    <th class="px-3 py-2 text-center">Documents</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($departures as $d)
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 tabular-nums">{{ optional($d->effective_date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ $d->employee?->full_name ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ $d->typeLabel() }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($d->total_stc) }}</td>
                    <td class="px-3 py-1.5 text-center">{!! $d->equipment_returned ? '<span class="text-emerald-700 text-xs">✓</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                    <td class="px-3 py-1.5 text-center">{!! $d->documents_issued ? '<span class="text-emerald-700 text-xs">✓</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                    <td class="px-3 py-1.5 text-center"><span class="text-xs {{ $d->status === 'cloture' ? 'text-emerald-700 font-semibold' : 'text-amber-600' }}">{{ $d->statusLabel() }}</span></td>
                    <td class="px-3 py-1.5 text-right"><a href="{{ route('rh.departs.show', $d) }}" class="text-blue-700 hover:underline text-xs font-semibold">Ouvrir</a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucun départ enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $departures->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Départs : <span class="text-white font-semibold">{{ $departures->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
