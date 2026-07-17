<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
    .page { padding: 15px 20px; }
    h2 { font-size: 12px; color: #1e40af; margin-bottom: 4px; }
    .sub { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #7c3aed; color: white; padding: 5px 6px; text-align: left; font-size: 8px; text-transform: uppercase; }
    td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #f9fafb; }
    .text-right { text-align: right; font-family: monospace; }
    tfoot td { font-weight: bold; border-top: 2px solid #7c3aed; background: #f5f3ff; }
</style>
</head>
<body>
<div class="page">
<h2>ÉTAT DE DÉCLARATION IUTS</h2>
<p class="sub">Impôt Unique sur les Traitements et Salaires — Période : {{ $run->period_label }}</p>

<table>
    <thead>
        <tr>
            <th>Matricule</th>
            <th>Nom & Prénom</th>
            <th class="text-right">Salaire brut</th>
            <th class="text-right">CNSS (emp.)</th>
            <th class="text-right">Base imposable</th>
            <th class="text-right">Charges</th>
            <th class="text-right">IUTS brut</th>
            <th class="text-right">Réduction</th>
            <th class="text-right">IUTS dû</th>
        </tr>
    </thead>
    <tbody>
    @foreach($run->items as $item)
    <tr>
        <td style="font-family:monospace;">{{ $item->employee_matricule }}</td>
        <td>{{ $item->employee_name }}</td>
        <td class="text-right">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
        <td class="text-right" style="color:#dc2626;">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
        <td class="text-right" style="font-weight:600;">{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
        @php $d = $item->iuts_detail; @endphp
        <td class="text-right">{{ $item->family_charges !== null ? $item->family_charges : number_format($item->nb_parts, 1) . ' pts' }}</td>
        <td class="text-right">{{ $d ? number_format($d['brut'], 0, ',', ' ') : '—' }}</td>
        <td class="text-right">{{ $d ? '− ' . number_format($d['reduction'], 0, ',', ' ') . ' (' . $d['reduction_rate'] . ' %)' : '—' }}</td>
        <td class="text-right" style="font-weight:700; color:#7c3aed;">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="8">TOTAL IUTS À REVERSER</td>
            <td class="text-right" style="color:#7c3aed; font-size:11px;">{{ number_format($run->total_iuts, 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
</table>
<p style="margin-top:10px; font-size:8px; color:#6b7280;">
    @php
        $cur = $payroll->currency_code;
        $bracketLabels = collect($payroll->iuts_brackets)->map(function($b, $i) use ($payroll, $cur) {
            $brackets = $payroll->iuts_brackets;
            $from = $i === 0 ? 0 : ($brackets[$i-1][0] + 1);
            $to   = $b[0] >= 9_999_999_999 ? '+' . number_format($brackets[$i-1][0] ?? 0, 0, ',', ' ') . ' ' . $cur : number_format($b[0], 0, ',', ' ') . ' ' . $cur;
            $label = $b[0] >= 9_999_999_999
                ? '+' . number_format($brackets[$i-1][0] ?? 0, 0, ',', ' ') . ' ' . $cur . ' : ' . $b[1] . '%'
                : number_format($from, 0, ',', ' ') . '–' . number_format($b[0], 0, ',', ' ') . ' ' . $cur . ' : ' . $b[1] . '%';
            return $label;
        })->implode(' | ');
    @endphp
    Barème mensuel par part : {{ $bracketLabels }}<br>
    {{ $settings?->company_name ?? '' }} — {{ now()->format('d/m/Y') }}
</p>
</div>
</body>
</html>
