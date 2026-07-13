@extends('layouts.erp')
@section('title', 'Actions correctives (CAPA)')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Actions correctives</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
@endphp
<div class="space-y-4">

    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Actions correctives &amp; préventives (CAPA)</h1>
        <p class="text-[12px] text-gray-500">Registre transverse — plans d'action, responsables, délais et vérification d'efficacité.</p>
    </div>

    <div class="grid grid-cols-3 gap-3">
        @foreach([
            ['label'=>'Ouvertes','value'=>$stats['ouvertes'],'color'=>$stats['ouvertes']>0?'text-amber-600':'text-gray-900'],
            ['label'=>'En retard','value'=>$stats['en_retard'],'color'=>$stats['en_retard']>0?'text-red-600':'text-gray-900'],
            ['label'=>'Inefficaces','value'=>$stats['inefficaces'],'color'=>$stats['inefficaces']>0?'text-red-600':'text-gray-900'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[11px] text-gray-500">{{ $kpi['label'] }}</p>
            <p class="text-[18px] font-bold {{ $kpi['color'] }} tabular-nums leading-tight">{{ number_format($kpi['value'],0,',',' ') }}</p>
        </div>
        @endforeach
    </div>

    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative"><select name="status" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(\App\Modules\Quality\Models\CorrectiveAction::STATUSES as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Type</label>
                <div class="relative"><select name="type" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(\App\Modules\Quality\Models\CorrectiveAction::TYPES as $k=>$v)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">En retard</label>
                <div class="relative"><select name="overdue" class="{{ $lk }}">
                    <option value="">Tous</option>
                    <option value="1" @selected(request('overdue'))>Seulement en retard</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px]">Rechercher</button>
                @if(request()->hasAny(['status','type','overdue']))
                <a href="{{ route('qualite.corrective-actions.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center">Réinitialiser</a>
                @endif
            </div>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Réf.</th>
                        <th class="{{ $th }} text-left">NC</th>
                        <th class="{{ $th }} text-left">Type</th>
                        <th class="{{ $th }} text-left">Plan d'action</th>
                        <th class="{{ $th }} text-left">Responsable</th>
                        <th class="{{ $th }} text-left">Échéance</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }} text-center">Efficacité</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $a)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $a->reference }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <a href="{{ route('qualite.corrective-actions.nc', $a->non_conformity_id) }}" class="text-blue-700 hover:underline">{{ $a->nonConformity?->reference ?? '#'.$a->non_conformity_id }}</a>
                        </td>
                        <td class="px-3 py-1.5">{{ $a->typeLabel() }}</td>
                        <td class="px-3 py-1.5 max-w-[280px] truncate text-gray-800">{{ $a->action_plan }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap text-gray-600">{{ $a->responsible?->full_name ?? '—' }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap {{ $a->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ optional($a->due_date)->format('d/m/Y') ?? '—' }}{{ $a->isOverdue() ? ' ⚠' : '' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php $ac = match($a->status){ 'a_faire'=>'bg-gray-100 text-gray-600','en_cours'=>'bg-blue-100 text-blue-700','faite'=>'bg-indigo-100 text-indigo-700','verifiee'=>'bg-emerald-100 text-emerald-700',default=>'bg-gray-100 text-gray-600' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $ac }}">{{ $a->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @if($a->is_effective === true)<span class="text-emerald-700 text-[11px] font-semibold">✓</span>
                            @elseif($a->is_effective === false)<span class="text-red-600 text-[11px] font-semibold">✕</span>
                            @else<span class="text-gray-400">—</span>@endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune action corrective.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $items->total() }} action(s) — {{ $stats['ouvertes'] }} ouverte(s) — {{ $stats['en_retard'] }} en retard</span>
            @if($items->hasPages())<div>{{ $items->links() }}</div>@endif
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Actions correctives</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
