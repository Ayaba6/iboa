<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* ── Reset ─────────────────────────────────────────────────────────────────── */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #111; background:#fff; }
.page { padding: 7mm 10mm 6mm 10mm; }

/* ── En-tête ────────────────────────────────────────────────────────────────── */
.header-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
.title-bulletin { font-size:20pt; font-weight:bold; letter-spacing:6px; color:#000; text-align:right; }
.title-periode  { font-size:10pt; text-align:right; margin-top:4px; }
.header-sep     { border-top:1px solid #333; margin-top:3px; padding-top:3px; }
.company-name   { font-weight:bold; font-size:8pt; margin-top:2px; }
.company-line   { font-size:7pt; margin-top:1px; }

/* ── Bloc employé ───────────────────────────────────────────────────────────── */
.emp-block { width:100%; border-collapse:collapse; border:1px solid #555; margin-bottom:5px; }
.emp-block td { vertical-align:top; }
.emp-left  { width:52%; padding:5px 8px; border-right:1px solid #555; }
.emp-right { width:48%; padding:6px 10px; }
.inf-tbl   { border-collapse:collapse; width:100%; font-size:7pt; line-height:1.6; }
.inf-tbl td { padding:0.5px 0; vertical-align:top; }
.inf-lbl   { font-weight:bold; padding-right:6px; white-space:nowrap; }
.emp-name  { font-size:10.5pt; font-weight:bold; margin-top:6px; }
.mat-row   { font-size:7pt; font-weight:bold; }

/* ── Tableau principal ─────────────────────────────────────────────────────── */
.mt { width:100%; border-collapse:collapse; margin-bottom:4px; font-size:7pt; }
.mt th, .mt td { border:1px solid #888; padding:2px 3px; vertical-align:middle; }
.th-main  { background:#c8c8c8; text-align:center; font-weight:bold; font-size:7pt; }
.th-sal   { background:#d8d8d8; text-align:center; font-weight:bold; font-size:6.5pt; }
.th-pat   { background:#bec8d4; text-align:center; font-weight:bold; font-size:6.5pt; }
.td-pat   { background:#e6ecf2; }
.r        { text-align:right; }
.c        { text-align:center; }
.num      { font-family: DejaVu Sans Mono, monospace; }
.code-c   { font-size:6.5pt; color:#333; text-align:center; }
.row-total-brut td { background:#e0e0e0; font-weight:bold; }
.row-total-cot  td { background:#d0d0d0; font-weight:bold; }
.row-empty td { height:8px; font-size:4pt; }

/* ── Cumuls ─────────────────────────────────────────────────────────────────── */
.cumuls { width:100%; border-collapse:collapse; font-size:6.5pt; }
.cumuls th { background:#333; color:#fff; padding:2px 3px; text-align:center;
             border:1px solid #333; font-size:6pt; line-height:1.3; font-weight:bold; }
.cumuls th:first-child { text-align:left; }
.cumuls td { padding:2px 4px; border:1px solid #aaa; text-align:right; font-size:6.5pt; }
.cumuls td:first-child { text-align:left; font-weight:bold; background:#f0f0f0; }

/* ── NET À PAYER ─────────────────────────────────────────────────────────────── */
.net-box { border:2px solid #000; padding:5px 8px; text-align:center; white-space:nowrap; vertical-align:middle; }
.net-lbl { font-size:7pt; font-weight:bold; text-transform:uppercase; letter-spacing:1px; margin-bottom:3px; }
.net-amt { font-size:13pt; font-weight:bold; font-family: DejaVu Sans Mono, monospace; }

/* ── Bas de page ─────────────────────────────────────────────────────────────── */
.footer-tbl { width:100%; border-collapse:collapse; margin-top:4px; font-size:7pt; }
.conges-tbl { border-collapse:collapse; font-size:7pt; }
.conges-tbl td { padding:2px 6px 2px 0; }
.conges-line { border-bottom:1px solid #666; min-width:70px; display:inline-block; }
</style>
</head>
<body>
<div class="page">

@php
    $emp        = $item->employee;
    $company    = $run->company;
    $logoBase64 = $company?->logo_base64;

    /* ── Période ───────────────────────────────────────────────────────────── */
    $periodDate = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
    $monthName  = ucfirst($periodDate->translatedFormat('F'));
    $yearLabel  = $run->period_year;

    /* ── Ancienneté ────────────────────────────────────────────────────────── */
    $hiringDate     = $emp?->hiring_date;
    $yearsOfService = $hiringDate ? (int) $hiringDate->diffInYears($periodDate) : 0;
    $monthsRemain   = $hiringDate ? ((int) $hiringDate->diffInMonths($periodDate)) % 12 : 0;
    $ancRate        = (int) ($item->anc_rate   ?? 0);
    $ancAmount      = (int) ($item->anc_amount ?? 0);
    if ($ancRate === 0 && $yearsOfService > 0) {
        $ancRate   = min($yearsOfService * ($payroll->anc_rate_per_year ?? 2), $payroll->anc_rate_max_pct ?? 25);
        $ancAmount = (int) round($item->base_salary * $ancRate / 100);
    }

    /* ── Civilité ──────────────────────────────────────────────────────────── */
    $civility = ($emp?->gender === 'F') ? 'Mme' : 'M.';

    /* ── CNSS split BF ─────────────────────────────────────────────────────── */
    $cnssBase  = (int) $item->cnss_base;
    $rpRatePat = (float) ($payroll->cnss_at_rate ?? 1.5);
    $pfRatePat = 6.0;
    $avRateEmp = (float) ($payroll->cnss_employee_rate ?? 5.5);
    $avRatePat = (float) ($payroll->cnss_employer_rate ?? 16) - $pfRatePat - $rpRatePat;
    $avAmtEmp  = (int) $item->cnss_employee;
    $avAmtPat  = (int) round($cnssBase * $avRatePat / 100);
    $pfAmtPat  = (int) round($cnssBase * $pfRatePat / 100);
    $rpAmtPat  = max(0, (int) $item->cnss_employer - $avAmtPat - $pfAmtPat);

    /* ── TPA (3 % brut total, patronal uniquement) ─────────────────────────── */
    $tpaRatePat  = 3.0;
    $tpaBrutBase = (int) $item->salaire_brut + (int) ($item->total_allowances_non_taxable ?? 0);
    $tpaAmtPat   = (int) round($tpaBrutBase * $tpaRatePat / 100);

    /* ── Effort de paix ────────────────────────────────────────────────────── */
    $effortPaix     = (int) ($item->effort_paix_amount ?? 0);
    $effortPaixRate = (float) ($payroll->effort_paix_rate ?? 1);
    $effortPaixBase = max(0, $tpaBrutBase - (int) $item->cnss_employee - (int) $item->iuts_amount);

    /* ── Totaux ────────────────────────────────────────────────────────────── */
    $totalBrut    = $tpaBrutBase;
    $totalCotSal  = (int) $item->cnss_employee + (int) $item->iuts_amount;
    $totalCotPat  = (int) $item->cnss_employer + $tpaAmtPat;

    /* ── Heures ────────────────────────────────────────────────────────────── */
    $hsTotal     = round(($item->hs_25_hours ?? 0) + ($item->hs_50_hours ?? 0) + ($item->hs_nuit_hours ?? 0), 2);
    $workedHours = (int) round(($item->worked_days ?? 0) * ($payroll->work_hours_day ?? 8));

    /* ── Primes individuelles (hors ANCIENNETE) ────────────────────────────── */
    try {
        $activeAllowances = $emp
            ? $emp->allowances()->where('is_active', true)->with('type')
                ->whereHas('type', fn($q) => $q->where('code', '!=', 'ANCIENNETE'))
                ->get()
            : collect();
    } catch (\Exception $e) {
        $activeAllowances = collect();
    }

    /* ── Map codes rubriques ───────────────────────────────────────────────── */
    $codeMap = [
        'ANCIENNETE'    => '1010', 'LOGEMENT'   => '1200', 'TRANSPORT'  => '1210',
        'TRANSP'        => '1210', 'PANIER'     => '1215', 'FONCTION'   => '1220',
        'RESPONSABILITE'=> '1225', 'RENDEMENT'  => '1230', 'TECHNICITE' => '1235',
        'SPECIFIQUE'    => '1250', 'INSALUBRITE'=> '1240', 'FORMATION'  => '1245',
        'FIN_ANNEE'     => '1300', 'GARDE'      => '1260', 'REPRESENTATION'=> '1270',
    ];
    $dynCode = 1280;

    /* ── Infos entreprise pour le bulletin ─────────────────────────────────── */
    $companyPhone   = $payroll->phone   ?? $company?->phone   ?? '';
    $companyAddr    = $payroll->address_bulletin ?? ($company ? trim(($company->address ?? '').' '.($company->city ?? '')) : '');
    $cnssAffil      = $payroll->cnss_affiliation ?? '';
@endphp

{{-- ════════════════════════════════════════════════════════════════════════
     EN-TÊTE : Logo + Infos société | BULLETIN DE PAIE + Période
     ════════════════════════════════════════════════════════════════════════ --}}
<table class="header-table">
    <tr>
        {{-- ── Gauche : Logo + infos société ─────────────────────────────────── --}}
        <td style="width:38%; vertical-align:top; padding-right:10px;">
            @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Logo"
                 style="max-height:70px; max-width:110px; object-fit:contain; display:block; margin-bottom:4px;">
            @endif
            <div class="header-sep">
                @if($companyPhone)
                <div class="company-line"><strong>Tél. : {{ $companyPhone }}</strong></div>
                @endif
                <div class="company-name">{{ mb_strtoupper($company?->name ?? '') }}</div>
                @if($companyAddr)
                <div class="company-line">{{ $companyAddr }}</div>
                @endif
                @if($cnssAffil)
                <div class="company-line"><strong>N° d'affiliation CNSS : {{ $cnssAffil }}</strong></div>
                @endif
            </div>
        </td>
        {{-- ── Droite : BULLETIN DE PAIE + Période ────────────────────────────── --}}
        <td style="width:62%; vertical-align:top; padding-top:8px;">
            <div class="title-bulletin">BULLETIN&nbsp;&nbsp;DE&nbsp;&nbsp;PAIE</div>
            <div class="title-periode">
                Période :&nbsp;&nbsp;&nbsp;
                <strong>{{ $monthName }}</strong>&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>{{ $yearLabel }}</strong>
            </div>
        </td>
    </tr>
</table>

{{-- ════════════════════════════════════════════════════════════════════════
     BLOC EMPLOYÉ
     ════════════════════════════════════════════════════════════════════════ --}}
<table class="emp-block">
    <tr>
        {{-- Gauche : informations salariées ─────────────────────────────────── --}}
        <td class="emp-left">
            <table class="inf-tbl">
                <tr>
                    <td class="inf-lbl">N° CNSS :</td>
                    <td>{{ $emp?->cnss_number ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="inf-lbl">Ancienneté</td>
                    <td>{{ $yearsOfService }} an(s) et {{ $monthsRemain }} mois</td>
                </tr>
                <tr>
                    <td class="inf-lbl">Nb Charge :</td>
                    <td>{{ $emp?->nb_children ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="inf-lbl">Emploi :</td>
                    <td>{{ mb_strtoupper($item->job_title ?? ($emp?->job_title ?? '')) }}</td>
                </tr>
                <tr>
                    <td class="inf-lbl">Service :</td>
                    <td>{{ mb_strtoupper($item->department_name ?? ($emp?->department?->name ?? '')) }}</td>
                </tr>
            </table>
        </td>
        {{-- Droite : Matricule + Nom ─────────────────────────────────────────── --}}
        <td class="emp-right">
            <table class="inf-tbl" style="margin-bottom:8px;">
                <tr>
                    <td class="inf-lbl">Matricule :</td>
                    <td style="font-weight:bold;">{{ $item->employee_matricule }}</td>
                </tr>
            </table>
            <div class="emp-name">{{ $civility }}&nbsp;&nbsp;{{ mb_strtoupper($item->employee_name) }}</div>
        </td>
    </tr>
</table>

{{-- ════════════════════════════════════════════════════════════════════════
     TABLEAU PRINCIPAL — 9 colonnes (N°, Désignation, Nombre, Base,
     Part sal [Taux, Gain, Retenue], Part pat [Taux, Retenue])
     ════════════════════════════════════════════════════════════════════════ --}}
<table class="mt">
    <thead>
        <tr>
            <th class="th-main c" rowspan="2" style="width:5%">N°</th>
            <th class="th-main" rowspan="2" style="width:27%; text-align:left; padding-left:4px;">Désignation</th>
            <th class="th-main r" rowspan="2" style="width:7%">Nombre</th>
            <th class="th-main r" rowspan="2" style="width:10%">Base</th>
            <th class="th-sal" colspan="3">Part salariale</th>
            <th class="th-pat" colspan="2">Part patronale</th>
        </tr>
        <tr>
            <th class="th-sal r" style="width:6%">Taux</th>
            <th class="th-sal r" style="width:12%">Gain</th>
            <th class="th-sal r" style="width:12%">Retenue</th>
            <th class="th-pat r" style="width:6%">Taux</th>
            <th class="th-pat r" style="width:14%">Retenue</th>
        </tr>
    </thead>
    <tbody>

    {{-- ── 1000 Salaire de base ─────────────────────────────────────────────── --}}
    <tr>
        <td class="code-c">1000</td>
        <td>Salaire de base</td>
        <td class="r num">{{ number_format($item->worked_days, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td></td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>

    {{-- ── 1010 Prime d'ancienneté (Art. 109 — 2 %/an, plafond 25 %) ─────────── --}}
    @if($ancAmount > 0)
    <tr>
        <td class="code-c">1010</td>
        <td>Prime d'ancienneté</td>
        <td class="r num"></td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format($ancRate, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($ancAmount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Primes & indemnités individuelles ───────────────────────────────── --}}
    @foreach($activeAllowances as $allowance)
    @php
        $tc      = $allowance->type?->code ?? '';
        $tName   = $allowance->type?->name ?? 'Prime';
        $rubCode = $codeMap[$tc] ?? sprintf('%04d', $dynCode++);
        $lineAmt = (int) $allowance->amount;
        $lineNb  = number_format($item->worked_days, 2, ',', '');
    @endphp
    @if($lineAmt > 0)
    <tr>
        <td class="code-c">{{ $rubCode }}</td>
        <td>{{ $tName }}</td>
        <td class="r num">{{ $lineNb }}</td>
        <td class="r num">{{ number_format($lineAmt, 0, ',', ' ') }}</td>
        <td></td>
        <td class="r num">{{ number_format($lineAmt, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>
    @endif
    @endforeach

    {{-- ── Heures supplémentaires ──────────────────────────────────────────── --}}
    @if(($item->hs_25_hours ?? 0) > 0)
    <tr>
        <td class="code-c">1100</td>
        <td>HS {{ $payroll->hs_rate_25 }} %</td>
        <td class="r num">{{ number_format($item->hs_25_hours, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format(1 + $payroll->hs_rate_25/100, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->hs_25_amount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif
    @if(($item->hs_50_hours ?? 0) > 0)
    <tr>
        <td class="code-c">1101</td>
        <td>HS {{ $payroll->hs_rate_50 }} %</td>
        <td class="r num">{{ number_format($item->hs_50_hours, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format(1 + $payroll->hs_rate_50/100, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->hs_50_amount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif
    @if(($item->hs_nuit_hours ?? 0) > 0)
    <tr>
        <td class="code-c">1102</td>
        <td>HS Nuit {{ $payroll->hs_rate_nuit }} %</td>
        <td class="r num">{{ number_format($item->hs_nuit_hours, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format(1 + $payroll->hs_rate_nuit/100, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->hs_nuit_amount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Absences ─────────────────────────────────────────────────────────── --}}
    @if(($item->absence_days ?? 0) > 0)
    <tr>
        <td class="code-c">1400</td>
        <td>Retenue absences</td>
        <td class="r num">{{ number_format($item->absence_days, 2, ',', '') }}</td>
        <td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->absence_amount, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ══ TOTAL BRUT ══════════════════════════════════════════════════════════ --}}
    <tr class="row-total-brut">
        <td colspan="5" class="c" style="letter-spacing:1px; padding:3px;">Total Brut</td>
        <td class="r num">{{ number_format($totalBrut, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat" style="background:#bec8d4;"></td>
        <td class="td-pat" style="background:#bec8d4;"></td>
    </tr>

    {{-- ── 2000 TPA ──────────────────────────────────────────────────────────── --}}
    <tr>
        <td class="code-c">2000</td>
        <td>TPA</td>
        <td></td>
        <td class="r num">{{ number_format($tpaBrutBase, 0, ',', ' ') }}</td>
        <td class="r num">0,00</td>
        <td class="r num">0</td>
        <td></td>
        <td class="r num td-pat">{{ number_format($tpaRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($tpaAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2010 CNSS Assurance Vieillesse ──────────────────────────────────── --}}
    <tr>
        <td class="code-c">2010</td>
        <td>CNSS, Assurance Vieillesse</td>
        <td></td>
        <td class="r num">{{ number_format($cnssBase, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format($avRateEmp, 2, ',', '') }}</td>
        <td></td>
        <td class="r num">{{ number_format($avAmtEmp, 0, ',', ' ') }}</td>
        <td class="r num td-pat">{{ number_format($avRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($avAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2011 CNSS Prestations Familiales ────────────────────────────────── --}}
    <tr>
        <td class="code-c">2011</td>
        <td>CNSS, Prestations Familiales</td>
        <td></td>
        <td class="r num">{{ number_format($cnssBase, 0, ',', ' ') }}</td>
        <td class="r num">0,00</td>
        <td></td>
        <td class="r num" style="color:#888;">0</td>
        <td class="r num td-pat">{{ number_format($pfRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($pfAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2012 CNSS Risques Professionnels ────────────────────────────────── --}}
    <tr>
        <td class="code-c">2012</td>
        <td>CNSS, Risques Professionnels</td>
        <td></td>
        <td class="r num">{{ number_format($cnssBase, 0, ',', ' ') }}</td>
        <td class="r num">0,00</td>
        <td></td>
        <td class="r num" style="color:#888;">0</td>
        <td class="r num td-pat">{{ number_format($rpRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($rpAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2110 Retenue IUTS ───────────────────────────────────────────────── --}}
    <tr>
        <td class="code-c">2110</td>
        <td>Retenue IUTS</td>
        <td></td>
        <td class="r num">{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
        <td></td>
        <td></td>
        <td class="r num">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
        <td class="td-pat"></td>
        <td class="r num td-pat" style="color:#888;">0</td>
    </tr>

    {{-- ══ TOTAL COTISATIONS ═══════════════════════════════════════════════════ --}}
    <tr class="row-total-cot">
        <td colspan="5" class="c" style="letter-spacing:1px; padding:3px;">Total Cotisations</td>
        <td></td>
        <td class="r num">{{ number_format($totalCotSal, 0, ',', ' ') }}</td>
        <td class="td-pat" style="background:#a8b8c8;"></td>
        <td class="r num td-pat" style="background:#a8b8c8;">{{ number_format($totalCotPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 9000 Effort de paix ─────────────────────────────────────────────── --}}
    @if($effortPaix > 0)
    <tr>
        <td class="code-c">9000</td>
        <td>Retenue Effort de paix de {{ (int)$effortPaixRate }}%</td>
        <td></td>
        <td class="r num">{{ number_format($effortPaixBase, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format($effortPaixRate, 2, ',', '') }}</td>
        <td></td>
        <td class="r num">{{ number_format($effortPaix, 0, ',', ' ') }}</td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Avance sur salaire ──────────────────────────────────────────────── --}}
    @if(($item->avances_deductions ?? 0) > 0)
    <tr>
        <td class="code-c">3100</td>
        <td>Récupération avance sur salaire</td>
        <td></td><td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->avances_deductions, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Remboursement prêt ──────────────────────────────────────────────── --}}
    @if(($item->loan_deductions ?? 0) > 0)
    <tr>
        <td class="code-c">3200</td>
        <td>Remboursement prêt salarié</td>
        <td></td><td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->loan_deductions, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Autres retenues ────────────────────────────────────────────────── --}}
    @if(($item->autres_retenues ?? 0) > 0)
    <tr>
        <td class="code-c">3300</td>
        <td>Autres retenues</td>
        <td></td><td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->autres_retenues, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Lignes vides (rembourrage) ──────────────────────────────────────── --}}
    @php
    $varLines = ($ancAmount>0?1:0) + $activeAllowances->where('amount','>',0)->count()
              + (($item->hs_25_hours??0)>0?1:0) + (($item->hs_50_hours??0)>0?1:0)
              + (($item->hs_nuit_hours??0)>0?1:0) + (($item->absence_days??0)>0?1:0)
              + (($item->avances_deductions??0)>0?1:0) + (($item->loan_deductions??0)>0?1:0)
              + (($item->autres_retenues??0)>0?1:0) + ($effortPaix>0?1:0);
    $fillers = max(0, 5 - $varLines);
    @endphp
    @for($fi = 0; $fi < $fillers; $fi++)
    <tr class="row-empty">
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endfor

    </tbody>
</table>

{{-- ════════════════════════════════════════════════════════════════════════
     CUMULS + NET À PAYER
     ════════════════════════════════════════════════════════════════════════ --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:5px;">
    <tr>
        <td style="vertical-align:top; padding-right:5px;">
            <table class="cumuls">
                <thead>
                    <tr>
                        <th style="width:7%; text-align:left;">Cumuls</th>
                        <th style="width:13%">Salaire brut</th>
                        <th style="width:11%">Charges<br>salariales</th>
                        <th style="width:11%">Charges<br>patronales</th>
                        <th style="width:9%">Avantages<br>en nature</th>
                        <th style="width:13%">Net imposable</th>
                        <th style="width:9%">Heures<br>travaillées</th>
                        <th style="width:11%">Heures<br>supplémentaires</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Période</td>
                        <td>{{ number_format($totalBrut, 0, ',', ' ') }}</td>
                        <td>{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
                        <td>{{ number_format($totalCotPat, 0, ',', ' ') }}</td>
                        <td>0</td>
                        <td>{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
                        <td>{{ $workedHours }}</td>
                        <td>{{ $hsTotal > 0 ? number_format($hsTotal, 0, ',', ' ') : 0 }}</td>
                    </tr>
                    <tr>
                        <td>Année</td>
                        <td>{{ number_format($item->cumul_brut_ytd  ?? $totalBrut,            0, ',', ' ') }}</td>
                        <td>{{ number_format($item->cumul_cnss_ytd  ?? $item->cnss_employee,  0, ',', ' ') }}</td>
                        <td>{{ number_format($totalCotPat, 0, ',', ' ') }}</td>
                        <td>0</td>
                        <td>{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
                        <td>{{ $workedHours }}</td>
                        <td>0</td>
                    </tr>
                </tbody>
            </table>
        </td>
        {{-- NET À PAYER ──────────────────────────────────────────────────────── --}}
        <td style="width:115px; vertical-align:middle;">
            <div class="net-box">
                <div class="net-lbl">NET A PAYER</div>
                <div class="net-amt">{{ number_format($item->salaire_net, 0, ',', ' ') }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ════════════════════════════════════════════════════════════════════════
     DATES DE CONGÉS + CACHET DE L'EMPLOYEUR
     ════════════════════════════════════════════════════════════════════════ --}}
<table class="footer-tbl">
    <tr>
        <td style="width:55%; vertical-align:top; padding-right:12px;">
            <div style="font-weight:bold; margin-bottom:5px;">Dates de congés</div>
            <table class="conges-tbl">
                @for($ri = 0; $ri < 3; $ri++)
                <tr>
                    <td style="font-weight:bold; padding-right:4px;">Du</td>
                    <td style="border-bottom:1px solid #555; min-width:75px; padding:1px 4px;">&nbsp;</td>
                    <td style="padding:1px 8px; font-weight:bold;">Au</td>
                    <td style="border-bottom:1px solid #555; min-width:75px; padding:1px 4px;">&nbsp;</td>
                </tr>
                @endfor
            </table>
        </td>
        <td style="width:45%; vertical-align:top; padding-top:2px;">
            <div style="font-size:8pt; font-weight:bold;">Cachet de l'employeur</div>
            @if($emp && ($emp->bank_name || $emp->bank_account_number))
            <div style="font-size:6.5pt; color:#555; margin-top:4px;">
                Règlement : <strong>{{ mb_strtoupper($emp->payment_mode ?? 'VIREMENT') }}</strong>
                @if($emp->bank_name) &mdash; {{ $emp->bank_name }}@endif
                @if($emp->bank_account_number) — n° {{ $emp->bank_account_number }}@endif
            </div>
            @endif
        </td>
    </tr>
</table>

</div>
</body>
</html>
