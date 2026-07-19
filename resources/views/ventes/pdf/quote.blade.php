<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Devis {{ $quote->number }}</title>
    @php
        $color = $settings?->primary_color ?? '#1e40af';
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
        .party-right { display: table-cell; width: 48%; vertical-align: top; margin-left: 4%; }
        .party-label { font-size: 9px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .party-name { font-size: 12px; font-weight: bold; color: #111827; margin-bottom: 3px; }
        .party-detail { font-size: 10px; color: #374151; margin-bottom: 1px; }

        .info-row { display: table; width: 100%; margin-bottom: 14px; }
        .info-box { display: table-cell; padding: 6px 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 10px; }
        .info-box-label { color: #6b7280; font-size: 9px; margin-bottom: 2px; }
        .info-box-value { font-weight: bold; color: #111827; }

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
        .totals-sep { border: none; border-top: 1px solid #e5e7eb; }

        .notes-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; padding: 8px 10px; margin-top: 12px; }
        .notes-label { font-size: 9px; font-weight: bold; color: #92400e; margin-bottom: 3px; text-transform: uppercase; }
        .notes-text { font-size: 10px; color: #78350f; }

        .terms-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; margin-top: 12px; }
        .terms-label { font-size: 9px; font-weight: bold; color: #374151; margin-bottom: 3px; text-transform: uppercase; }
        .terms-text { font-size: 9px; color: #6b7280; line-height: 1.55; }

        .signature-row { display: table; width: 100%; margin-top: 30px; }
        .signature-cell { display: table-cell; width: 50%; text-align: center; padding: 0 10px; }
        .signature-img { max-height: 40px; max-width: 120px; }
        .signature-line { border-top: 1px solid #374151; margin-top: 30px; padding-top: 5px; font-size: 9.5px; color: #6b7280; }

        .watermark { position: fixed; top: 40%; left: 50%; transform: translateX(-50%) translateY(-50%) rotate(-35deg); font-size: 72px; font-weight: bold; color: rgba(0,0,0,0.06); text-transform: uppercase; white-space: nowrap; z-index: 0; }

        .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
        .validity-banner { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 5px 10px; margin-bottom: 10px; font-size: 10px; color: {{ $color }}; }
        .clearfix::after { content: ''; display: table; clear: both; }
        .pagenum { position: fixed; bottom: 4px; right: 28px; font-size: 7.5px; color: #9ca3af; }
    .pagenum:after { content: "Page " counter(page) " / " counter(pages); }
</style>
</head>
<body>
<div class="pagenum"></div>
<div class="page">

    {{-- Entête --}}
    @php
        $company = \App\Models\Company::first();
        $logoBase64  = $settings?->show_logo !== false ? pdf_image_data($company?->logo) : null;
        $sigBase64   = pdf_image_data($settings?->signature_image);
        $stampBase64 = pdf_image_data($settings?->stamp_image);
    @endphp

    @if($settings?->show_watermark && $settings?->watermark_text)
    <div class="watermark">{{ $settings->watermark_text }}</div>
    @endif

    <div class="header">
        <div class="header-left">
            @if($logoBase64)<img src="{{ $logoBase64 }}" class="logo" alt="Logo">@endif
            <div class="company-name">{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</div>
            @if($company?->address)
            <div class="company-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}</div>
            @endif
            @if($company?->phone)
            <div class="company-sub">Tél. : {{ $company->phone }}</div>
            @endif
            @if($company?->ifu)
            <div class="company-sub">IFU : {{ $company->ifu }} | RCCM : {{ $company->rccm ?? '—' }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">DEVIS</div>
            <div class="doc-number">{{ $quote->number }}</div>
            <div class="doc-date">Émis le {{ $quote->issued_at?->format('d/m/Y') ?? '—' }}</div>
            @if($quote->expires_at)
            <div class="doc-date">Valable jusqu'au {{ $quote->expires_at->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>

    <hr class="separator">

    {{-- Parties --}}
    <div class="parties">
        <div class="party-left">
            <div class="party-label">Émetteur</div>
            <div class="party-name">{{ $company?->name ?? 'A3 ERP' }}</div>
            @if($company?->legal_form) <div class="party-detail">{{ $company->legal_form }}</div> @endif
            @if($company?->email) <div class="party-detail">{{ $company->email }}</div> @endif
        </div>
        <div class="party-right" style="padding-left: 20px;">
            <div class="party-label">Destinataire</div>
            <div class="party-name">{{ $quote->client?->name ?? '—' }}</div>
            @if($quote->client?->address)
            <div class="party-detail">{{ $quote->client->address }}{{ $quote->client->city ? ', '.$quote->client->city : '' }}</div>
            @elseif($quote->client?->city)
            <div class="party-detail">{{ $quote->client->city }}</div>
            @endif
            @if($quote->client?->phone) <div class="party-detail">Tél. : {{ $quote->client->phone }}</div> @endif
            @if($quote->client?->email) <div class="party-detail">{{ $quote->client->email }}</div> @endif
            @if($quote->client?->ifu || $quote->client?->rccm || $quote->client?->tax_regime || $quote->client?->tax_division)
            <div style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6; font-size:7pt;">
                @if($quote->client?->ifu)<strong>IFU :</strong> {{ $quote->client->ifu }}<br>@endif
                @if($quote->client?->rccm)<strong>RCCM :</strong> {{ $quote->client->rccm }}<br>@endif
                @if($quote->client?->tax_regime)<strong>Régime fiscal :</strong> {{ $quote->client->tax_regime }}<br>@endif
                @if($quote->client?->tax_division)<strong>Division fiscale :</strong> {{ $quote->client->tax_division }}<br>@endif
            </div>
            @endif
        </div>
    </div>

    @if($quote->expires_at && $quote->expires_at->isPast())
    <div class="validity-banner" style="background:#fef2f2; border-color:#fca5a5; color:#991b1b;">
        ⚠ Ce devis a expiré le {{ $quote->expires_at->format('d/m/Y') }}
    </div>
    @elseif($quote->expires_at)
    <div class="validity-banner">
        Ce devis est valable jusqu'au {{ $quote->expires_at->format('d/m/Y') }}
    </div>
    @endif

    {{-- Tableau des lignes --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Description</th>
                <th class="right" style="width:50px">Qté</th>
                <th class="right" style="width:80px">Prix Unit.</th>
                <th class="right" style="width:45px">Rem.%</th>
                <th class="right" style="width:40px">TVA%</th>
                <th class="right" style="width:80px">Total HT</th>
                <th class="right" style="width:85px">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quote->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    {{ $item->description }}
                    @if($item->nb_toles > 0 && $item->metrage_par_tole > 0)
                    <br><span style="font-size:8px;color:#6b7280">{{ number_format($item->nb_toles, 0, ',', ' ') }} tôle(s) × {{ rtrim(rtrim(number_format($item->metrage_par_tole, 2, ',', ' '), '0'), ',') }} m = {{ number_format($item->quantity, 2, ',', ' ') }} ml</span>
                    @endif
                </td>
                <td class="right">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($item->unit_price, 0, ',', ' ') }}</td>
                <td class="right">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 1, ',', '').'%' : '—' }}</td>
                <td class="right">{{ number_format($item->tax_rate_value, 1, ',', '') }}%</td>
                <td class="right">{{ number_format($item->line_total_ht, 0, ',', ' ') }}</td>
                <td class="right">{{ number_format($item->line_total_ttc, 0, ',', ' ') }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:12px;">Aucune ligne</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totaux --}}
    <div class="clearfix">
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Sous-total HT</div>
                <div class="totals-value">{{ number_format($quote->subtotal_ht, 0, ',', ' ') }} FCFA</div>
            </div>
            <hr class="totals-sep">
            <div class="totals-row">
                <div class="totals-label">Total TVA</div>
                <div class="totals-value">{{ number_format($quote->total_tax, 0, ',', ' ') }} FCFA</div>
            </div>
            @if($quote->global_discount_amount > 0)
            <hr class="totals-sep">
            <div class="totals-row">
                <div class="totals-label">Remise globale</div>
                <div class="totals-value">— {{ number_format($quote->global_discount_amount, 0, ',', ' ') }} FCFA</div>
            </div>
            @endif
            <div class="totals-row grand">
                <div class="totals-label">TOTAL TTC</div>
                <div class="totals-value">{{ number_format($quote->total_ttc, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    @if($quote->notes)
    <div class="notes-box" style="margin-top: 60px;">
        <div class="notes-label">Notes et conditions</div>
        <div class="notes-text">{{ $quote->notes }}</div>
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

    {{-- Footer --}}
    <div class="footer">
        @if($settings?->footer_text)
            {{ $settings->footer_text }}
        @else
            {{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}
            @if($company?->address) — {{ $company->address }} @endif
            @if($company?->rccm) | RCCM : {{ $company->rccm }} @endif
            @if($company?->ifu) | IFU : {{ $company->ifu }} @endif
        @endif
    </div>

</div>
</body>
</html>
