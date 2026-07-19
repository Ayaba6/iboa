<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Contrat {{ $contract->number }}</title>
    @php
        $color = $settings?->primary_color ?? '#047857';
        $font  = $settings?->font_family   ?? 'DejaVu Sans';
        $logo  = pdf_image_data($company?->logo);
        $party = $contract->contract_type === 'vente' ? $contract->client : $contract->supplier;
        $partyLabel = $contract->contract_type === 'vente' ? 'CLIENT' : 'FOURNISSEUR';
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: {{ $font }}, sans-serif; font-size: 11px; color: #1f2937; }
        .page { padding: 25px 30px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .h-left { display: table-cell; width: 58%; vertical-align: top; }
        .h-right { display: table-cell; width: 42%; vertical-align: top; text-align: right; }
        .logo { max-height: 48px; max-width: 160px; margin-bottom: 5px; }
        .co-name { font-size: 17px; font-weight: bold; color: {{ $color }}; margin-bottom: 2px; }
        .co-sub { font-size: 9.5px; color: #6b7280; }
        .doc-title { font-size: 21px; font-weight: bold; color: {{ $color }}; }
        .doc-num { font-size: 13px; font-weight: bold; color: #374151; margin-top: 2px; }
        .doc-meta { font-size: 9.5px; color: #6b7280; margin-top: 3px; }
        .badge { display: inline-block; font-size: 9px; font-weight: bold; padding: 2px 7px; border-radius: 8px; background: {{ $color }}; color: #fff; margin-top: 4px; }
        hr.sep { border: none; border-top: 2px solid {{ $color }}; margin: 12px 0; }
        .desc { font-size: 12px; font-weight: bold; color: #111827; margin-bottom: 12px; }
        .parties { display: table; width: 100%; margin-bottom: 14px; }
        .p-cell { display: table-cell; width: 48%; vertical-align: top; }
        .p-gap { display: table-cell; width: 4%; }
        .p-label { font-size: 8.5px; font-weight: bold; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin-bottom: 4px; }
        .p-name { font-size: 11.5px; font-weight: bold; color: #111827; margin-bottom: 2px; }
        .p-det { font-size: 9.5px; color: #374151; margin-bottom: 1px; }
        .meta-tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-tbl td { padding: 4px 8px; font-size: 9.5px; border: 1px solid #e5e7eb; }
        .meta-tbl td.k { background: #f9fafb; color: #6b7280; width: 16%; font-weight: bold; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .items thead th { background: {{ $color }}; color: #fff; padding: 6px 8px; font-size: 8.5px; text-transform: uppercase; text-align: left; }
        .items thead th.r { text-align: right; }
        .items tbody td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
        .items tbody tr:nth-child(even) { background: #f9fafb; }
        .items td.r { text-align: right; }
        .total-box { float: right; width: 260px; border: 1px solid {{ $color }}; border-radius: 4px; overflow: hidden; }
        .total-row { display: table; width: 100%; background: {{ $color }}; }
        .total-row .l, .total-row .v { display: table-cell; padding: 8px 12px; color: #fff; font-weight: bold; font-size: 12px; }
        .total-row .v { text-align: right; }
        .obs { clear: both; margin-top: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .obs-l { font-size: 8.5px; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 3px; }
        .obs-t { font-size: 10px; color: #374151; }
        .sign { display: table; width: 100%; margin-top: 40px; }
        .sign-cell { display: table-cell; width: 48%; text-align: center; font-size: 9.5px; color: #6b7280; }
        .sign-line { border-top: 1px solid #9ca3af; margin: 30px 20px 4px; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8.5px; color: #9ca3af; text-align: center; }
        .pagenum { position: fixed; bottom: 4px; right: 28px; font-size: 7.5px; color: #9ca3af; }
    .pagenum:after { content: "Page " counter(page) " / " counter(pages); }
</style>
</head>
<body>
<div class="pagenum"></div>
<div class="page">
    <div class="header">
        <div class="h-left">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="">@endif
            <div class="co-name">{{ $company?->name ?? 'Société' }}</div>
            @if($company?->address)<div class="co-sub">{{ trim(($company->address ?? '') . ' ' . ($company->city ?? '')) }}</div>@endif
            @if($company?->ifu)<div class="co-sub">IFU : {{ $company->ifu }}@if($company?->rccm) &nbsp;·&nbsp; RCCM : {{ $company->rccm }}@endif</div>@endif
            @if($company?->phone)<div class="co-sub">Tél : {{ $company->phone }}</div>@endif
        </div>
        <div class="h-right">
            <div class="doc-title">CONTRAT</div>
            <div class="doc-num">{{ $contract->number }}</div>
            <div class="doc-meta">Date : {{ $contract->contract_date?->format('d/m/Y') }}</div>
            <div class="doc-meta">Période : {{ $contract->starts_at?->format('d/m/Y') }} → {{ $contract->ends_at?->format('d/m/Y') ?? '—' }}</div>
            <span class="badge">{{ strtoupper($contract->contract_type) }}{{ $contract->is_framework ? ' · CADRE' : '' }}</span>
        </div>
    </div>
    <hr class="sep">

    <div class="desc">{{ $contract->description }}</div>

    <div class="parties">
        <div class="p-cell">
            <div class="p-label">{{ $partyLabel }}</div>
            <div class="p-name">{{ $party?->name ?? '—' }}</div>
            @if($party?->address)<div class="p-det">{{ trim(($party->address ?? '') . ' ' . ($party->city ?? '')) }}</div>@endif
            @if($party?->ifu)<div class="p-det">IFU : {{ $party->ifu }}</div>@endif
            @if($party?->phone)<div class="p-det">Tél : {{ $party->phone }}</div>@endif
        </div>
        <div class="p-gap"></div>
        <div class="p-cell">
            <div class="p-label">CONDITIONS</div>
            <div class="p-det">Représentant : {{ $contract->salesRep?->name ?? '—' }}</div>
            <div class="p-det">Paiement : {{ $contract->payment_terms ?? '—' }}</div>
            <div class="p-det">Incoterm : {{ $contract->incoterm ?? '—' }}</div>
            <div class="p-det">Devise : {{ $contract->currency_code }}</div>
            @if($contract->category)<div class="p-det">Catégorie : {{ $contract->category }}</div>@endif
        </div>
    </div>

    <table class="items">
        <thead><tr>
            <th style="width:5%">#</th>
            <th>Désignation</th>
            <th style="width:8%">Unité</th>
            <th class="r" style="width:15%">Qté contract.</th>
            <th class="r" style="width:15%">Prix unit.</th>
            <th class="r" style="width:10%">Remise</th>
            <th class="r" style="width:18%">Montant HT</th>
        </tr></thead>
        <tbody>
            @foreach($contract->items as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->product?->name ?? $line->designation }}</td>
                <td>{{ $line->unit ?? '—' }}</td>
                <td class="r">{{ $fmt($line->quantity) }}</td>
                <td class="r">{{ $fmt($line->unit_price) }}</td>
                <td class="r">{{ (float) $line->discount_percent ? number_format((float) $line->discount_percent, 1, ',', '').'%' : '—' }}</td>
                <td class="r">{{ $fmt($line->amount_ht) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <div class="total-row"><div class="l">TOTAL CONTRACTUEL HT</div><div class="v">{{ $fmt($contract->total_ht) }} {{ $contract->currency_code }}</div></div>
    </div>

    @if($contract->observations)
    <div class="obs">
        <div class="obs-l">Observations</div>
        <div class="obs-t">{{ $contract->observations }}</div>
    </div>
    @endif

    <div class="sign">
        <div class="sign-cell"><div class="sign-line"></div>Le {{ $partyLabel === 'CLIENT' ? 'Client' : 'Fournisseur' }}</div>
        <div class="sign-cell"><div class="sign-line"></div>Pour {{ $company?->name ?? 'la Société' }}</div>
    </div>

    <div class="footer">
        Contrat {{ $contract->number }} — généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</div>
</body>
</html>
