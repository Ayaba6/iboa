<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @php
        $fmt2 = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2, ',', ' ');
        $dOnly = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
        $tOnly = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('H:i:s') : '—';
        // Logo société encodé pour DomPDF (jamais bloquant).
        $logo = function_exists('pdf_image_data') ? pdf_image_data($company?->logo) : null;

        // ── Lignes du bon de fabrication : MP consommée (−), PF produit (+), avarié, chute ──
        $rows = [];

        // Matière première consommée (bobines) — quantité négative
        foreach ($order->consumptions as $c) {
            $mp = $c->coil?->product;
            $rows[] = [
                'ref'     => $mp?->code_article ?? $c->coil?->reference ?? '—',
                'lot'     => $c->coil?->lot_number ?? '—',
                'des'     => $mp?->name ?? $c->coil?->reference ?? '—',
                'nbre'    => '',
                'metrage' => '',
                'qte'     => -1 * (float) ($c->length_consumed ?: $c->weight_consumed),
                'unit'    => $mp?->unit?->abbreviation ?? 'ML',
            ];
        }
        // Repli : pas encore de consommation → composants de la nomenclature
        if (empty($rows) && $order->billOfMaterial) {
            foreach ($order->billOfMaterial->lines as $l) {
                $rows[] = [
                    'ref' => $l->product?->code_article ?? '—', 'lot' => '',
                    'des' => $l->product?->name ?? '—', 'nbre' => '', 'metrage' => '',
                    'qte' => -1 * (float) (($l->quantity_per_meter ?? 0) * ($order->quantity_requested ?? 0)),
                    'unit' => $l->product?->unit?->abbreviation ?? 'ML',
                ];
            }
        }

        // Produit fini déclaré (+)
        $pfLot = optional($order->outputs->first())->lot_number;
        foreach ($order->outputs as $o) {
            $rows[] = [
                'ref' => $o->product?->code_article ?? '—', 'lot' => $o->lot_number ?? '—',
                'des' => $o->product?->name ?? '—',
                'nbre' => (float) $o->quantity ?: '',
                'metrage' => (float) $o->length ?: '',
                'qte' => (float) ($o->total_meters ?: $o->quantity),
                'unit' => $o->product?->unit?->abbreviation ?? 'ML',
            ];
        }
        // Repli : pas encore déclaré → produit fini planifié
        if ($order->outputs->isEmpty() && $order->product) {
            $rows[] = [
                'ref' => $order->product->code_article ?? '—', 'lot' => '',
                'des' => $order->product->name ?? '—', 'nbre' => (float) $order->quantity_requested ?: '',
                'metrage' => '', 'qte' => (float) $order->quantity_requested,
                'unit' => $order->product->unit?->abbreviation ?? 'ML',
            ];
        }

        // Sous-produits : avarié + chute (articles liés)
        foreach ([$order->product?->articleAvarie, $order->product?->articleChute] as $bp) {
            if (! $bp) continue;
            $rows[] = [
                'ref' => $bp->code_article ?? '—', 'lot' => $pfLot ?? '',
                'des' => $bp->name ?? '—', 'nbre' => '', 'metrage' => '', 'qte' => null,
                'unit' => $bp->unit?->abbreviation ?? 'ML',
            ];
        }
    @endphp
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 24px 26px; }
        body { font-size: 9pt; color: #111827; margin: 0; }
        .letterhead { display: table; width: 100%; margin-bottom: 10px; border-bottom: 1.5px solid #ea580c; padding-bottom: 8px; }
        .lh-logo { display: table-cell; width: 130px; vertical-align: middle; }
        .lh-logo img { max-height: 54px; max-width: 120px; }
        .lh-co { display: table-cell; vertical-align: middle; }
        .lh-co .n { font-size: 13pt; font-weight: bold; color: #ea580c; }
        .lh-co .a { font-size: 7.8pt; color: #4b5563; margin-top: 2px; }
        .lh-co .ids { font-size: 7.8pt; color: #6b7280; margin-top: 1px; }
        .rule { overflow: hidden; margin-bottom: 18px; }
        .rule .r { float: left; width: 140px; height: 4px; background: #c0392b; }
        .rule .g { float: left; width: 340px; height: 4px; background: #9ca3af; margin-top: 1px; }
        .title { font-size: 13pt; font-weight: bold; letter-spacing: .3px; margin: 2px 0 2px; }
        .title .num { margin-left: 26px; }
        .title-underline { width: 260px; border-bottom: 1px solid #111827; margin: 2px 0 16px 210px; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.info td { padding: 4px 6px; font-size: 9pt; vertical-align: top; }
        table.info td.k { color: #374151; width: 15%; }
        table.info td.v { color: #111827; width: 35%; }
        table.doc { width: 100%; border-collapse: collapse; margin-bottom: 26px; }
        table.doc th { border: 1px solid #4b5563; background: #fff; padding: 6px 6px; font-size: 8.5pt; font-weight: bold; text-align: left; }
        table.doc td { border: 1px solid #6b7280; padding: 7px 6px; font-size: 8.5pt; vertical-align: middle; }
        table.doc th.n, table.doc td.n { text-align: right; }
        .qte { font-weight: bold; }
        .neg { color: #b91c1c; }
        .ref { color: #1d4ed8; font-weight: bold; }
        .unit { color: #6b7280; font-size: 7.5pt; padding-left: 6px; }
        table.sign { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.sign td { width: 33.33%; vertical-align: top; padding: 0 14px; }
        .sign-title { text-align: center; font-weight: bold; font-size: 9.5pt; border-bottom: 1px solid #111827; padding-bottom: 4px; margin-bottom: 12px; }
        .sign-line { font-size: 8.5pt; color: #374151; margin-bottom: 14px; }
    </style>
</head>
<body>

    {{-- ── Entête société ── --}}
    <div class="letterhead">
        <div class="lh-logo">
            @if($logo)<img src="{{ $logo }}" alt="Logo">@endif
        </div>
        <div class="lh-co">
            <div class="n">{{ $company?->name ?? 'A3 ERP' }}</div>
            <div class="a">
                @if($company?->address){{ $company->address }}@endif
                @if($company?->city), {{ $company->city }}@endif
                @if($company?->phone) · Tél. {{ $company->phone }}@endif
                @if($company?->email) · {{ $company->email }}@endif
            </div>
            <div class="ids">
                @if($company?->ifu)IFU : {{ $company->ifu }}@endif
                @if($company?->rccm) · RCCM : {{ $company->rccm }}@endif
            </div>
        </div>
    </div>

    <div class="rule"><div class="r"></div><div class="g"></div></div>

    <div class="title">ORDRE DE FABRICATION :<span class="num">{{ $order->number ?? ('OF-' . $order->id) }}</span></div>
    <div class="title-underline"></div>

    {{-- ── Entête ── --}}
    <table class="info">
        <tr>
            <td class="k">Référence :</td><td class="v">{{ $order->order?->number ?? '—' }}</td>
            <td class="k">Client :</td><td class="v">{{ $order->client?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Date de fabrication :</td><td class="v">{{ $dOnly($order->launched_at ?? $order->created_at) }}</td>
            {{-- Site = site de production (le dépôt PF n'est pas un site) --}}
            <td class="k">Site :</td><td class="v">{{ $order->site_production ?? '01' }}@if($order->depotProduitFini?->code) · Dépôt {{ $order->depotProduitFini->code }}@endif</td>
        </tr>
        <tr>
            <td class="k">Date création :</td><td class="v">{{ $dOnly($order->created_at) }}</td>
            <td class="k">Date impression :</td><td class="v">{{ now()->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="k">Heure création :</td><td class="v">{{ $tOnly($order->created_at) }}</td>
            <td class="k">Heure impression :</td><td class="v">{{ now()->format('H:i:s') }}</td>
        </tr>
    </table>

    {{-- ── Articles lancés ── --}}
    <table class="doc">
        <thead>
            <tr>
                <th style="width:18%">Référence</th>
                <th style="width:16%">Lot</th>
                <th>Désignation</th>
                <th class="n" style="width:9%">Nbre</th>
                <th class="n" style="width:10%">Metrage</th>
                <th class="n" style="width:16%">Quantité</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                <td class="ref">{{ $r['ref'] }}</td>
                <td>{{ $r['lot'] }}</td>
                <td>{{ $r['des'] }}</td>
                <td class="n">{{ $fmt2($r['nbre']) }}</td>
                <td class="n">{{ $fmt2($r['metrage']) }}</td>
                <td class="n">
                    @if($r['qte'] !== null)
                        <span class="qte {{ $r['qte'] < 0 ? 'neg' : '' }}">{{ $fmt2($r['qte']) }}</span>
                    @endif
                    <span class="unit">{{ strtoupper($r["unit"]) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Signatures ── --}}
    <table class="sign">
        <tr>
            @foreach(['Coordinateur de production', 'Opérateur', 'Confirmation de suivi'] as $s)
            <td>
                <div class="sign-title">{{ $s }}</div>
                <div class="sign-line">Nom :</div>
                <div class="sign-line">Signature :</div>
                <div class="sign-line">Date :</div>
                <div class="sign-line">Heure :</div>
            </td>
            @endforeach
        </tr>
    </table>

</body>
</html>
