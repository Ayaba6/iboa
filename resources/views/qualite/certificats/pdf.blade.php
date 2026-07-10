<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Certificat Qualité {{ $certificat->number }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
.header { display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 30px; border-bottom: 3px solid #16a34a; }
.company-name { font-size: 16px; font-weight: bold; color: #166534; }
.doc-title { text-align: right; }
.doc-title h1 { font-size: 18px; font-weight: bold; color: #166534; }
.doc-title .number { font-size: 13px; color: #555; margin-top: 4px; }
.badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.badge-conforme { background: #dcfce7; color: #166534; }
.badge-non_conforme { background: #fee2e2; color: #991b1b; }
.badge-sous_reserve { background: #fef3c7; color: #92400e; }
.section { padding: 16px 30px; }
.section-title { font-size: 12px; font-weight: bold; color: #166534; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { margin-bottom: 8px; }
.field-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
.field-value { font-size: 12px; color: #111; font-weight: 500; margin-top: 2px; }
.result-box { border: 2px solid #16a34a; border-radius: 6px; padding: 12px 20px; text-align: center; margin: 16px 30px; }
.result-box.non_conforme { border-color: #dc2626; }
.result-box.sous_reserve { border-color: #d97706; }
.result-label { font-size: 14px; font-weight: bold; color: #166534; }
.result-box.non_conforme .result-label { color: #dc2626; }
.result-box.sous_reserve .result-label { color: #d97706; }
table { width: 100%; border-collapse: collapse; font-size: 11px; }
table th { background: #f0fdf4; color: #166534; font-weight: 600; padding: 7px 10px; text-align: left; border: 1px solid #d1fae5; }
table td { padding: 7px 10px; border: 1px solid #e5e7eb; }
.signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; padding: 20px 30px; border-top: 1px solid #e5e7eb; margin-top: 30px; }
.sig-box { border-top: 2px solid #6b7280; padding-top: 8px; }
.sig-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
.footer { padding: 10px 30px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
</style>
</head>
<body>

<div class="header">
    <div>
        <div class="company-name">{{ $certificat->company->name ?? 'PS INDUSTRIE SARL' }}</div>
        <div style="font-size:10px;color:#6b7280;margin-top:3px;">Industrie Métallurgique</div>
    </div>
    <div class="doc-title">
        <h1>CERTIFICAT DE QUALITÉ</h1>
        <div class="number">N° {{ $certificat->number }}</div>
        <div style="margin-top:6px;">
            <span class="badge badge-{{ $certificat->resultat }}">{{ $certificat->resultatLabel() }}</span>
        </div>
    </div>
</div>

<div class="result-box {{ $certificat->resultat }}">
    <div class="result-label">{{ strtoupper($certificat->resultatLabel()) }}</div>
    <div style="font-size:10px;color:#555;margin-top:3px;">{{ $certificat->typeLabel() }} — {{ $certificat->date_certificat?->format('d/m/Y') }}</div>
</div>

<div class="section">
    <div class="section-title">Identification</div>
    <div class="grid-2">
        <div>
            <div class="field">
                <div class="field-label">Numéro de Lot</div>
                <div class="field-value">{{ $certificat->lot_number ?? '—' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Fournisseur</div>
                <div class="field-value">{{ $certificat->fournisseur ?? '—' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Date de Réception</div>
                <div class="field-value">{{ $certificat->date_reception?->format('d/m/Y') ?? '—' }}</div>
            </div>
        </div>
        <div>
            <div class="field">
                <div class="field-label">Type de contrôle</div>
                <div class="field-value">{{ $certificat->typeLabel() }}</div>
            </div>
            <div class="field">
                <div class="field-label">Norme / Référence</div>
                <div class="field-value">{{ $certificat->norme ?? '—' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Date du Certificat</div>
                <div class="field-value">{{ $certificat->date_certificat?->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Caractéristiques physiques (§13.5 CDC)</div>
    <table>
        <thead>
            <tr>
                <th>Paramètre</th>
                <th>Valeur mesurée</th>
                <th>Unité</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Poids réel</td>
                <td>{{ $certificat->poids_reel ? number_format($certificat->poids_reel, 3) : '—' }}</td>
                <td>t</td>
            </tr>
            <tr>
                <td>Largeur</td>
                <td>{{ $certificat->largeur_mm ? number_format($certificat->largeur_mm, 2) : '—' }}</td>
                <td>mm</td>
            </tr>
            <tr>
                <td>Épaisseur</td>
                <td>{{ $certificat->epaisseur_mm ? number_format($certificat->epaisseur_mm, 3) : '—' }}</td>
                <td>mm</td>
            </tr>
            <tr>
                <td>Couleur</td>
                <td colspan="2">{{ $certificat->couleur ?? '—' }}</td>
            </tr>
        </tbody>
    </table>
</div>

@if($certificat->observations)
<div class="section">
    <div class="section-title">Observations</div>
    <p style="font-size:11px;color:#374151;line-height:1.5;">{{ $certificat->observations }}</p>
</div>
@endif

<div class="signatures">
    <div class="sig-box">
        <div class="sig-label">Contrôleur Qualité</div>
        <div style="font-size:12px;font-weight:500;margin-top:6px;">{{ $certificat->controleur?->name ?? '' }}</div>
        <div style="margin-top:30px;"></div>
    </div>
    <div class="sig-box">
        <div class="sig-label">Responsable Qualité (Validateur)</div>
        <div style="font-size:12px;font-weight:500;margin-top:6px;">{{ $certificat->validateur?->name ?? '' }}</div>
        @if($certificat->validated_at)
        <div style="font-size:9px;color:#6b7280;margin-top:3px;">Validé le {{ $certificat->validated_at->format('d/m/Y à H:i') }}</div>
        @else
        <div style="margin-top:30px;"></div>
        @endif
    </div>
</div>

<div class="footer">
    Certificat généré par ERP IBOA • {{ now()->format('d/m/Y H:i') }} • Document conforme aux exigences §8 & §10 du Cahier des Charges
</div>

</body>
</html>
