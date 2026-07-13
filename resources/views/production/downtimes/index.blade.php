@extends('layouts.erp')
@section('title', 'Temps d\'arrêt')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Temps d'arrêt</span>
@endsection

@section('content')
@php
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $inp  = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0';
    $fmtH = fn ($m) => number_format($m / 60, 1, ',', ' ').' h';
@endphp
<div class="max-w-6xl space-y-4">

    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Temps d'arrêt</h1>
            <p class="text-sm text-gray-500">Arrêts de production (pannes, changements d'outil, ruptures…) — {{ $days }} derniers jours.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-1">Période</label>
                <select name="jours" onchange="this.form.submit()" class="{{ $sel }}">
                    @foreach([7 => '7 j', 30 => '30 j', 90 => '90 j', 180 => '180 j'] as $v => $lbl)
                        <option value="{{ $v }}" @selected($days === $v)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Total arrêts</p><p class="text-xl font-bold text-gray-900 tabular-nums mt-1">{{ $fmtH($totalMinutes) }}</p></div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Déclarations</p><p class="text-xl font-bold text-gray-900 tabular-nums mt-1">{{ $downtimes->count() }}</p></div>
        <div class="bg-white border {{ $ongoing > 0 ? 'border-amber-200' : 'border-gray-200' }} rounded-[4px] p-4"><p class="text-xs {{ $ongoing > 0 ? 'text-amber-600' : 'text-gray-500' }} uppercase">En cours</p><p class="text-xl font-bold {{ $ongoing > 0 ? 'text-amber-700' : 'text-gray-900' }} tabular-nums mt-1">{{ $ongoing }}</p></div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Cause principale</p><p class="text-sm font-bold text-gray-900 mt-1">{{ $byReason[0]['label'] ?? '—' }}</p>@if(isset($byReason[0]))<p class="text-[11px] text-gray-500">{{ $fmtH($byReason[0]['minutes']) }}</p>@endif</div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Par machine --}}
        <div>
            <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Arrêts par machine</div>
            <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#3b4248] text-white"><tr>
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-right">Arrêts</th>
                        <th class="{{ $th }} text-right">Durée</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($byMachine as $m)
                        <tr><td class="px-3 py-1.5 font-medium">{{ $m['machine'] }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $m['count'] }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmtH($m['minutes']) }}</td></tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Aucun arrêt sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{-- Par cause --}}
        <div>
            <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Arrêts par cause</div>
            <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#3b4248] text-white"><tr>
                        <th class="{{ $th }} text-left">Cause</th>
                        <th class="{{ $th }} text-right">Durée</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($byReason as $r)
                        <tr><td class="px-3 py-1.5 font-medium">{{ $r['label'] }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmtH($r['minutes']) }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="px-4 py-6 text-center text-gray-400">Aucun arrêt sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Déclaration --}}
    @can('production.update')
    <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Déclarer un arrêt</div>
    <form method="POST" action="{{ route('production.downtimes.store') }}" class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] p-4 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">OF (optionnel)</label>
            <select name="production_order_id" class="{{ $sel }}">
                <option value="">—</option>
                @foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->number }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Machine (optionnel)</label>
            <select name="machine_id" class="{{ $sel }}">
                <option value="">— (déduite de l'OF)</option>
                @foreach($machinesList as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Catégorie *</label>
            <select name="category" class="{{ $sel }}" required>
                @foreach(\App\Modules\Production\Models\ProductionDowntime::CATEGORIES as $k => $lbl)<option value="{{ $k }}" @selected($k==='non_planifie')>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Cause *</label>
            <select name="reason" class="{{ $sel }}" required>
                @foreach(\App\Modules\Production\Models\ProductionDowntime::REASONS as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Début *</label>
            <input type="datetime-local" name="started_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="{{ $inp }}" required>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Fin (vide = en cours)</label>
            <input type="datetime-local" name="ended_at" class="{{ $inp }}">
        </div>
        <div class="sm:col-span-2 lg:col-span-1">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Description</label>
            <input name="description" class="{{ $inp }}" maxlength="255">
        </div>
        <div><button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold h-8 rounded-[3px]">Déclarer</button></div>
    </form>
    @endcan

    {{-- Journal --}}
    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#3b4248] text-white"><tr>
                <th class="{{ $th }} text-left">Début</th>
                <th class="{{ $th }} text-left">Fin</th>
                <th class="{{ $th }} text-right">Durée</th>
                <th class="{{ $th }} text-left">Machine</th>
                <th class="{{ $th }} text-left">OF</th>
                <th class="{{ $th }} text-left">Cause</th>
                <th class="{{ $th }} text-left">Catégorie</th>
                <th class="{{ $th }} text-right">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($downtimes as $d)
                <tr>
                    <td class="px-3 py-1.5 tabular-nums whitespace-nowrap">{{ $d->started_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-1.5 tabular-nums whitespace-nowrap">
                        @if($d->ended_at){{ $d->ended_at->format('d/m/Y H:i') }}@else<span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10px] font-semibold bg-amber-100 text-amber-700">En cours</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ number_format($d->effectiveMinutes(), 0, ',', ' ') }} min</td>
                    <td class="px-3 py-1.5">{{ $d->machine?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 font-mono text-emerald-800">{{ $d->productionOrder?->number ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ $d->reasonLabel() }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $d->categoryLabel() }}</td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        @can('production.update')
                        @if($d->isOngoing())
                        <form method="POST" action="{{ route('production.downtimes.close', $d) }}" class="inline">@csrf
                            <button class="text-[11px] font-semibold text-emerald-700 hover:underline">Clôturer</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('production.downtimes.destroy', $d) }}" class="inline ml-2" onsubmit="return confirm('Supprimer cet arrêt ?')">@csrf @method('DELETE')
                            <button class="text-[11px] font-semibold text-red-600 hover:underline">Suppr.</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucun arrêt déclaré sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Période : <span class="text-white font-semibold">{{ $days }} j</span></span>
        <span class="ml-auto"><a href="{{ route('production.planning') }}" class="text-emerald-300 hover:underline">→ Plan de charge</a></span>
    </div>
</div>
@endsection
