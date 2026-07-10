@extends('layouts.erp')
@section('title', 'Soldes Intermédiaires de Gestion')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Soldes Intermédiaires de Gestion</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
    $signClass = fn($n) => $n >= 0 ? 'text-emerald-700' : 'text-red-600';
    // Helper pour afficher une ligne de cascade : niveau cumul (sg_X) en gras, lignes détail en normal
    $level = function (string $label, int $value, string $variant = 'detail') use ($fmt, $signClass) {
        $bg   = $variant === 'cumul' ? 'bg-violet-50' : '';
        $font = $variant === 'cumul' ? 'font-bold' : ($variant === 'sub' ? 'font-medium' : '');
        $size = $variant === 'cumul' ? 'text-base' : 'text-sm';
        return [
            'bg'    => $bg,
            'font'  => $font,
            'size'  => $size,
            'label' => $label,
            'value' => $value,
            'sign'  => $signClass($value),
        ];
    };
@endphp

<div class="max-w-5xl mx-auto space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Soldes Intermédiaires de Gestion</h1>
            <p class="text-sm text-gray-500 mt-0.5">Cascade analytique des résultats — conforme SYSCOHADA révisé.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Exercice</label>
                <select name="fiscal_year_id" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    @foreach($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" {{ $fiscalYear?->id === $fy->id ? 'selected' : '' }}>
                        {{ $fy->label }}
                        @if($fy->status !== 'ouvert') ({{ ucfirst($fy->status) }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if(!$fiscalYear)
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4 text-sm text-amber-700">
            Aucun exercice fiscal défini. Créez-en un depuis « Paramètres → Exercices ».
        </div>
    @elseif(!$sig || $sig['ca'] === 0)
        <div class="bg-gray-50 border border-gray-300 rounded-[4px] p-6 text-center text-gray-500 text-sm">
            Aucune écriture validée trouvée pour l'exercice <strong>{{ $fiscalYear->label }}</strong>.
        </div>
    @else

    {{-- KPI cards : ratios clés --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Chiffre d'affaires</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-gray-900">{{ $fmt($sig['ca']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Taux de VA</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums {{ $signClass($sig['ratio_va_ca']) }}">{{ $sig['ratio_va_ca'] }}%</p>
            <p class="text-xs text-gray-400 mt-0.5">VA / CA</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Marge EBE</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums {{ $signClass($sig['ratio_ebe_ca']) }}">{{ $sig['ratio_ebe_ca'] }}%</p>
            <p class="text-xs text-gray-400 mt-0.5">EBE / CA</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Rentabilité nette</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums {{ $signClass($sig['ratio_resultat_ca']) }}">{{ $sig['ratio_resultat_ca'] }}%</p>
            <p class="text-xs text-gray-400 mt-0.5">Résultat net / CA</p>
        </div>
    </div>

    {{-- Cascade détaillée --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Cascade des soldes — {{ $fiscalYear->label }}</h2>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-50">
                @php
                    $rows = [
                        // (label, value, variant)
                        ['Ventes de marchandises (701)',              null,                            'header'],
                        [' − Achats de marchandises (601)',            null,                            'detail'],
                        [' − Variation stocks marchandises (6031)',    null,                            'detail'],
                        ['= MARGE COMMERCIALE',                        $sig['marge_commerciale'],       'cumul'],
                        ['Ventes de produits (702-705)',               null,                            'header'],
                        [' − Achats matières (602-604-608)',           null,                            'detail'],
                        [' − Variation stocks matières (6032/6033)',   null,                            'detail'],
                        ['= MARGE SUR MATIÈRES',                       $sig['marge_matieres'],          'cumul'],
                        ['+ Production de l\'exercice (vendue + immob. + var.)', $sig['production_exercice'], 'sub'],
                        ['− Consommations externes (61, 62, 605)',     -$sig['consommations_externes'], 'sub'],
                        ['= VALEUR AJOUTÉE (VA)',                      $sig['valeur_ajoutee'],          'cumul'],
                        ['+ Subventions d\'exploitation (71)',         $sig['subventions_expl'],        'sub'],
                        ['− Impôts et taxes (64)',                     -$sig['impots_taxes'],           'sub'],
                        ['− Charges de personnel (66)',                -$sig['charges_personnel'],      'sub'],
                        ['= EXCÉDENT BRUT D\'EXPLOITATION (EBE)',      $sig['ebe'],                     'cumul'],
                        ['+ Autres produits d\'exploitation (75)',     $sig['autres_produits_expl'],    'sub'],
                        ['− Autres charges d\'exploitation (65)',      -$sig['autres_charges_expl'],    'sub'],
                        ['+ Reprises sur provisions (78, 79)',         $sig['reprises_expl'],           'sub'],
                        ['− Dotations aux amortissements (68, 69)',    -$sig['dotations_expl'],         'sub'],
                        ['= RÉSULTAT D\'EXPLOITATION',                 $sig['resultat_exploitation'],   'cumul'],
                        ['+ Produits financiers (77)',                 $sig['produits_financiers'],     'sub'],
                        ['− Charges financières (67)',                 -$sig['charges_financieres'],    'sub'],
                        ['= RÉSULTAT FINANCIER',                       $sig['resultat_financier'],      'cumul'],
                        ['+ Produits HAO (84, 85, 86, 88)',            $sig['produits_hao'],            'sub'],
                        ['− Charges HAO (81, 82, 83, 87)',             -$sig['charges_hao'],            'sub'],
                        ['= RÉSULTAT HAO',                             $sig['resultat_hao'],            'cumul'],
                        ['= RÉSULTAT AVANT IMPÔTS',                    $sig['resultat_avant_impot'],    'cumul'],
                        ['− Impôt sur le résultat (89)',               -$sig['impot_sur_resultat'],     'sub'],
                        ['= RÉSULTAT NET DE L\'EXERCICE',              $sig['resultat_net'],            'final'],
                    ];
                @endphp
                @foreach($rows as $r)
                    @php
                        [$label, $value, $variant] = $r;
                        $rowBg = match($variant) {
                            'cumul' => 'bg-violet-50',
                            'final' => 'bg-emerald-50',
                            'header'=> 'bg-gray-50',
                            default => '',
                        };
                        $rowFont = match($variant) {
                            'cumul','final' => 'font-bold',
                            'header'        => 'font-semibold text-gray-700',
                            default         => '',
                        };
                        $valueSign = $value === null ? '' : ($value >= 0 ? 'text-emerald-700' : 'text-red-600');
                    @endphp
                    <tr class="{{ $rowBg }} {{ $rowFont }}">
                        <td class="px-5 py-2.5 {{ $variant === 'detail' ? 'pl-10 text-gray-600 text-xs' : '' }}">
                            {{ $label }}
                        </td>
                        <td class="px-5 py-2.5 text-right tabular-nums {{ $valueSign }} {{ $variant === 'final' ? 'text-lg' : '' }}">
                            @if($value !== null) {{ $fmt($value) }} @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Lecture --}}
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Lecture rapide</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700">
            @if($sig['valeur_ajoutee'] > 0)
            <li>La valeur ajoutée représente <strong>{{ $sig['ratio_va_ca'] }}%</strong> du CA — c'est la richesse créée après consommations externes.</li>
            @endif
            @if($sig['ebe'] > 0)
            <li>L'EBE de <strong>{{ $fmt($sig['ebe']) }} FCFA</strong> mesure la performance économique pure (avant amortissements & financier).</li>
            @elseif($sig['ebe'] < 0)
            <li>⚠ L'EBE est négatif : l'exploitation ne dégage pas de marge — risque structurel.</li>
            @endif
            @if($sig['resultat_net'] > 0)
            <li>Le résultat net de <strong>{{ $fmt($sig['resultat_net']) }} FCFA</strong> sera reporté au compte 13.</li>
            @else
            <li>Le résultat net est négatif (perte) — sera reporté en débit du compte 13.</li>
            @endif
        </ul>
    </div>
    @endif

</div>
@endsection
