<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Avoir {{ $creditNote->number }}</title>
    @php
        $color = $settings?->primary_color ?? '#6b21a8';
        $font  = $settings?->font_family   ?? 'DejaVu Sans';
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: {{ $font }}, sans-serif; font-size: 11.5px; color: #1f2937; background: #fff; }
        .page { padding: 25px 30px; }

        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header-left { display: table-cell; width: 55%; vertical-align: top; }
        .header-right { display: table-cell; width: 45%; vertical-align: top; text-align: right; }
        .logo { max-height: 50px; max-width: 160px; margin-bottom: 6px; }
        .company-name { font-size: 18px; font-weight: bold; color: {{ $color }}; margin-bottom: 3px; }
        .company-sub { font-size: 10px; color: #6b7280; margin-bottom: 2px; }
        .doc-title { font-size: 22px; font-weight: bold; color: {{ $color }}; margin-bottom: 4px; }
        .doc-number { font-size: 13px; font-weight: bold; color: #374151; }
        .doc-date { font-size: 10px; color: #6b7280; margin-top: 3px; }

        .separator { border: none; border-top: 2px solid {{ $color }}; margin: 16px 0; }

        .parties { display: table; width: 100%; margin-bottom: 16px; }
        .party-left { display: table-cell; width: 48%; vertical-align: top; }
        .party-right { display: table-cell; width: 48%; vertical-align: top; }
        .party-label { font-size: 9px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .party-name { font-size: 12px; font-weight: bold; color: #111827; margin-bottom: 3px; }
        .party-detail { font-size: 10px; color: #374151; margin-bottom: 1px; }

        .ref-box { background: #faf5ff; border: 1px solid #d8b4fe; border-radius: 4px; padding: 7px 10px; margin-bottom: 14px; }
        .ref-label { font-size: 9px; font-weight: bold; color: {{ $color }}; text-transform: uppercase; margin-bottom: 3px; }
        .ref-value { font-size: 10.5px; color: #374151; }
        .ref-row { display: table; width: 100%; }
        .ref-col { display: table-cell; width: 50%; }

        .watermark { position: fixed; top: 40%; left: 50%; transform: translateX(-50%) translateY(-50%) rotate(-35deg); font-size: 72px; font-weight: bold; color: rgba(0,0,0,0.06); text-transform: uppercase; white-space: nowrap; z-index: 0; }
        .terms-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; margin-top: 12px; }
        .terms-label { font-size: 9px; font-weight: bold; color: #374151; margin-bottom: 3px; text-transform: uppercase; }
        .terms-text { font-size: 9px; color: #6b7280; line-height: 1.55; }
        .signature-row { display: table; width: 100%; margin-top: 30px; }
        .signature-cell { display: table-cell; width: 50%; text-align: center; padding: 0 10px; }
        .signature-img { max-height: 40px; max-width: 120px; }
        .signature-line { border-top: 1px solid #374151; margin-top: 30px; padding-top: 5px; font-size: 9.5px; color: #6b7280; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .items-table thead th { background: {{ $color }}; color: #fff; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; font-weight: bold; }
        .items-table thead th.right { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid #e5e7eb; }
        .items-table tbody tr:nth-child(even) { background: #f9fafb; }
        .items-table tbody td { padding: 6px 8px; font-size: 10.5px; color: #374151; }
        .items-table tbody td.right { text-align: right; }

        .totals { width: 240px; float: right; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 14px; }
        .totals-row { display: table; width: 100%; }
        .totals-label { display: table-cell; padding: 6px 10px; font-size: 10.5px; color: #374151; background: #f9fafb; }
        .totals-value { display: table-cell; padding: 6px 10px; font-size: 10.5px; color: #111827; font-weight: 600; text-align: right; }
        .totals-row.grand { background: {{ $color }}; }
        .totals-row.grand .totals-label,
        .totals-row.grand .totals-value { color: #fff; font-size: 12px; font-weight: bold; background: {{ $color }}; }
        .totals-row.credit .totals-label,
        .totals-row.credit .totals-value { background: #fff7ed; color: #c2410c; font-weight: bold; }

        .credit-banner { background: #faf5ff; border: 1px solid #d8b4fe; border-radius: 4px; padding: 7px 10px; margin-bottom: 12px; font-size: 10px; color: {{ $color }}; }
        .credit-banner-title { font-weight: bold; font-size: 11px; margin-bottom: 2px; }

        .notes-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; padding: 8px 10px; }
        .notes-label { font-size: 9px; font-weight: bold; color: #92400e; margin-bottom: 3px; text-transform: uppercase; }
        .notes-text { font-size: 10px; color: #78350f; }

        .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
        .clearfix::after { content: ''; display: table; clear: both; }
        .pagenum { position: fixed; bottom: 4px; right: 28px; font-size: 7.5px; color: #9ca3af; }
    .pagenum:after { content: "Page " counter(page) " / " counter(pages); }
</style>
</head>
<body>
<div class="pagenum"></div>
<div class="page">

    @php
        $company = \App\Models\Company::first();
        $logoBase64  = $settings?->show_logo !== false ? pdf_image_data($company?->logo) : null;
        $sigBase64   = pdf_image_data($settings?->signature_image);
        $stampBase64 = pdf_image_data($settings?->stamp_image);
    @endphp

    @if($settings?->show_watermark && $settings?->watermark_text)
    <div class="watermark">{{ $settings->watermark_text }}</div>
    @endif

    {{-- En-tête --}}
    <div class="header">
        <div class="header-left">
            @if($logoBase64)<img src="{{ $logoBase64 }}" class="logo" alt="Logo">@endif
            <div class="company-name">{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</div>
            @if($company?->address)
            <div class="company-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}</div>
            @endif
            @if($company?->phone)<div class="company-sub">Tél : {{ $company->phone }}</div>@endif
            @if($company?->email)<div class="company-sub">{{ $company->email }}</div>@endif
            @if($company?->ifu || $company?->nif)
                <div class="company-sub">
                    @if($company->ifu)IFU : {{ $company->ifu }}@endif
                    @if($company->ifu && $company->nif) | @endif
                    @if($company->nif)NIF : {{ $company->nif }}@endif
                </div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">AVOIR</div>
            <div class="doc-number">{{ $creditNote->number }}</div>
            <div class="doc-date">Date : {{ $creditNote->issued_at?->format('d/m/Y') }}</div>
            @php
                $statusLabels = ['brouillon' => 'Brouillon', 'valide' => 'Validé', 'applique' => 'Appliqué', 'annule' => 'Annulé'];
            @endphp
            <div class="doc-date">Statut : {{ $statusLabels[$creditNote->status] ?? $creditNote->status }}</div>
        </div>
    </div>

    <hr class="separator">

    {{-- Parties --}}
    <div class="parties">
        <div class="party-left">
            <div class="party-label">Émetteur</div>
            <div class="party-name">{{ $company?->trade_name ?? $company?->name ?? '—' }}</div>
            @if($company?->address)<div class="party-detail">{{ $company->address }}</div>@endif
            @if($company?->city)<div class="party-detail">{{ $company->city }}</div>@endif
            @if($company?->phone)<div class="party-detail">{{ $company->phone }}</div>@endif
        </div>
        <div class="party-right">
            <div class="party-label">Client</div>
            <div class="party-name">{{ $creditNote->client?->name ?? '—' }}</div>
            @if($creditNote->client?->address)
            <div class="party-detail">{{ $creditNote->client->address }}{{ $creditNote->client->city ? ', '.$creditNote->client->city : '' }}</div>
            @elseif($creditNote->client?->city)
            <div class="party-detail">{{ $creditNote->client->city }}</div>
            @endif
            @if($creditNote->client?->phone)<div class="party-detail">Tél. : {{ $creditNote->client->phone }}</div>@endif
            @if($creditNote->client?->email)<div class="party-detail">{{ $creditNote->client->email }}</div>@endif
            @if($creditNote->client?->ifu || $creditNote->client?->rccm || $creditNote->client?->tax_regime || $creditNote->client?->tax_division)
            <div style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6; font-size:7pt;">
                @if($creditNote->client?->ifu)<strong>IFU :</strong> {{ $creditNote->client->ifu }}<br>@endif
                @if($creditNote->client?->rccm)<strong>RCCM :</strong> {{ $creditNote->client->rccm }}<br>@endif
                @if($creditNote->client?->tax_regime)<strong>Régime fiscal :</strong> {{ $creditNote->client->tax_regime }}<br>@endif
                @if($creditNote->client?->tax_division)<strong>Division fiscale :</strong> {{ $creditNote->client->tax_division }}<br>@endif
            </div>
            @endif
        </div>
    </div>

    {{-- Référence facture + motif --}}
    <div class="ref-box">
        <div class="ref-row">
            @if($creditNote->invoice)
            <div class="ref-col">
                <div class="ref-label">Facture liée</div>
                <div class="ref-value">{{ $creditNote->invoice->number }} — {{ $creditNote->invoice->issued_at?->format('d/m/Y') }}</div>
            </div>
            @endif
            @if($creditNote->reason)
            <div class="ref-col">
                <div class="ref-label">Motif de l'avoir</div>
                <div class="ref-value">{{ $creditNote->reason }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Lignes --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:42%">Description</th>
                <th class="right" style="width:10%">Qté</th>
                <th class="right" style="width:16%">Prix Unit. HT</th>
                <th class="right" style="width:10%">TVA%</th>
                <th class="right" style="width:18%">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditNote->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="right">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($item->unit_price, 0, ',', ' ') }}</td>
                <td class="right">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                <td class="right">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:10px;color:#9ca3af;">Aucune ligne.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totaux --}}
    <div class="clearfix">
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Sous-total HT</div>
                <div class="totals-value">{{ number_format($creditNote->subtotal_ht, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Total TVA</div>
                <div class="totals-value">{{ number_format($creditNote->total_tax, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="totals-row grand">
                <div class="totals-label">Total avoir TTC</div>
                <div class="totals-value">{{ number_format($creditNote->total_ttc, 0, ',', ' ') }} FCFA</div>
            </div>
            @if($creditNote->remaining_credit > 0)
            <div class="totals-row credit">
                <div class="totals-label">Solde restant</div>
                <div class="totals-value">{{ number_format($creditNote->remaining_credit, 0, ',', ' ') }} FCFA</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Solde crédit disponible --}}
    @if($creditNote->remaining_credit > 0 && $creditNote->status === 'valide')
    <div class="credit-banner">
        <div class="credit-banner-title">Crédit disponible</div>
        Un solde de <strong>{{ number_format($creditNote->remaining_credit, 0, ',', ' ') }} FCFA</strong> est disponible sur cet avoir.
        Il peut être appliqué en déduction d'une prochaine facture.
    </div>
    @endif

    {{-- Notes --}}
    @if($creditNote->notes)
    <div class="notes-box">
        <div class="notes-label">Notes</div>
        <div class="notes-text">{{ $creditNote->notes }}</div>
    </div>
    @endif

    {{-- Signature & cachet --}}
    @if($settings?->signature_name || $sigBase64 || $stampBase64)
    <div class="signature-row">
        <div class="signature-cell">
            @if($sigBase64)<img src="{{ $sigBase64 }}" class="signature-img" alt="Signature">@endif
            <div class="signature-line">
                {{ $settings?->signature_name ?? '' }}
                @if($settings?->signature_title)<br>{{ $settings->signature_title }}@endif
            </div>
        </div>
        <div class="signature-cell">
            @if($stampBase64)<img src="{{ $stampBase64 }}" class="signature-img" alt="Cachet">@endif
            <div class="signature-line">Cachet société</div>
        </div>
    </div>
    @endif

    {{-- CGV --}}
    @if($settings?->terms_conditions)
    <div class="terms-box">
        <div class="terms-label">Conditions générales de vente</div>
        <div class="terms-text">{{ $settings->terms_conditions }}</div>
    </div>
    @endif

    {{-- Pied de page --}}
    <div class="footer">
        @if($settings?->footer_text)
            {{ $settings->footer_text }}
        @else
            {{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}
            @if($company?->address) — {{ $company->address }}@endif
            @if($company?->phone) — {{ $company->phone }}@endif
            @if($company?->ifu) — IFU : {{ $company->ifu }}@endif
        @endif
    </div>

</div>
</body>
</html>
