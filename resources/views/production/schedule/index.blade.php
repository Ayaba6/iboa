@extends('layouts.erp')
@section('title', 'Ordonnancement — production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Ordonnancement</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ═══ Bandeau SAGE X3 ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Ordonnancement</h2>
                <p class="text-[11.5px] text-gray-400">
                    Positionne chaque OF sur une ligne, à une date et une heure. Une ligne ne produit
                    qu’un ordre à la fois — les chevauchements sont refusés.
                </p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('production.planning') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors"
                   title="Le plan de charge mesure la capacité ; l’ordonnancement place les ordres.">Plan de charge</a>
                <a href="{{ route('production.orders.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Ordres de fabrication</a>
            </div>
        </div>
        <form method="GET" class="px-4 py-2 border-t border-gray-200 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Du</label>
                <input type="date" name="du" value="{{ $du->toDateString() }}"
                       class="h-8 py-0 text-[13px] border border-gray-300 rounded-[3px] px-2">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Au</label>
                <input type="date" name="au" value="{{ $au->toDateString() }}"
                       class="h-8 py-0 text-[13px] border border-gray-300 rounded-[3px] px-2">
            </div>
            <button type="submit" class="h-8 px-4 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold rounded-[3px]">Afficher</button>
            <span class="ml-auto text-[12px] text-gray-600">
                <span class="font-semibold text-gray-900 tabular-nums">{{ $places->count() }}</span> placé(s) ·
                <span class="font-semibold text-amber-700 tabular-nums">{{ $a_placer->count() }}</span> à placer
            </span>
        </form>
    </div>

    {{-- ═══ OF placés, groupés par ligne ═══ --}}
    @foreach($lignes as $ligne)
        @php $surLigne = $places->where('production_line_id', $ligne->id)->sortBy(fn ($o) => $o->date_debut_prevue); @endphp
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex items-center justify-between">
                <h2 class="text-[13px] font-bold text-gray-900">
                    {{ $ligne->name }}
                    <span class="text-[11px] text-gray-400 font-mono ml-1">{{ $ligne->code }}</span>
                </h2>
                @if(in_array($ligne->status, ['indisponible', 'arretee', 'en_panne'], true))
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">{{ $ligne->status }}</span>
                @else
                    <span class="text-[11px] text-gray-500 tabular-nums">{{ $surLigne->count() }} ordre(s)</span>
                @endif
            </div>

            @if($surLigne->isEmpty())
                <p class="px-3 py-3 text-center text-gray-400 text-[12.5px]">Aucun ordre placé sur cette ligne pour la période.</p>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-[#eef5f0] border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">OF</th>
                            <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Article</th>
                            <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Début</th>
                            <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Fin</th>
                            <th class="px-3 py-1 text-right text-[11px] font-bold text-emerald-900 uppercase">Quantité</th>
                            <th class="px-3 py-1 text-center text-[11px] font-bold text-emerald-900 uppercase">Statut</th>
                            <th class="px-3 py-1 text-right text-[11px] font-bold text-emerald-900 uppercase w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($surLigne as $o)
                        <tr class="hover:bg-[#eef5f0]/40">
                            <td class="px-3 py-1 font-mono text-[12px]">
                                <a href="{{ route('production.orders.show', $o) }}" class="text-emerald-800 hover:underline">{{ $o->number }}</a>
                            </td>
                            <td class="px-3 py-1 text-gray-700">{{ $o->product?->name ?? '—' }}</td>
                            {{-- Dates effectives : le service applique le repli sur
                                 date_fabrication_prevue, la vue doit montrer la même. --}}
                            <td class="px-3 py-1 tabular-nums text-gray-700">
                                {{ optional($o->debut_effectif)->format('d/m/Y H:i') ?? '—' }}
                                @unless($o->date_debut_prevue)
                                    <span class="text-amber-600 text-[11px]" title="Repli sur la date de fabrication prévue : aucun créneau fin n’a été saisi.">hérité</span>
                                @endunless
                            </td>
                            <td class="px-3 py-1 tabular-nums text-gray-700">
                                {{ optional($o->fin_effectif)->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ number_format((float) $o->quantity_requested, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1 text-center text-[11px] text-gray-600">{{ $o->status }}</td>
                            <td class="px-3 py-1 text-right">
                                <form method="POST" action="{{ route('production.schedule.clear', $o) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[12px] text-gray-500 hover:text-red-700 hover:underline">Retirer</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    @endforeach

    {{-- ═══ OF à placer ═══ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">À placer</h2>
            <p class="text-[11px] text-gray-500">Ordres sans ligne ou sans créneau — un OF ne se produit pas « quelque part, un jour ».</p>
        </div>
        @if($a_placer->isEmpty())
            <p class="px-3 py-3 text-center text-gray-400 text-[12.5px]">Tous les ordres actifs sont placés.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">OF</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Article</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Ligne</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Début</th>
                        <th class="px-3 py-1 text-left text-[11px] font-bold text-emerald-900 uppercase">Fin</th>
                        <th class="px-3 py-1 text-right text-[11px] font-bold text-emerald-900 uppercase w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($a_placer as $o)
                    <tr class="hover:bg-[#eef5f0]/40">
                        <td class="px-3 py-1 font-mono text-[12px]">
                            <a href="{{ route('production.orders.show', $o) }}" class="text-emerald-800 hover:underline">{{ $o->number }}</a>
                        </td>
                        <td class="px-3 py-1 text-gray-700">{{ $o->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1">
                            <select name="production_line_id" form="sch-{{ $o->id }}" class="h-7 py-0 text-[12px] border border-gray-300 rounded-[3px]">
                                <option value="">—</option>
                                @foreach($lignes as $l)
                                    <option value="{{ $l->id }}" @selected($o->production_line_id === $l->id)>{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-3 py-1 whitespace-nowrap">
                            <input type="date" name="date_debut_prevue" form="sch-{{ $o->id }}"
                                   value="{{ optional($o->debut_effectif)->format('Y-m-d') }}"
                                   class="h-7 py-0 text-[12px] border border-gray-300 rounded-[3px] px-1">
                            <input type="time" name="heure_debut_prevue" form="sch-{{ $o->id }}" value="{{ $o->heure_debut_prevue }}"
                                   class="h-7 py-0 text-[12px] border border-gray-300 rounded-[3px] px-1 w-24">
                        </td>
                        <td class="px-3 py-1 whitespace-nowrap">
                            <input type="date" name="date_fin_prevue" form="sch-{{ $o->id }}"
                                   value="{{ optional($o->date_fin_prevue)->format('Y-m-d') }}"
                                   class="h-7 py-0 text-[12px] border border-gray-300 rounded-[3px] px-1">
                            <input type="time" name="heure_fin_prevue" form="sch-{{ $o->id }}" value="{{ $o->heure_fin_prevue }}"
                                   class="h-7 py-0 text-[12px] border border-gray-300 rounded-[3px] px-1 w-24">
                        </td>
                        <td class="px-3 py-1 text-right">
                            <button type="submit" form="sch-{{ $o->id }}" class="px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[3px]">Placer</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Les <form> vivent HORS du tableau : un <form> entre <tr> et <td> est du
             HTML invalide que le navigateur remonte hors de la table, ce qui vide la
             soumission. L'attribut HTML5 `form="…"` relie les champs à leur formulaire. --}}
        @foreach($a_placer as $o)
            <form id="sch-{{ $o->id }}" method="POST" action="{{ route('production.schedule.assign', $o) }}" class="hidden">@csrf</form>
        @endforeach
        @endif
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Période : <span class="text-white font-semibold tabular-nums">{{ $du->format('d/m/Y') }} → {{ $au->format('d/m/Y') }}</span></span>
        <span class="border-l border-white/10 pl-6">Module : <span class="text-white font-semibold">production — ordonnancement</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
