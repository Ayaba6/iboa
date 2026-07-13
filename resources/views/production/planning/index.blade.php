@extends('layouts.erp')
@section('title', 'Plan de charge')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Plan de charge</span>
@endsection

@section('content')
@php
    $lk  = 'appearance-none h-8 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $tauxGlobal = $plan['total_capacity_h'] > 0 ? $plan['total_planned_h'] / $plan['total_capacity_h'] * 100 : 0;
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Plan de charge</h1>
            <p class="text-[12px] text-gray-500">Capacité vs charge planifiée par centre de travail (OF actifs)</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-[11px] font-bold text-gray-700">Horizon</label>
            <div class="relative">
                <select name="horizon" class="{{ $lk }}" onchange="this.form.submit()">
                    @foreach([1,3,7,14,30] as $h)<option value="{{ $h }}" @selected($horizon==$h)>{{ $h }} jour(s)</option>@endforeach
                </select>
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>
            </div>
        </form>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach([
            ['label' => 'Charge planifiée',      'value' => number_format($plan['total_planned_h'], 1, ',', ' ') . ' h', 'color' => 'text-gray-900',    'bg' => 'bg-[#eef5f0]', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'Capacité (' . $horizon . ' j)', 'value' => number_format($plan['total_capacity_h'], 1, ',', ' ') . ' h', 'color' => 'text-gray-900', 'bg' => 'bg-blue-50', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Centres en surcharge',  'value' => number_format($plan['overloaded'], 0, ',', ' '),             'color' => $plan['overloaded'] > 0 ? 'text-red-600' : 'text-gray-900', 'bg' => 'bg-red-50', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['label' => 'Taux global',           'value' => number_format($tauxGlobal, 0, ',', ' ') . ' %',              'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg style="width:18px;height:18px" class="{{ str_contains($kpi['color'], 'red') ? 'text-red-500' : (str_contains($kpi['color'], 'emerald') ? 'text-emerald-600' : 'text-gray-500') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 truncate">{{ $kpi['label'] }}</p>
                <p class="text-[16px] font-bold {{ $kpi['color'] }} tabular-nums leading-tight">{{ $kpi['value'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Table — onglets Centre / Machine / Équipe --}}
    @php
        $barClass = fn ($s) => match($s){ 'surcharge'=>'bg-red-500','charge'=>'bg-amber-500','libre'=>'bg-gray-300',default=>'bg-emerald-500' };
        $occCell = function ($r) use ($barClass) {
            $b = $barClass($r['status']); $o = $r['occupation'];
            return '<div class="flex items-center gap-2"><div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full '.$b.'" style="width: '.min(100,$o).'%"></div></div><span class="text-[11.5px] tabular-nums w-12 text-right '.($r['status']==='surcharge'?'text-red-600 font-semibold':'text-gray-600').'">'.number_format($o,0,',',' ').' %</span></div>';
        };
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden" x-data="{ tab: 'centre' }">
        <div class="px-3 pt-2 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex items-center gap-1">
            @foreach(['centre' => 'Par centre de travail', 'machine' => 'Par machine', 'equipe' => 'Par équipe'] as $k => $lbl)
            <button type="button" @click="tab = '{{ $k }}'"
                    class="px-3 py-1.5 text-[12.5px] font-semibold rounded-t-[4px] -mb-px border-b-2 transition-colors"
                    :class="tab === '{{ $k }}' ? 'border-emerald-600 text-emerald-800 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $lbl }}</button>
            @endforeach
        </div>

        {{-- Centre --}}
        <div x-show="tab === 'centre'" class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white"><tr>
                    <th class="{{ $th }} text-left">Centre</th>
                    <th class="{{ $th }} text-right">Opérations</th>
                    <th class="{{ $th }} text-right">Charge</th>
                    <th class="{{ $th }} text-right">Capacité</th>
                    <th class="{{ $th }} text-left w-1/3">Occupation</th>
                </tr></thead>
                <tbody>
                    @forelse($plan['rows'] as $r)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5"><span class="font-medium text-gray-900">{{ $r['name'] }}</span><span class="text-emerald-800 font-mono text-[11px] ml-1">{{ $r['code'] }}</span></td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $r['ops'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($r['planned_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($r['capacity_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5">{!! $occCell($r) !!}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun centre de travail. Créez-en + affectez des gammes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Machine --}}
        <div x-show="tab === 'machine'" x-cloak class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white"><tr>
                    <th class="{{ $th }} text-left">Machine</th>
                    <th class="{{ $th }} text-right">Charge</th>
                    <th class="{{ $th }} text-right">Capacité brute</th>
                    <th class="{{ $th }} text-right" title="Temps d'arrêt déclaré sur l'horizon">Arrêts</th>
                    <th class="{{ $th }} text-right">Capacité nette</th>
                    <th class="{{ $th }} text-left w-1/4">Occupation</th>
                </tr></thead>
                <tbody>
                    @forelse($planMachine['rows'] as $r)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5"><span class="font-medium text-gray-900">{{ $r['name'] }}</span><span class="text-emerald-800 font-mono text-[11px] ml-1">{{ $r['code'] }}</span></td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($r['planned_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($r['capacity_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $r['downtime_h'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($r['downtime_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($r['net_capacity_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5">{!! $occCell($r) !!}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune machine rattachée à un centre de travail.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Équipe --}}
        <div x-show="tab === 'equipe'" x-cloak class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white"><tr>
                    <th class="{{ $th }} text-left">Équipe</th>
                    <th class="{{ $th }} text-right">Centres</th>
                    <th class="{{ $th }} text-right">Charge</th>
                    <th class="{{ $th }} text-right">Capacité</th>
                    <th class="{{ $th }} text-left w-1/3">Occupation</th>
                </tr></thead>
                <tbody>
                    @forelse($planTeam['rows'] as $r)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $r['name'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $r['centers'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($r['planned_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($r['capacity_h'], 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5">{!! $occCell($r) !!}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune équipe définie sur les centres de travail.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            Charge = temps prévu des opérations non terminées sur OF lancés/en cours. Capacité = capacité journalière × rendement × horizon.
            La capacité nette machine déduit les <a href="{{ route('production.downtimes') }}" class="text-emerald-700 hover:underline">temps d'arrêt</a> déclarés.
        </div>
    </div>

    {{-- [X3 §19] Replanification — déplacer OF / réaffecter ligne --}}
    @can('production.create')
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Replanification des OF actifs</h2>
            <p class="text-[11px] text-gray-500">Déplacer les dates prévues ou réaffecter la ligne — OF clôturés/annulés exclus.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-left whitespace-nowrap">N° OF</th>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-left">Article</th>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-left hidden lg:table-cell">Client</th>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-center">Statut</th>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-left">Ligne</th>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-left whitespace-nowrap">Fabrication</th>
                        <th class="px-3 py-1.5 text-[11px] font-semibold uppercase text-left whitespace-nowrap">Fin prévue</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ofActifs as $of)
                    @php
                        $enRetard = $of->date_fin_prevue && $of->date_fin_prevue->isPast()
                                    && in_array($of->status, ['lance', 'en_cours', 'suspendu'], true);
                        $inpMini  = 'h-7 border border-[#c3d3c9] rounded-[3px] px-1.5 py-0 text-[12px] bg-white focus:outline-none focus:border-emerald-600';
                    @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            {{-- form vide + attribut form="" sur les champs : un <form> ne peut pas enjamber plusieurs <td> --}}
                            <form id="replan-{{ $of->id }}" method="POST" action="{{ route('production.planning.replan', $of) }}">@csrf</form>
                            <a href="{{ route('production.orders.show', $of) }}" class="font-mono text-emerald-800 hover:underline">{{ $of->number }}</a>
                            @if($enRetard)<span class="ml-1 inline-block w-2 h-2 rounded-full bg-red-500" title="En retard"></span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 max-w-[180px] truncate" title="{{ $of->product?->name }}">{{ $of->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 hidden lg:table-cell max-w-[130px] truncate">{{ $of->client?->trade_name ?? $of->client?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php $badge = match($of->status){
                                'brouillon' => 'bg-gray-100 text-gray-600',
                                'lance'     => 'bg-blue-100 text-blue-700',
                                'en_cours'  => 'bg-emerald-100 text-emerald-700',
                                'suspendu'  => 'bg-orange-100 text-orange-700',
                                default     => 'bg-amber-100 text-amber-700',
                            }; @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium {{ $badge }}">{{ $of->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="relative">
                                <select name="production_line_id" form="replan-{{ $of->id }}" class="{{ $inpMini }} appearance-none pr-6 min-w-[120px]">
                                    <option value="">—</option>
                                    @foreach($lignes as $l)
                                    <option value="{{ $l->id }}" @selected($of->production_line_id == $l->id)>{{ $l->name }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[10px]">&#9662;</span>
                            </div>
                        </td>
                        <td class="px-3 py-1.5">
                            <input type="date" name="date_fabrication_prevue" form="replan-{{ $of->id }}" value="{{ $of->date_fabrication_prevue?->format('Y-m-d') }}" class="{{ $inpMini }}">
                        </td>
                        <td class="px-3 py-1.5">
                            <input type="date" name="date_fin_prevue" form="replan-{{ $of->id }}" value="{{ $of->date_fin_prevue?->format('Y-m-d') }}" class="{{ $inpMini }} {{ $enRetard ? 'border-red-400' : '' }}">
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <button form="replan-{{ $of->id }}" class="text-[12px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-2.5 py-1 rounded-[3px]">Appliquer</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Aucun OF actif à replanifier.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endcan

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Plan de charge ({{ $horizon }} j)</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
