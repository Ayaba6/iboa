@extends('layouts.erp')
@section('title', 'CAPA — '.$nc->reference)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.non-conformities.index') }}" class="hover:text-gray-700">Non-conformités</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">CAPA {{ $nc->reference }}</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600';
    $ta  = 'w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $complete = $nc->capaComplete();
@endphp
<div class="space-y-4">

    {{-- En-tête NC --}}
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Actions correctives — {{ $nc->reference }}</h1>
            <p class="text-[12px] text-gray-500">{{ $nc->title }}</p>
        </div>
        <div class="flex items-center gap-2">
            @php $vc = match($nc->severity){ 'mineure'=>'bg-gray-100 text-gray-600','majeure'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $vc }}">{{ $nc->severityLabel() }}</span>
            @php $sc = match($nc->status){ 'ouverte'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-blue-100 text-blue-700',default=>'bg-emerald-100 text-emerald-700' }; @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $nc->statusLabel() }}</span>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($complete)
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2 font-semibold">✓ CAPA soldée — toutes les actions sont vérifiées efficaces. La non-conformité est clôturée.</div>
    @endif

    {{-- Nouvelle action --}}
    @can('production.update')
    <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Nouvelle action corrective / préventive</div>
    <form method="POST" action="{{ route('qualite.corrective-actions.store', $nc) }}" class="bg-white border border-t-0 border-gray-300 rounded-b-[4px] p-4 space-y-3">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="{{ $lbl }}">Type *</label>
                <select name="type" class="{{ $inp }}">
                    @foreach(\App\Modules\Quality\Models\CorrectiveAction::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type')===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Responsable</label>
                <select name="responsible_id" class="{{ $inp }}">
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('responsible_id')==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Échéance</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}" class="{{ $inp }}">
            </div>
        </div>
        <div>
            <label class="{{ $lbl }}">Cause racine (5 pourquoi, Ishikawa…)</label>
            <textarea name="root_cause" rows="2" class="{{ $ta }}">{{ old('root_cause') }}</textarea>
        </div>
        <div>
            <label class="{{ $lbl }}">Plan d'action *</label>
            <textarea name="action_plan" rows="2" class="{{ $ta }}" required>{{ old('action_plan') }}</textarea>
        </div>
        <div class="flex justify-end">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px]">+ Ajouter l'action</button>
        </div>
    </form>
    @endcan

    {{-- Liste des actions --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Réf.</th>
                        <th class="{{ $th }} text-left">Type</th>
                        <th class="{{ $th }} text-left">Cause racine / Plan d'action</th>
                        <th class="{{ $th }} text-left">Responsable</th>
                        <th class="{{ $th }} text-left">Échéance</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }} text-center">Efficacité</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nc->correctiveActions as $a)
                    <tr class="border-b border-gray-100 align-top odd:bg-white even:bg-gray-50/40">
                        <td class="px-3 py-2 font-mono text-emerald-800 whitespace-nowrap">{{ $a->reference }}</td>
                        <td class="px-3 py-2">{{ $a->typeLabel() }}</td>
                        <td class="px-3 py-2 max-w-[320px]">
                            @if($a->root_cause)<p class="text-gray-500 text-[11px] mb-0.5"><span class="font-semibold">Cause :</span> {{ $a->root_cause }}</p>@endif
                            <p class="text-gray-800">{{ $a->action_plan }}</p>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $a->responsible?->full_name ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap {{ $a->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ optional($a->due_date)->format('d/m/Y') ?? '—' }}{{ $a->isOverdue() ? ' ⚠' : '' }}</td>
                        <td class="px-3 py-2 text-center">
                            @php $ac = match($a->status){ 'a_faire'=>'bg-gray-100 text-gray-600','en_cours'=>'bg-blue-100 text-blue-700','faite'=>'bg-indigo-100 text-indigo-700','verifiee'=>'bg-emerald-100 text-emerald-700',default=>'bg-gray-100 text-gray-600' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $ac }}">{{ $a->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($a->is_effective === true)<span class="text-emerald-700 text-[11px] font-semibold">✓ Efficace</span>
                            @elseif($a->is_effective === false)<span class="text-red-600 text-[11px] font-semibold">✕ Inefficace</span>
                            @else<span class="text-gray-400 text-[11px]">—</span>@endif
                            @if($a->effectiveness_comment)<p class="text-gray-400 text-[10px] mt-0.5">{{ $a->effectiveness_comment }}</p>@endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @can('production.update')
                            @if(in_array($a->status, ['a_faire','en_cours']))
                            <form method="POST" action="{{ route('qualite.corrective-actions.status', $a) }}" class="inline">@csrf
                                <input type="hidden" name="status" value="{{ $a->status === 'a_faire' ? 'en_cours' : 'faite' }}">
                                <button class="text-blue-700 hover:underline text-[12px] font-semibold">{{ $a->status === 'a_faire' ? 'Démarrer' : 'Marquer réalisée' }}</button>
                            </form>
                            @elseif($a->status === 'faite')
                            <div class="inline-flex flex-col items-end gap-1">
                                <form method="POST" action="{{ route('qualite.corrective-actions.verify', $a) }}" class="flex items-center gap-1">@csrf
                                    <input type="hidden" name="is_effective" value="1">
                                    <input type="text" name="comment" placeholder="Constat" class="h-7 py-0 px-1 w-28 border border-gray-300 rounded-[3px] text-[11px]">
                                    <button class="text-emerald-700 hover:underline text-[12px] font-semibold">Efficace</button>
                                </form>
                                <form method="POST" action="{{ route('qualite.corrective-actions.verify', $a) }}" class="inline">@csrf
                                    <input type="hidden" name="is_effective" value="0">
                                    <button class="text-red-600 hover:underline text-[11px]">Inefficace → retravailler</button>
                                </form>
                            </div>
                            @else
                            <span class="text-emerald-700 text-[11px]">✓ {{ optional($a->verified_at)->format('d/m/Y') }}</span>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">Aucune action. Ajoutez le plan d'action corrigeant cette non-conformité.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">NC : <span class="text-white font-semibold">{{ $nc->reference }}</span></span>
        <span class="border-l border-white/10 pl-6">Actions : <span class="text-white font-semibold">{{ $nc->correctiveActions->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
