<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
    .page { padding: 12px 15px; }
    h2 { font-size: 11px; color: #1e40af; }
    .sub { font-size: 8px; color: #6b7280; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1f2937; color: white; padding: 4px 5px; font-size: 7px; text-transform: uppercase; }
    td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #f9fafb; }
    .text-right { text-align: right; font-family: monospace; }
    tfoot td { font-weight: bold; border-top: 2px solid #1f2937; background: #f8fafc; }
    .kpi { display: inline-block; padding: 6px 12px; background: #1e40af; color:white; border-radius: 6px; margin: 0 4px 8px; text-align: center; }
    .kpi-label { font-size: 7px; }
    .kpi-val { font-size: 10px; font-weight: bold; font-family: monospace; }
</style>
</head>
<body>
<div class="page">
<h2>RÉCAPITULATIF MENSUEL DE PAIE — {{ strtoupper($run->period_label) }}</h2>
<p class="sub">Généré le {{ now()->format('d/m/Y à H:i') }} — Statut : {{ $run->status_label }}</p>

<div style="margin-bottom:10px;">
    @foreach([
        ['Effectif', $run->employee_count.' emp.'],
        ['Total brut', number_format($run->total_brut,0,',',' ').' F'],
        ['CNSS salarial', number_format($run->total_cnss_employee,0,',',' ').' F'],
        ['CNSS patronal', number_format($run->total_cnss_employer,0,',',' ').' F'],
        ['IUTS', number_format($run->total_iuts,0,',',' ').' F'],
        ['Total net', number_format($run->total_net,0,',',' ').' F'],
    ] as [$l,$v])
    <div class="kpi"><div class="kpi-label">{{ $l }}</div><div class="kpi-val">{{ $v }}</div></div>
    @endforeach
</div>

<table>
    <thead>
        <tr>
            <th>Mat.</th><th>Employé</th><th>Dépt.</th>
            <th class="text-right">Base</th>
            <th class="text-right">Primes</th>
            <th class="text-right">Brut</th>
            <th class="text-right">CNSS emp.</th>
            <th class="text-right">CNSS pat.</th>
            <th class="text-right">Charges</th>
            <th class="text-right">IUTS</th>
            <th class="text-right">NET</th>
            <th class="text-right">Coût total</th>
        </tr>
    </thead>
    <tbody>
    @foreach($run->items as $item)
    <tr>
        <td style="font-family:monospace; font-size:7px;">{{ $item->employee_matricule }}</td>
        <td>{{ $item->employee_name }}</td>
        <td style="font-size:7px; color:#6b7280;">{{ $item->department_name }}</td>
        <td class="text-right">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="text-right">{{ number_format($item->total_allowances_taxable + $item->total_allowances_non_taxable + ($item->primes_exceptionnelles ?? 0) + ($item->autres_gains ?? 0), 0, ',', ' ') }}</td>
        <td class="text-right" style="font-weight:600;">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
        <td class="text-right" style="color:#dc2626;">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
        <td class="text-right" style="color:#d97706;">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
        <td class="text-right">{{ $item->family_charges !== null ? $item->family_charges : number_format($item->nb_parts, 1) }}</td>
        <td class="text-right" style="color:#7c3aed;">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
        <td class="text-right" style="font-weight:700; color:#059669;">{{ number_format($item->salaire_net, 0, ',', ' ') }}</td>
        <td class="text-right" style="font-weight:600;">{{ number_format($item->cout_employeur, 0, ',', ' ') }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTAUX</td>
            <td class="text-right">{{ number_format($run->total_brut, 0, ',', ' ') }}</td>
            <td class="text-right" style="color:#dc2626;">{{ number_format($run->total_cnss_employee, 0, ',', ' ') }}</td>
            <td class="text-right" style="color:#d97706;">{{ number_format($run->total_cnss_employer, 0, ',', ' ') }}</td>
            <td></td>
            <td class="text-right" style="color:#7c3aed;">{{ number_format($run->total_iuts, 0, ',', ' ') }}</td>
            <td class="text-right" style="color:#059669;">{{ number_format($run->total_net, 0, ',', ' ') }}</td>
            <td class="text-right">{{ number_format($run->total_brut + $run->total_cnss_employer, 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
</table>
</div>
</body>
</html>
