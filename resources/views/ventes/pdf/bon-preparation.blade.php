<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bon de préparation {{ $bonPreparation->number }}</title>
    @php
        $color   = $settings?->primary_color ?? '#0f766e';
        $font    = $settings?->font_family   ?? 'DejaVu Sans';
        $co      = currentCompany();
        $order   = $bonPreparation->order;
        $client  = $order?->client;
        $fmt     = fn ($n) => number_format((float) $n, 0, ',', ' ');
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: {{ $font }}, 'DejaVu Sans', sans-serif; font-size: 11.5px; color: #1f2937; }
        .page { padding: 25px 30px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header-left { display: table-cell; width: 58%; vertical-align: top; }
        .header-right { display: table-cell; width: 42%; vertical-align: top; text-align: right; }
        .company-name { font-size: 17px; font-weight: bold; color: {{ $color }}; margin-bottom: 3px; }
        .company-sub { font-size: 10px; color: #6b7280; margin-bottom: 1px; }
        .doc-title { font-size: 21px; font-weight: bold; color: {{ $color }}; }
        .doc-number { font-size: 13px; font-weight: bold; color: #374151; font-family: 'DejaVu Sans Mono', monospace; }
        .doc-date { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .separator { border: none; border-top: 2px solid {{ $color }}; margin: 12px 0 16px; }
        .parties { display: table; width: 100%; margin-bottom: 16px; }
        .party { display: table-cell; width: 50%; vertical-align: top; padding-right: 16px; }
        .party-label { font-size: 9px; font-weight: bold; color: {{ $color }}; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
        .party-box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .party-name { font-weight: bold; font-size: 12px; }
        .party-line { font-size: 10px; color: #4b5563; margin-top: 1px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items thead th { background: {{ $color }}; color: #fff; font-size: 9.5px; text-transform: uppercase; padding: 6px 8px; text-align: left; }
        table.items thead th.r { text-align: right; }
        table.items thead th.c { text-align: center; }
        table.items tbody td { border-bottom: 1px solid #eef0f2; padding: 6px 8px; font-size: 10.5px; }
        table.items tbody td.r { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        table.items tbody td.c { text-align: center; }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
        .tot { margin-top: 6px; text-align: right; font-size: 11px; }
        .tot strong { color: {{ $color }}; }
        .checkbox { display: inline-block; width: 11px; height: 11px; border: 1px solid #6b7280; }
        .sign { display: table; width: 100%; margin-top: 40px; }
        .sign-cell { display: table-cell; width: 33%; text-align: center; font-size: 10px; color: #4b5563; padding: 0 8px; }
        .sign-line { border-top: 1px solid #9ca3af; margin: 34px 12px 4px; }
        .foot { margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8.5px; color: #9ca3af; text-align: center; }
        .badge { display: inline-block; font-size: 9px; font-weight: bold; padding: 2px 8px; border-radius: 3px; background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $co?->name ?? 'OA METAL INDUSTRIE' }}</div>
            @if($co?->address)<div class="company-sub">{{ $co->address }}{{ $co->city ? ', ' . $co->city : '' }}</div>@endif
            @if($co?->phone)<div class="company-sub">Tél : {{ $co->phone }}</div>@endif
            @if($co?->rccm)<div class="company-sub">RCCM : {{ $co->rccm }}@if($co?->ifu) · IFU : {{ $co->ifu }}@endif</div>@endif
        </div>
        <div class="header-right">
            <div class="doc-title">BON DE PRÉPARATION</div>
            <div class="doc-number">{{ $bonPreparation->number }}</div>
            <div class="doc-date">Créé le {{ optional($bonPreparation->created_at)->format('d/m/Y') }}</div>
            <div class="doc-date">Statut : <span class="badge">{{ ucfirst(str_replace('_', ' ', $bonPreparation->status)) }}</span></div>
        </div>
    </div>
    <hr class="separator">

    <div class="parties">
        <div class="party">
            <div class="party-label">Client</div>
            <div class="party-box">
                <div class="party-name">{{ $client?->name ?? '—' }}</div>
                @if($client?->trade_name)<div class="party-line">{{ $client->trade_name }}</div>@endif
                @if($client?->phone)<div class="party-line">Tél : {{ $client->phone }}</div>@endif
                @if($client?->address)<div class="party-line">{{ $client->address }}{{ $client->city ? ', ' . $client->city : '' }}</div>@endif
            </div>
        </div>
        <div class="party">
            <div class="party-label">Commande</div>
            <div class="party-box">
                <div class="party-name">{{ $order?->number ?? '—' }}</div>
                <div class="party-line">Mode règlement : {{ $bonPreparation->payment_mode ?? '—' }}</div>
                @if($bonPreparation->validated_at)<div class="party-line">Validé le {{ $bonPreparation->validated_at->format('d/m/Y H:i') }}</div>@endif
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:36px" class="c">Chargé</th>
                <th>Article</th>
                <th>Référence</th>
                <th class="r" style="width:80px">Quantité</th>
                <th class="c" style="width:50px">Unité</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order?->items ?? [] as $it)
            <tr>
                <td class="c"><span class="checkbox"></span></td>
                <td>{{ $it->description ?: $it->product?->name ?? '—' }}</td>
                <td>{{ $it->product?->reference ?? '—' }}</td>
                <td class="r">{{ $fmt($it->quantity) }}</td>
                <td class="c">{{ $it->product?->unit?->abbreviation ?? 'U' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="c" style="padding:16px;color:#9ca3af">Aucun article.</td></tr>
            @endforelse
        </tbody>
    </table>

    @php $totalQty = collect($order?->items ?? [])->sum('quantity'); @endphp
    <div class="tot">Total à charger : <strong>{{ $fmt($totalQty) }}</strong> unité(s)</div>

    @if($bonPreparation->notes)
    <div style="margin-top:14px;font-size:10px;color:#4b5563"><strong>Observations :</strong> {{ $bonPreparation->notes }}</div>
    @endif

    <div class="sign">
        <div class="sign-cell"><div class="sign-line"></div>Préparé par (magasinier)</div>
        <div class="sign-cell"><div class="sign-line"></div>Chargé / vérifié</div>
        <div class="sign-cell"><div class="sign-line"></div>Chauffeur / enlèvement</div>
    </div>

    <div class="foot">
        {{ $co?->name ?? 'OA METAL INDUSTRIE' }} — Bon de préparation {{ $bonPreparation->number }} — imprimé le {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
