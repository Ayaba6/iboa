@extends('layouts.erp')
@section('title', 'Nomenclature '.$bom->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.bom.index') }}" class="hover:text-gray-700">Nomenclatures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $bom->name }}</span>
@endsection

@section('content')
@php
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $dt   = 'text-[11px] font-bold text-gray-500';
    $dd   = 'text-[13px] text-gray-900';
@endphp
<div class="max-w-4xl mx-auto space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2.5 bg-gradient-to-b from-gray-50 to-white flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[15px] font-bold text-gray-900">{{ $bom->name }}</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $bom->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $bom->is_active ? 'Active' : 'Inactive' }}</span>
                @if($bom->statut)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-700">{{ ucfirst($bom->statut) }}</span>
                @endif
            </div>
            <p class="text-[12px] text-gray-500 mt-0.5">{{ $bom->product?->name ?? 'Sans produit fini' }} · {{ $bom->sheet_type ?? '—' }}</p>
        </div>
        <div class="flex items-center gap-2">
            @can('production.update')
            <a href="{{ route('production.bom.edit', $bom) }}" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Modifier</a>
            @endcan
            <a href="{{ route('production.bom.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Retour</a>
        </div>
    </div>

    {{-- Entête article composé (parité SAGE) --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Article composé</div>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3 p-4">
            <div><dt class="{{ $dt }}">Site</dt><dd class="{{ $dd }} font-mono">{{ $bom->site ?? '—' }}</dd></div>
            <div><dt class="{{ $dt }}">Alternative</dt><dd class="{{ $dd }} font-mono">{{ $bom->alternative ?? '—' }}</dd></div>
            <div><dt class="{{ $dt }}">Version</dt><dd class="{{ $dd }} font-mono">{{ $bom->version_majeure ?? '—' }}.{{ $bom->version_mineure ?? '0' }}</dd></div>
            <div><dt class="{{ $dt }}">Quantité de base</dt><dd class="{{ $dd }} tabular-nums">{{ number_format((float) ($bom->quantite_base ?? 1), 0, ',', ' ') }}</dd></div>
            <div><dt class="{{ $dt }}">Unité de gestion</dt><dd class="{{ $dd }}">{{ $bom->uniteGestion?->abbreviation ?? $bom->uniteGestion?->name ?? '—' }}</dd></div>
            <div><dt class="{{ $dt }}">Date référence</dt><dd class="{{ $dd }}">{{ $bom->date_reference?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="{{ $dt }}">Début validité</dt><dd class="{{ $dd }}">{{ $bom->date_debut_validite?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="{{ $dt }}">Fin validité</dt><dd class="{{ $dd }}">{{ $bom->date_fin_validite?->format('d/m/Y') ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Paramètres de fabrication --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Paramètres de fabrication</div>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3 p-4">
            <div><dt class="{{ $dt }}">Épaisseur</dt><dd class="{{ $dd }} tabular-nums">{{ $bom->thickness ?? '—' }} mm</dd></div>
            <div><dt class="{{ $dt }}">Largeur bobine</dt><dd class="{{ $dd }} tabular-nums">{{ $bom->coil_width ?? '—' }} mm</dd></div>
            <div><dt class="{{ $dt }}">Largeur utile</dt><dd class="{{ $dd }} tabular-nums">{{ $bom->usable_width ?? '—' }} mm</dd></div>
            <div><dt class="{{ $dt }}">Conso / mètre</dt><dd class="{{ $dd }} font-mono">{{ number_format($bom->consumption_per_meter,4,',',' ') }} kg</dd></div>
            <div><dt class="{{ $dt }}">Taux chute std.</dt><dd class="{{ $dd }} tabular-nums">{{ number_format($bom->standard_waste_rate,2,',',' ') }} %</dd></div>
            <div><dt class="{{ $dt }}">Rendement std.</dt><dd class="{{ $dd }} tabular-nums">{{ $bom->rendement_standard !== null ? number_format($bom->rendement_standard,2,',',' ').' %' : '—' }}</dd></div>
            <div><dt class="{{ $dt }}">Temps machine / u</dt><dd class="{{ $dd }} tabular-nums">{{ $bom->machine_time_per_unit ?? '—' }} min</dd></div>
            <div><dt class="{{ $dt }}">MO / u</dt><dd class="{{ $dd }} font-mono">{{ number_format($bom->labor_per_unit,0,',',' ') }} F</dd></div>
            <div><dt class="{{ $dt }}">Emballage / u</dt><dd class="{{ $dd }} font-mono">{{ number_format($bom->packaging_per_unit,0,',',' ') }} F</dd></div>
            <div><dt class="{{ $dt }}">Contrôle qualité</dt><dd class="{{ $dd }}">{{ $bom->controle_qualite ? 'Obligatoire' : 'Non' }}</dd></div>
        </dl>
    </div>

    {{-- Coûts standards (§11 CDC) --}}
    @php
        $stdCosts = [
            'Matière'     => $bom->std_material_cost,
            'Main d\'œuvre' => $bom->std_labor_cost,
            'Machine'     => $bom->std_machine_cost,
            'Énergie'     => $bom->std_energy_cost,
            'Maintenance' => $bom->std_maintenance_cost,
            'Emballage'   => $bom->std_packaging_cost,
            'Indirect'    => $bom->std_overhead_cost,
        ];
        $stdTotal = (int) collect($stdCosts)->sum();
    @endphp
    @if($stdTotal > 0)
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Coûts standards <span class="font-normal text-emerald-800/70">— §11 CDC, comparaison coût std/réel</span></div>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3 p-4">
            @foreach($stdCosts as $cl => $cv)
            <div><dt class="{{ $dt }}">{{ $cl }}</dt><dd class="{{ $dd }} font-mono">{{ number_format((int) $cv, 0, ',', ' ') }} F</dd></div>
            @endforeach
            <div><dt class="{{ $dt }}">Total standard / u</dt><dd class="text-[13px] font-bold text-emerald-800 font-mono">{{ number_format($stdTotal, 0, ',', ' ') }} F</dd></div>
        </dl>
    </div>
    @endif

    {{-- Composants --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Composants ({{ $bom->lines->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-left">Substitut</th>
                        <th class="{{ $th }} text-left">Libellé</th>
                        <th class="{{ $th }} text-right">Qté / mètre</th>
                        <th class="{{ $th }} text-left">Unité</th>
                        <th class="{{ $th }} text-right">Chute %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bom->lines as $l)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-900">{{ $l->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500">{{ $l->substitute?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $l->label ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-900">{{ number_format($l->quantity_per_meter,4,',',' ') }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $l->unit?->abbreviation ?? $l->unit?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($l->waste_rate,2,',',' ') }} %</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Aucun composant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Explosion multi-niveaux --}}
    @if(isset($explosion) && count($explosion))
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Nomenclature multi-niveaux (explosion)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Composant</th>
                        <th class="{{ $th }} text-right">Quantité / unité</th>
                        <th class="{{ $th }} text-left">Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($explosion as $row)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-900">
                            <span style="padding-left: {{ $row['depth'] * 18 }}px">{{ $row['depth'] > 0 ? '└ ' : '' }}{{ $row['label'] }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700">{{ number_format($row['quantity'], 4, ',', ' ') }}</td>
                        <td class="px-3 py-1.5">
                            @if($row['is_semi_finished'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700">Semi-fini{{ $row['has_sub_bom'] ? ' ▼' : '' }}</span>
                            @else
                                <span class="text-gray-400 text-[11.5px]">Matière / composant</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-3 py-2 text-[11.5px] text-gray-500 border-t border-gray-200 bg-[#f7faf8]">Les composants semi-finis ▼ sont explosés selon leur propre nomenclature (assemblages charpentes/hangars).</p>
    </div>
    @endif
</div>
@endsection
