@extends('layouts.erp')
@section('title', 'Formation & compétences')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Formation</span>
@endsection

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F'; @endphp
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Formation &amp; compétences</h1>
            <p class="text-sm text-gray-500">Sessions, coûts, présences, évaluations, habilitations et échéances de certificats.</p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.formations.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 py-2 rounded-[4px]">+ Nouvelle session</a>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-blue-200 rounded-[4px] p-4"><p class="text-xs text-blue-600 uppercase">Planifiées</p><p class="text-2xl font-bold text-blue-700 tabular-nums">{{ $stats['planifiees'] }}</p></div>
        <div class="bg-white border border-emerald-200 rounded-[4px] p-4"><p class="text-xs text-emerald-600 uppercase">Terminées</p><p class="text-2xl font-bold text-emerald-700 tabular-nums">{{ $stats['terminees'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Coût {{ now()->year }}</p><p class="text-xl font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($stats['cout_annee']) }}</p></div>
        <div class="bg-white border border-amber-200 rounded-[4px] p-4"><p class="text-xs text-amber-600 uppercase">Habilitations à échéance</p><p class="text-2xl font-bold text-amber-700 tabular-nums">{{ $stats['habilit_echeance'] }}</p><p class="text-[10px] text-gray-400">≤ 60 jours</p></div>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Recherche</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Intitulé, compétence…" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]">
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut</label>
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\TrainingSession::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end"><button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button></div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Intitulé</th>
                    <th class="px-3 py-2 text-left">Compétence</th>
                    <th class="px-3 py-2 text-left">Organisme</th>
                    <th class="px-3 py-2 text-left">Dates</th>
                    <th class="px-3 py-2 text-right">Participants</th>
                    <th class="px-3 py-2 text-right">Coût</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($sessions as $s)
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5"><a href="{{ route('rh.formations.show', $s) }}" class="text-blue-700 hover:underline font-medium">{{ $s->title }}</a></td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $s->competence ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $s->provider ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600 tabular-nums text-xs">{{ optional($s->start_date)->format('d/m/Y') ?? '—' }}{{ $s->end_date ? ' → '.$s->end_date->format('d/m/Y') : '' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $s->participants_count }}{{ $s->max_participants ? ' / '.$s->max_participants : '' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $s->cost !== null ? $fmt($s->cost) : '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @php $sc = ['planifiee'=>'text-blue-700','en_cours'=>'text-amber-600','terminee'=>'text-emerald-700','annulee'=>'text-gray-400'][$s->status] ?? 'text-gray-500'; @endphp
                        <span class="{{ $sc }} text-xs font-semibold">{{ $s->statusLabel() }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune session de formation.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Sessions : <span class="text-white font-semibold">{{ $sessions->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
