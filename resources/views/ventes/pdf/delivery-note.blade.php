<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bon de livraison {{ $deliveryNote->number }}</title>
    @php
        $color = $settings?->primary_color ?? '#0f766e';
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
        .party-right { display: table-cell; width: 48%; vertical-align: top; padding-left: 20px; }
        .party-label { font-size: 9px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .party-name { font-size: 12px; font-weight: bold; color: #111827; margin-bottom: 3px; }
        .party-detail { font-size: 10px; color: #374151; margin-bottom: 1px; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-bottom: 12px; }
        .status-validated { background: #d1fae5; color: #065f46; }
        .status-draft { background: #f3f4f6; color: #374151; }

        .watermark { position: fixed; top: 40%; left: 50%; transform: translateX(-50%) translateY(-50%) rotate(-35deg); font-size: 72px; font-weight: bold; color: rgba(0,0,0,0.06); text-transform: uppercase; white-space: nowrap; z-index: 0; }
        .terms-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; margin-top: 12px; }
        .terms-label { font-size: 9px; font-weight: bold; color: #374151; margin-bottom: 3px; text-transform: uppercase; }
        .terms-text { font-size: 9px; color: #6b7280; line-height: 1.55; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table thead th {
            background: {{ $color }}; color: #fff; padding: 6px 8px;
            text-align: left; font-size: 9px; text-transform: uppercase; font-weight: bold;
        }
        .items-table thead th.right { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid #e5e7eb; }
        .items-table tbody tr:nth-child(even) { background: #f9fafb; }
        .items-table tbody td { padding: 6.5px 8px; font-size: 10.5px; color: #374151; }
        .items-table tbody td.right { text-align: right; }

        .info-row { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; }
        .info-cell { display: table-cell; padding: 8px 12px; font-size: 10px; border-right: 1px solid #e5e7eb; }
        .info-cell:last-child { border-right: none; }
        .info-label { color: #6b7280; font-size: 9px; margin-bottom: 2px; }
        .info-value { font-weight: bold; color: #111827; }

        .signature-row { display: table; width: 100%; margin-top: 40px; }
        .signature-cell { display: table-cell; width: 33%; text-align: center; padding: 0 10px; }
        .signature-line { border-top: 1px solid #374151; margin-top: 30px; padding-top: 5px; font-size: 9.5px; color: #6b7280; }

        .notes-box { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 4px; padding: 8px 10px; margin-top: 12px; }
        .notes-label { font-size: 9px; font-weight: bold; color: #134e4a; margin-bottom: 3px; text-transform: uppercase; }
        .notes-text { font-size: 10px; color: #134e4a; }

        /* ── QR code de vérification ── */
        .qr-section { display:table; width:100%; margin-top:10px; padding-top:10px; border-top:1px solid #e5e7eb; }
        .qr-img-cell { display:table-cell; width:126px; vertical-align:middle; }
        .qr-img-cell img { width:110px; height:110px; }
        .qr-text-cell { display:table-cell; vertical-align:middle; padding-left:12px; }
        .qr-title { font-size:9.5px; font-weight:bold; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
        .qr-desc { font-size:9px; color:#6b7280; line-height:1.6; }
        .qr-ref { font-size:8px; color:#9ca3af; margin-top:5px; font-family:monospace; }

        .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    @php
        $company = \App\Models\Company::first();
        $logoBase64 = $settings?->show_logo !== false ? pdf_image_data($company?->logo) : null;
        $stampBase64 = pdf_image_data($settings?->stamp_image);

        /* ── QR code de vérification (URL signée HMAC) ── */
        $qrDataUri = null;
        try {
            $verifyUrl = \Illuminate\Support\Facades\URL::signedRoute(
                'delivery-note.verify', ['number' => $deliveryNote->number]
            );
            // PNG (GD) plutôt que SVG : DomPDF rend mal le SVG.
            $qrDataUri = app(\App\Services\QrPngService::class)->dataUri($verifyUrl);
        } catch (\Throwable) {}
    @endphp

    @if($settings?->show_watermark && $settings?->watermark_text)
    <div class="watermark">{{ $settings->watermark_text }}</div>
    @endif

    {{-- Entête --}}
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
            <div class="company-sub">IFU : {{ $company->ifu }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">BON DE LIVRAISON</div>
            <div class="doc-number">{{ $deliveryNote->number }}</div>
            <div class="doc-date">Émis le {{ $deliveryNote->issued_at?->format('d/m/Y') ?? '—' }}</div>
            @if($deliveryNote->order)
            <div class="doc-date">Réf. commande : {{ $deliveryNote->order->number }}</div>
            @endif
        </div>
    </div>

    <hr class="separator">

    {{-- Statut --}}
    @if($deliveryNote->status === 'valide')
        <span class="status-badge status-validated">&#10003; Livraison validée</span>
    @else
        <span class="status-badge status-draft">Brouillon</span>
    @endif

    {{-- Parties --}}
    <div class="parties">
        <div class="party-left">
            <div class="party-label">Expéditeur</div>
            <div class="party-name">{{ $company?->name ?? 'A3 ERP' }}</div>
            @if($company?->address) <div class="party-detail">{{ $company->address }}</div> @endif
            @if($company?->phone) <div class="party-detail">Tél. : {{ $company->phone }}</div> @endif
        </div>
        <div class="party-right">
            <div class="party-label">Destinataire</div>
            <div class="party-name">{{ $deliveryNote->client?->name ?? '—' }}</div>
            @if($deliveryNote->client?->address)
            <div class="party-detail">{{ $deliveryNote->client->address }}{{ $deliveryNote->client->city ? ', '.$deliveryNote->client->city : '' }}</div>
            @elseif($deliveryNote->client?->city)
            <div class="party-detail">{{ $deliveryNote->client->city }}</div>
            @endif
            @if($deliveryNote->client?->phone) <div class="party-detail">Tél. : {{ $deliveryNote->client->phone }}</div> @endif
            @if($deliveryNote->client?->email) <div class="party-detail">{{ $deliveryNote->client->email }}</div> @endif
            @if($deliveryNote->client?->ifu || $deliveryNote->client?->rccm || $deliveryNote->client?->tax_regime || $deliveryNote->client?->tax_division)
            <div style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6; font-size:7pt;">
                @if($deliveryNote->client?->ifu)<strong>IFU :</strong> {{ $deliveryNote->client->ifu }}<br>@endif
                @if($deliveryNote->client?->rccm)<strong>RCCM :</strong> {{ $deliveryNote->client->rccm }}<br>@endif
                @if($deliveryNote->client?->tax_regime)<strong>Régime fiscal :</strong> {{ $deliveryNote->client->tax_regime }}<br>@endif
                @if($deliveryNote->client?->tax_division)<strong>Division fiscale :</strong> {{ $deliveryNote->client->tax_division }}<br>@endif
            </div>
            @endif
        </div>
    </div>

    {{-- Infos dépôt --}}
    <div class="info-row">
        <div class="info-cell">
            <div class="info-label">Entrepôt d'expédition</div>
            <div class="info-value">{{ $deliveryNote->warehouse?->name ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Date de livraison</div>
            <div class="info-value">{{ $deliveryNote->issued_at?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Nombre de lignes</div>
            <div class="info-value">{{ $deliveryNote->items->count() }}</div>
        </div>
    </div>

    {{-- Tableau des lignes --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Désignation</th>
                <th>Référence</th>
                <th class="right" style="width:70px">Qté livrée</th>
                <th>Unité</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deliveryNote->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    {{ $item->description }}
                    @if($item->nb_toles > 0 && $item->metrage_par_tole > 0)
                    <br><span style="font-size:8px;color:#6b7280">{{ number_format($item->nb_toles, 0, ',', ' ') }} tôle(s) × {{ rtrim(rtrim(number_format($item->metrage_par_tole, 2, ',', ' '), '0'), ',') }} m = {{ number_format($item->quantity, 2, ',', ' ') }} ml</span>
                    @endif
                </td>
                <td style="font-family: monospace; font-size: 8px; color: #6b7280;">
                    {{ $item->product?->reference ?? '—' }}
                </td>
                <td class="right" style="font-weight: bold;">{{ number_format($item->quantity_delivered ?? $item->quantity, 2, ',', ' ') }}</td>
                <td>{{ $item->product?->unit?->abbreviation ?? 'pcs' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:12px;">Aucune ligne</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Notes --}}
    @if($deliveryNote->notes)
    <div class="notes-box">
        <div class="notes-label">Notes</div>
        <div class="notes-text">{{ $deliveryNote->notes }}</div>
    </div>
    @endif

    {{-- CGV --}}
    @if($settings?->terms_conditions)
    <div class="terms-box">
        <div class="terms-label">Conditions générales</div>
        <div class="terms-text">{{ $settings->terms_conditions }}</div>
    </div>
    @endif

    {{-- Signatures --}}
    <div class="signature-row">
        <div class="signature-cell">
            <div class="signature-line">
                @if($settings?->signature_name){{ $settings->signature_name }}@else Signature expéditeur @endif
                @if($settings?->signature_title)<br>{{ $settings->signature_title }}@endif
            </div>
        </div>
        <div class="signature-cell">
            @if($stampBase64)<img src="{{ $stampBase64 }}" class="signature-img" alt="Cachet">@endif
            <div class="signature-line">Cachet société</div>
        </div>
        <div class="signature-cell">
            <div class="signature-line">Signature destinataire</div>
        </div>
    </div>

    {{-- ── QR code de vérification ── --}}
    @if($qrDataUri)
    <div class="qr-section">
        <div class="qr-img-cell">
            <img src="{{ $qrDataUri }}" alt="QR Vérification">
        </div>
        <div class="qr-text-cell">
            <div class="qr-title">Vérification d'authenticité</div>
            <div class="qr-desc">
                Scannez ce QR code pour vérifier<br>
                l'authenticité de ce bon de livraison.<br>
                Le lien est sécurisé par signature cryptographique.
            </div>
            <div class="qr-ref">
                {{ $deliveryNote->number }}
                @if($deliveryNote->issued_at) · {{ $deliveryNote->issued_at->format('d/m/Y') }}@endif
                @if($company?->ifu) · IFU : {{ $company->ifu }}@endif
            </div>
        </div>
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
