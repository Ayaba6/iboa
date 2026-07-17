<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
    .page { padding: 12mm 14mm; }

    /* En-tete */
    .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; }
    .header-logo { display: table-cell; width: 120px; vertical-align: middle; }
    .header-logo img { max-height: 50px; max-width: 110px; }
    .header-logo .company-name { font-size: 11pt; font-weight: bold; color: #1e3a5f; }
    .header-info { display: table-cell; vertical-align: middle; text-align: right; font-size: 7.5pt; color: #6b7280; }
    .header-info .doc-title { font-size: 14pt; font-weight: bold; color: #1e3a5f; display: block; }
    .header-info .doc-date { font-size: 8pt; color: #9ca3af; }

    /* KPI banner */
    .kpi-banner { background: #1e3a5f; border-radius: 4px; padding: 8px 12px; margin-bottom: 12px; display: table; width: 100%; }
    .kpi-item { display: table-cell; text-align: center; padding: 0 8px; border-right: 1px solid #2d4f7f; }
    .kpi-item:last-child { border-right: none; }
    .kpi-lbl { font-size: 6.5pt; color: #93c5fd; display: block; text-transform: uppercase; letter-spacing: .4px; }
    .kpi-val { font-size: 11pt; font-weight: bold; color: #fff; display: block; font-family: monospace; }
    .kpi-item.green .kpi-val { color: #6ee7b7; }
    .kpi-item.red   .kpi-val { color: #fca5a5; }

    /* Parametres */
    .section-title { font-size: 9pt; font-weight: bold; color: #fff; background: #374151;
                     padding: 3px 8px; margin-bottom: 6px; border-radius: 2px; }
    .params-grid { display: table; width: 100%; margin-bottom: 10px; }
    .param-row { display: table-row; }
    .param-lbl { display: table-cell; width: 50%; padding: 2px 6px; font-size: 8pt; color: #6b7280; border-bottom: 1px solid #f3f4f6; }
    .param-val { display: table-cell; width: 50%; padding: 2px 6px; font-size: 8pt; font-weight: 600; color: #111; border-bottom: 1px solid #f3f4f6; }

    /* Tableau detail */
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1f2937; color: #fff; padding: 4px 8px; font-size: 7.5pt; text-transform: uppercase; }
    tbody td { padding: 3.5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 8.5pt; }
    tbody tr:nth-child(even) td { background: #f9fafb; }
    .td-label { color: #374151; }
    .td-signe { text-align: center; font-weight: bold; font-family: monospace; width: 24px; }
    .td-montant { text-align: right; font-family: monospace; }
    .row-bold td { font-weight: bold; background: #eff6ff !important; }
    .row-green td { background: #f0fdf4 !important; }
    .row-dark td { background: #111827 !important; color: #fff !important; font-weight: bold; }
    .signe-plus   { color: #15803d; }
    .signe-minus  { color: #dc2626; }
    .signe-equals { color: #6b7280; }
    .c-blue   { color: #1d4ed8; }
    .c-green  { color: #15803d; }
    .c-red    { color: #dc2626; }
    .c-purple { color: #7c3aed; }
    .c-amber  { color: #b45309; }
    .c-orange { color: #c2410c; }
    .c-rose   { color: #be123c; }
    .c-emerald{ color: #047857; }
    .c-teal   { color: #0f766e; font-weight: bold; }
    .c-violet { color: #6d28d9; }
    .c-slate  { color: #64748b; }
    .section-header td { background: #374151 !important; color: #d1d5db !important;
                         font-size: 7pt; text-transform: uppercase; letter-spacing: .5px; padding: 3px 8px; }

    /* Taux effectifs */
    .taux-section { margin-top: 10px; }
    .taux-row { display: table-row; }
    .taux-lbl { display: table-cell; width: 45%; font-size: 8pt; color: #6b7280; padding: 2px 0; }
    .taux-bar-cell { display: table-cell; width: 45%; vertical-align: middle; padding: 2px 4px; }
    .taux-bar-bg  { background: #e5e7eb; height: 6px; border-radius: 3px; width: 100%; }
    .taux-bar-fill{ height: 6px; border-radius: 3px; }
    .taux-pct { display: table-cell; width: 10%; font-size: 8pt; font-family: monospace; font-weight: bold; color: #374151; text-align: right; padding: 2px 0; }

    /* Note legale */
    .legal { margin-top: 14px; font-size: 7pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
</style>
</head>
<body>
<div class="page">

{{-- En-tete --}}
<div class="header">
    <div class="header-logo">
        @if($settings?->logo_path && file_exists(storage_path('app/public/' . $settings->logo_path)))
            <img src="{{ storage_path('app/public/' . $settings->logo_path) }}" alt="Logo">
        @else
            <span class="company-name">{{ $company->name }}</span>
        @endif
    </div>
    <div class="header-info">
        <span class="doc-title">SIMULATION DE SALAIRE</span>
        <span class="doc-date">Calcul inverse — Burkina Faso</span><br>
        <span class="doc-date">Edite le {{ now()->format('d/m/Y a H:i') }}</span>
    </div>
</div>

{{-- KPI Banner --}}
<div class="kpi-banner">
    <div class="kpi-item">
        <span class="kpi-lbl">Net cible</span>
        <span class="kpi-val">{{ number_format($result['net_souhaite'], 0, ',', ' ') }} F</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-lbl">Salaire brut</span>
        <span class="kpi-val">{{ number_format($result['salaire_brut'], 0, ',', ' ') }} F</span>
    </div>
    <div class="kpi-item red">
        <span class="kpi-lbl">CNSS salarie</span>
        <span class="kpi-val">{{ number_format($result['cnss_employee'], 0, ',', ' ') }} F</span>
    </div>
    <div class="kpi-item" style="border-right:1px solid #2d4f7f;">
        <span class="kpi-lbl" style="color:#5eead4;">Net imposable</span>
        <span class="kpi-val" style="color:#99f6e4;">{{ number_format($result['salaire_net_imposable'], 0, ',', ' ') }} F</span>
    </div>
    <div class="kpi-item red">
        <span class="kpi-lbl">IUTS</span>
        <span class="kpi-val">{{ number_format($result['iuts'], 0, ',', ' ') }} F</span>
    </div>
    <div class="kpi-item green">
        <span class="kpi-lbl">Net calcule</span>
        <span class="kpi-val">{{ number_format($result['net_calcule'], 0, ',', ' ') }} F</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-lbl">Cout employeur</span>
        <span class="kpi-val">{{ number_format($result['cout_employeur'], 0, ',', ' ') }} F</span>
    </div>
</div>

{{-- Parametres de simulation --}}
<div class="section-title">Parametres de la simulation</div>
<div class="params-grid" style="display:table;width:100%;margin-bottom:12px;">
    @foreach($params as $lbl => $val)
    <div class="param-row">
        <div class="param-lbl">{{ $lbl }}</div>
        <div class="param-val">{{ $val }}</div>
    </div>
    @endforeach
    <div class="param-row">
        <div class="param-lbl">Parts fiscales</div>
        <div class="param-val">{{ $result['family_charges'] ?? 0 }} charge(s)</div>
    </div>
</div>

{{-- Detail du calcul --}}
<div class="section-title">Detail du calcul</div>
<table>
    <thead>
        <tr>
            <th style="width:6%;text-align:center;"></th>
            <th style="width:62%;text-align:left;">Rubrique</th>
            <th style="width:32%;text-align:right;">Montant (FCFA)</th>
        </tr>
    </thead>
    <tbody>
    @foreach($result['detail'] as $row)
        @if(isset($row['section']) && $row['section'] === 'employeur')
        @php static $sectionShown = false; @endphp
        @if(!$sectionShown)
        <tr class="section-header">
            <td colspan="3">Charge patronale</td>
        </tr>
        @php $sectionShown = true; @endphp
        @endif
        @endif
        <tr class="{{ $row['bold'] ?? false ? ($row['color']==='green' ? 'row-green' : 'row-bold') : '' }}
                   {{ ($row['bold'] ?? false) && $row['color']==='rose' ? 'row-dark' : '' }}">
            <td class="td-signe
                @if($row['signe']==='+') signe-plus
                @elseif($row['signe']==='-') signe-minus
                @else signe-equals @endif">
                {{ $row['signe'] }}
            </td>
            <td class="td-label">{{ $row['label'] }}</td>
            <td class="td-montant
                @if(($row['bold']??false) && $row['color']==='rose') style='color:#fff'
                @elseif($row['color']==='green')   c-green
                @elseif($row['color']==='blue')    c-blue
                @elseif($row['color']==='red')     c-red
                @elseif($row['color']==='purple')  c-purple
                @elseif($row['color']==='amber')   c-amber
                @elseif($row['color']==='orange')  c-orange
                @elseif($row['color']==='emerald') c-emerald
                @elseif($row['color']==='teal')    c-teal
                @elseif($row['color']==='violet')  c-violet
                @elseif($row['color']==='slate')   c-slate
                @endif">
                @if($row['montant'] > 0 || ($row['bold'] ?? false))
                    {{ number_format($row['montant'], 0, ',', ' ') }} F
                @else
                    &mdash;
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- Taux effectifs --}}
<div class="taux-section">
    <div class="section-title" style="margin-top:10px;">Taux effectifs</div>
    @php
    $b = $result['salaire_brut'];
    $t = $result['cout_employeur'];
    $taux = [
        ['CNSS salarie / brut',    $b > 0 ? round($result['cnss_employee']/$b*100,1) : 0,                             '#ef4444'],
        ['Net imposable / brut',   $b > 0 ? round($result['salaire_net_imposable']/$b*100,1) : 0,                     '#0f766e'],
        ['IUTS / brut',            $b > 0 ? round($result['iuts']/$b*100,1) : 0,                                      '#7c3aed'],
        ['Pression fiscale totale',$b > 0 ? round(($result['cnss_employee']+$result['iuts'])/$b*100,1) : 0,           '#2563eb'],
        ['Net a payer / cout emp.',$t > 0 ? round($result['net_calcule']/$t*100,1) : 0,                               '#059669'],
    ];
    @endphp
    <table style="margin-top:4px;">
        <thead>
            <tr>
                <th style="width:40%;text-align:left;">Indicateur</th>
                <th style="width:40%;text-align:left;">Barre</th>
                <th style="width:20%;text-align:right;">Taux</th>
            </tr>
        </thead>
        <tbody>
        @foreach($taux as [$lbl, $pct, $clr])
        <tr>
            <td style="font-size:8pt;color:#374151;padding:3px 6px;">{{ $lbl }}</td>
            <td style="padding:3px 6px;vertical-align:middle;">
                <div style="background:#e5e7eb;height:7px;border-radius:3px;width:100%;">
                    <div style="background:{{ $clr }};height:7px;border-radius:3px;width:{{ min($pct,100) }}%;"></div>
                </div>
            </td>
            <td style="text-align:right;font-family:monospace;font-weight:bold;font-size:8.5pt;color:#111;padding:3px 6px;">{{ $pct }} %</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- Taux utilises --}}
<div style="margin-top:10px;font-size:7.5pt;color:#6b7280;">
    <strong>Parametres appliques :</strong>
    CNSS salarie {{ $result['cnss_employee_rate'] }}% &nbsp;|&nbsp;
    CNSS patronal {{ $result['cnss_employer_rate'] }}% &nbsp;|&nbsp;
    Plafond CNSS {{ number_format($payroll->cnss_ceiling, 0, ',', ' ') }} F &nbsp;|&nbsp;
    Abattement IUTS {{ $result['abattement_rate'] }}%
    @if(!$result['exact'])
    &nbsp;|&nbsp; <strong style="color:#b45309;">Ecart : {{ number_format($result['ecart'], 0, ',', ' ') }} F</strong> (net exact impossible)
    @endif
</div>

<div class="legal">
    Document genere automatiquement par A3 ERP le {{ now()->format('d/m/Y a H:i') }}.
    Simulation a titre indicatif — ne constitue pas un bulletin de paie officiel.
    Les montants sont en FCFA, arrondis a l'entier le plus proche.
    Calculs conformes aux dispositions du Code General des Impots du Burkina Faso et du regime CNSS.
</div>

</div>
</body>
</html>
