<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Facture {{ $invoice->number }}</title>
    @php
        $color  = $settings?->primary_color ?? '#1d4ed8';
        $font   = $settings?->font_family   ?? 'DejaVu Sans';

        /* ── Données société ────────────────────────── */
        $company = \App\Models\Company::with('bankAccounts')->first();

        /* ── Encodage logo / signature / cachet (jamais bloquant) ──── */
        $logoBase64  = $settings?->show_logo !== false ? pdf_image_data($company?->logo) : null;
        $sigBase64   = pdf_image_data($settings?->signature_image);
        $stampBase64 = pdf_image_data($settings?->stamp_image);

        /* ── TVA par taux (lignes « TVA x% » du bloc Totaux) ── */
        $tvaRecap = [];
        foreach ($invoice->items as $item) {
            $rate = (float) $item->tax_rate_value;
            if (!isset($tvaRecap[$rate])) $tvaRecap[$rate] = ['base' => 0, 'tva' => 0];
            $tvaRecap[$rate]['base'] += (float) $item->line_total_ht;
            $tvaRecap[$rate]['tva']  += (float) $item->line_tax;
        }
        ksort($tvaRecap);

        /* ── Total en lettres (PHP intl) ────────────── */
        $totalWords = '';
        try {
            if (class_exists('NumberFormatter')) {
                $fmt = new NumberFormatter('fr_FR', NumberFormatter::SPELLOUT);
                $totalWords = mb_strtoupper($fmt->format((int) round($invoice->total_ttc)));
            }
        } catch (\Throwable) {}

        /* ── Coordonnées bancaires ──────────────────── */
        $bank = $company?->bankAccounts->firstWhere('is_default', true)
             ?? $company?->bankAccounts->first();

        /* ── Retard de paiement ─────────────────────── */
        $isOverdue = $invoice->due_at
                  && $invoice->due_at->isPast()
                  && !in_array($invoice->status, ['payee', 'annulee']);

        /* ── Statut libellé ─────────────────────────── */
        $statusLabel = match($invoice->status) {
            'payee'               => 'PAYÉE',
            'partiellement_payee' => 'PARTIELLEMENT PAYÉE',
            'annulee'             => 'ANNULÉE',
            'en_retard'           => 'EN RETARD',
            default               => null,
        };

        /* ── QR code de vérification (URL signée HMAC) ── */
        $qrDataUri = null;
        try {
            $verifyUrl = \Illuminate\Support\Facades\URL::signedRoute(
                'invoice.verify', ['number' => $invoice->number]
            );
            // PNG (GD) plutôt que SVG : DomPDF rend mal le SVG (fichier gonflé + pages fantômes).
            $qrDataUri = app(\App\Services\QrPngService::class)->dataUri($verifyUrl);
        } catch (\Throwable) {}
    @endphp

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: {{ $font }}, 'DejaVu Sans', sans-serif; font-size:11px; color:#1f2937; background:#fff; }
        .page { padding:4px 28px 4px; }
        /* [Mise en page] Numérotation « Page X / Y » répétée sur chaque page
           (n'apparaît que sur les documents multipages). */
        .pagenum { position: fixed; bottom: 4px; right: 28px; font-size: 7.5px; color: #9ca3af; }
        .pagenum:after { content: "Page " counter(page) " / " counter(pages); }

        /* ── Filigrane ── */
        .watermark { position:fixed; top:42%; left:50%; transform:translateX(-50%) translateY(-50%) rotate(-35deg);
                     font-size:68px; font-weight:bold; color:rgba(0,0,0,.05); text-transform:uppercase;
                     white-space:nowrap; z-index:0; }

        /* ── Bandeau retard ── */
        .overdue-banner { background:#fef2f2; border:1.5px solid #fca5a5; border-radius:3px;
                          padding:5px 10px; margin-bottom:10px; font-size:10px; color:#991b1b; font-weight:bold; }

        /* ── Bandeau statut ── */
        .status-stamp { display:inline-block; border:2px solid; border-radius:3px; padding:3px 10px;
                        font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:.05em;
                        margin-top:5px; }
        .status-stamp.payee              { border-color:#059669; color:#059669; }
        .status-stamp.partiellement_payee{ border-color:#d97706; color:#d97706; }
        .status-stamp.en_retard          { border-color:#dc2626; color:#dc2626; }
        .status-stamp.annulee            { border-color:#6b7280; color:#6b7280; }

        /* ── En-tête ── */
        .header { display:table; width:100%; margin-bottom:3px; }
        .header-left  { display:table-cell; width:58%; vertical-align:top; }
        .header-right { display:table-cell; width:42%; vertical-align:top; text-align:right; }
        .logo { max-height:52px; max-width:170px; margin-bottom:5px; }
        .co-name { font-size:15px; font-weight:bold; color:{{ $color }}; margin-bottom:2px; }
        .co-legal { font-size:9px; color:#4b5563; margin-bottom:1px; }
        .co-capital { font-size:9px; color:#6b7280; }
        .co-addr { font-size:10px; color:#374151; margin-top:3px; line-height:1.5; }
        .co-ids  { font-size:9px; color:#374151; margin-top:3px; line-height:1.6; }
        .co-ids strong { color:#111827; }

        /* ── Titre document ── */
        .doc-badge { display:inline-block; background:{{ $color }}; color:#fff; font-size:17px;
                     font-weight:bold; padding:5px 14px; border-radius:3px; letter-spacing:.04em; margin-bottom:4px; }
        .doc-type-sub { font-size:9.5px; color:#6b7280; margin-bottom:6px; }
        .doc-meta { font-size:10px; line-height:1.7; }
        .doc-meta td:first-child { color:#6b7280; padding-right:8px; text-align:right; }
        .doc-meta td:last-child { font-weight:600; color:#111827; }

        /* ── Séparateur ── */
        .sep { border:none; border-top:2.5px solid {{ $color }}; margin:5px 0; }
        .sep-thin { border:none; border-top:1px solid #e5e7eb; margin:5px 0; }

        /* ── Parties vendeur/acheteur ── */
        .parties { display:table; width:100%; margin-bottom:5px; }
        .party-col { display:table-cell; width:50%; vertical-align:top; padding:8px 10px;
                     border:1px solid #e5e7eb; border-radius:3px; }
        .party-col.right { padding-left:14px; border-left:none; }
        .party-lbl  { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:.07em;
                      color:#fff; background:{{ $color }}; padding:2px 6px; border-radius:2px;
                      display:inline-block; margin-bottom:5px; }
        .party-name { font-size:12px; font-weight:bold; color:#111827; margin-bottom:3px; }
        .party-line { font-size:10px; color:#374151; line-height:1.55; }
        .party-id   { font-size:9.5px; color:#374151; line-height:1.6; }
        .party-id strong { color:#111827; }

        /* ── Refs ── */
        .refs { background:#f8fafc; border:1px solid #e5e7eb; border-radius:3px; padding:5px 10px;
                font-size:10px; margin-bottom:10px; }
        .refs td { padding:1px 12px 1px 0; color:#374151; }
        .refs td strong { color:#111827; }

        /* ── Tableau articles ── */
        .items-table { width:100%; border-collapse:collapse; margin-bottom:0; font-size:10px; }
        .items-table thead th { background:{{ $color }}; color:#fff; padding:6px 7px;
                                text-align:left; font-size:8.5px; text-transform:uppercase;
                                font-weight:bold; letter-spacing:.03em; }
        .items-table thead th.r { text-align:right; }
        .items-table tbody tr:nth-child(even) { background:#f9fafb; }
        .items-table tbody tr { border-bottom:1px solid #e5e7eb; }
        .items-table tbody td { padding:5.5px 7px; color:#374151; vertical-align:top; }
        .items-table tbody td.r { text-align:right; }
        .items-table tfoot td { padding:5px 7px; font-size:9px; color:#6b7280; }
        /* Mode compact : colonnes supplémentaires (Longueur/Épaisseur) — évite le débordement A4 portrait */
        .items-table.cols-extra { font-size:8.5px; }
        .items-table.cols-extra thead th { font-size:7px; padding:5px 4px; letter-spacing:0; }
        .items-table.cols-extra tbody td { padding:4px 4px; }
        .items-table.cols-extra tfoot td { padding:4px 4px; font-size:8px; }

        /* ── Bloc totaux + TVA (table deux colonnes) ── */
        .bottom-wrap { display:table; width:100%; margin-top:3px; }
        .tva-col   { display:table-cell; width:46%; vertical-align:top; padding-right:12px; }
        .total-col { display:table-cell; width:54%; vertical-align:top; }

        /* Récap TVA */
        .tva-box { border:1px solid #e5e7eb; border-radius:3px; overflow:hidden; }
        .tva-box .hd { background:#f3f4f6; padding:4px 8px; font-size:8.5px; font-weight:bold;
                       text-transform:uppercase; color:#374151; letter-spacing:.05em; }
        .tva-table { width:100%; border-collapse:collapse; font-size:10px; }
        .tva-table th { background:#f9fafb; padding:4px 8px; font-size:8px; color:#6b7280;
                        text-transform:uppercase; text-align:right; border-bottom:1px solid #e5e7eb; }
        .tva-table th:first-child { text-align:left; }
        .tva-table td { padding:4.5px 8px; color:#374151; text-align:right; border-bottom:1px solid #f3f4f6; }
        .tva-table td:first-child { text-align:left; }

        /* Totaux */
        .tot-table { width:100%; border-collapse:collapse; font-size:10.5px; }
        .tot-table td { padding:5px 10px; }
        .tot-table .lbl { color:#374151; background:#f9fafb; }
        .tot-table .val { text-align:right; color:#111827; font-weight:600; }
        .tot-table tr { border-bottom:1px solid #e5e7eb; }
        .tot-table .grand { background:{{ $color }} !important; }
        .tot-table .grand td { color:#fff; font-size:12px; font-weight:bold; padding:7px 10px; }
        .tot-table .owed td { background:#fef2f2; color:#991b1b; font-weight:bold; }
        .tot-table .paid td { color:#065f46; }

        /* ── Total en lettres ── */
        .lettres { background:#eff6ff; border:1px solid #bfdbfe; border-radius:3px;
                   padding:4px 9px; margin-top:4px; font-size:9.5px; }
        .lettres-lbl { font-size:8.5px; font-weight:bold; text-transform:uppercase;
                       color:#1d4ed8; letter-spacing:.04em; }
        .lettres-txt { font-weight:bold; color:#1e3a8a; font-size:10px; line-height:1.4; }

        /* ── Règlement ── */
        .reglement { background:#f0fdf4; border:1px solid #a7f3d0; border-radius:3px;
                     padding:7px 10px; margin-top:10px; }
        .reglement-lbl { font-size:8.5px; font-weight:bold; text-transform:uppercase;
                         color:#065f46; margin-bottom:4px; letter-spacing:.04em; }
        .reglement-row { font-size:10px; color:#374151; margin-bottom:2px; }
        .reglement-row strong { color:#111827; }

        /* Mobile money spécifique BF */
        .mobile-money { display:inline-block; background:#ff6600; color:#fff; font-size:8.5px;
                        font-weight:bold; padding:1px 5px; border-radius:2px; margin-right:4px; }
        .mobile-money.moov { background:#003f87; }

        /* ── Notes ── */
        .notes-box { background:#fffbeb; border:1px solid #fde68a; border-radius:3px;
                     padding:7px 10px; margin-top:10px; }
        .notes-lbl { font-size:8.5px; font-weight:bold; color:#92400e; text-transform:uppercase; margin-bottom:3px; }
        .notes-txt { font-size:10px; color:#78350f; }

        /* ── Signatures ── */
        .sig-row { display:table; width:100%; margin-top:4px; page-break-inside:avoid; }
        .sig-cell { display:table-cell; width:50%; text-align:center; padding:0 8px; vertical-align:bottom; }
        .sig-img  { max-height:28px; max-width:96px; margin-bottom:2px; }
        .sig-box  { border-top:1px solid #374151; padding-top:5px; font-size:9px; color:#6b7280; }
        .sig-box strong { color:#111827; font-size:9.5px; display:block; margin-bottom:1px; }

        /* Espace signature client */
        .client-sig { border:1px dashed #d1d5db; border-radius:3px; padding:7px; text-align:center; }
        .client-sig-lbl { font-size:9px; color:#9ca3af; margin-bottom:10px; }

        /* ── CGV ── */
        .cgv { background:#f9fafb; border:1px solid #e5e7eb; border-radius:3px;
               padding:5px 9px; margin-top:4px; page-break-inside:avoid; }
        .cgv-lbl { font-size:8.5px; font-weight:bold; color:#374151; text-transform:uppercase; margin-bottom:3px; }
        .cgv-txt { font-size:8px; color:#6b7280; line-height:1.35; }

        /* Pénalités (mention légale obligatoire Burkina) */
        .penalites { font-size:7.5px; color:#6b7280; margin-top:3px; line-height:1.3; }

        /* ── Pied de page ── */
        .footer { margin-top:4px; border-top:1px solid #e5e7eb; padding-top:4px;
                  font-size:8.5px; color:#9ca3af; text-align:center; line-height:1.6; }
        .footer strong { color:#6b7280; }

        .clearfix::after { content:''; display:table; clear:both; }
        .mt8 { margin-top:8px; }
        .nobr { white-space:nowrap; }

        /* ── QR code de vérification ── */
        .qr-section { display:table; width:100%; margin-top:3px; padding-top:4px; border-top:1px solid #e5e7eb; page-break-inside:avoid; }
        .qr-img-cell { display:table-cell; width:78px; vertical-align:middle; }
        .qr-img-cell img { width:66px; height:66px; }
        .qr-text-cell { display:table-cell; vertical-align:middle; padding-left:12px; }
        .qr-title { font-size:9.5px; font-weight:bold; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
        .qr-desc { font-size:8px; color:#6b7280; line-height:1.4; }
        .qr-ref { font-size:8px; color:#9ca3af; margin-top:5px; font-family:monospace; }
    </style>
</head>
<body>
<div class="pagenum"></div>
<div class="page">

    {{-- Filigrane --}}
    @if($settings?->show_watermark && $settings?->watermark_text)
    <div class="watermark">{{ $settings->watermark_text }}</div>
    @endif

    {{-- Alerte retard --}}
    @if($isOverdue)
    <div class="overdue-banner">
        ⚠&nbsp; FACTURE EN RETARD — Échéance dépassée depuis le {{ $invoice->due_at->format('d/m/Y') }}
        &nbsp;|&nbsp; Des pénalités de retard sont applicables.
    </div>
    @endif

    {{-- ════════════════════════════════════════════════
         EN-TÊTE : Société (gauche) + Document (droite)
    ════════════════════════════════════════════════ --}}
    <div class="header">

        {{-- Gauche : Logo + Infos société --}}
        <div class="header-left">
            @if($logoBase64)
            <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
            @endif

            <div class="co-name">{{ $company?->name ?? 'OA METAL INDUSTRIE' }}</div>
            @php
                $enseigne = ($company?->trade_name && $company->trade_name !== $company->name)
                    ? $company->trade_name.($company?->sigle ? ' ('.$company->sigle.')' : '')
                    : ($company?->sigle ?: null);
                $legalParts = [];
                if ($company?->legal_form) {
                    $lf = $company->legal_form;
                    if ($company->share_capital) {
                        $lf .= ' — Capital : '.number_format($company->share_capital, 0, ',', ' ').' '.($company->share_capital_currency ?? 'FCFA');
                    }
                    $legalParts[] = $lf;
                }
                if ($enseigne) { $legalParts[] = $enseigne; }
            @endphp
            @if($legalParts)
            <div class="co-legal">{{ implode(' · ', $legalParts) }}</div>
            @endif

            <div class="co-addr">
                @if($company?->address){{ $company->address }}@endif
                @if($company?->city), {{ $company->city }}@endif
                @if($company?->country && $company->country !== 'Burkina Faso'), {{ $company->country }}@endif
                @if($company?->phone)
                <br>Tél. : {{ $company->phone }}@if($company?->phone2) / {{ $company->phone2 }}@endif
                @endif
                @if($company?->email)<br>{{ $company->email }}@if($company?->website) · {{ $company->website }}@endif
                @elseif($company?->website)<br>{{ $company->website }}@endif
            </div>

            <div class="co-ids">
                @if($company?->ifu)<strong>IFU :</strong> {{ $company->ifu }}&nbsp;&nbsp;@endif
                @if($company?->rccm)<strong>RCCM :</strong> {{ $company->rccm }}&nbsp;&nbsp;@endif
                @if($company?->nif)<strong>NIF :</strong> {{ $company->nif }}&nbsp;&nbsp;@endif
                @if($company?->cnss_number)<strong>CNSS :</strong> {{ $company->cnss_number }}@endif
            </div>
        </div>

        {{-- Droite : Titre + méta --}}
        <div class="header-right">
            <div class="doc-badge">
                @if($invoice->type === 'proforma') FACTURE PROFORMA
                @elseif($invoice->type === 'acompte') FACTURE D'ACOMPTE
                @else FACTURE
                @endif
            </div>

            <br>
            <table class="doc-meta" style="display:inline-table; margin-top:4px;">
                <tr>
                    <td>N° :</td>
                    <td style="font-size:11px;">{{ $invoice->number }}</td>
                </tr>
                <tr>
                    <td>Date d'émission :</td>
                    <td>{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
                </tr>
                @if($invoice->due_at)
                <tr>
                    <td>Date d'échéance :</td>
                    <td style="{{ $isOverdue ? 'color:#dc2626;' : '' }}">{{ $invoice->due_at->format('d/m/Y') }}</td>
                </tr>
                @endif
                @if($invoice->payment_terms)
                <tr>
                    <td>Délai règlement :</td>
                    <td>{{ $invoice->payment_terms }}</td>
                </tr>
                @endif
            </table>

            @if($statusLabel)
            <br>
            <span class="status-stamp {{ $invoice->status }}">{{ $statusLabel }}</span>
            @endif
        </div>
    </div>

    <hr class="sep">

    {{-- ════════════════════════════════════════════════
         VENDEUR / ACHETEUR
    ════════════════════════════════════════════════ --}}
    <div class="parties">
        {{-- Vendeur --}}
        <div class="party-col">
            <div class="party-lbl">Vendeur / Prestataire</div>
            <div class="party-name">{{ $company?->name ?? 'OA METAL INDUSTRIE' }}</div>
            @if($company?->legal_form)
            <div class="party-line">{{ $company->legal_form }}
                @if($company->share_capital) — Cap. {{ number_format($company->share_capital, 0, ',', ' ') }} FCFA @endif
            </div>
            @endif
            @if($company?->address || $company?->city)
            <div class="party-line">{{ collect([$company->address, $company->city])->filter()->implode(', ') }}</div>
            @endif
            @if($company?->email)
            <div class="party-line">{{ $company->email }}</div>
            @endif
            <div class="party-id" style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6;">
                @if($company?->ifu)  <strong>IFU :</strong> {{ $company->ifu }}<br>@endif
                @if($company?->rccm) <strong>RCCM :</strong> {{ $company->rccm }}<br>@endif
                @if($company?->nif)  <strong>NIF :</strong> {{ $company->nif }}<br>@endif
                @if($company?->is_vat_subject)
                <strong>Assujetti TVA :</strong> Oui{{ $company?->vat_number ? ' — N° '.$company->vat_number : '' }}
                @else
                <strong>Régime :</strong> Non assujetti TVA
                @endif
            </div>
        </div>

        {{-- Acheteur --}}
        <div class="party-col right">
            <div class="party-lbl">Acheteur / Client</div>
            <div class="party-name">{{ $invoice->client?->name ?? '—' }}</div>
            @if($invoice->client?->trade_name && $invoice->client->trade_name !== $invoice->client->name)
            <div class="party-line">{{ $invoice->client->trade_name }}</div>
            @endif

            @php
                $clientAddr = $invoice->client?->addresses
                    ?->firstWhere('type', 'facturation')
                    ?? $invoice->client?->addresses?->firstWhere('is_default', true)
                    ?? $invoice->client?->addresses?->first();
            @endphp
            @if($clientAddr?->address || $clientAddr?->city)
            <div class="party-line">
                {{ implode(', ', array_filter([$clientAddr->address, $clientAddr->city, $clientAddr->country !== 'Burkina Faso' ? $clientAddr->country : null])) }}
            </div>
            @elseif($invoice->client?->address || $invoice->client?->city)
            <div class="party-line">
                {{ implode(', ', array_filter([$invoice->client->address, $invoice->client->city])) }}
            </div>
            @endif

            @if($invoice->client?->phone)
            <div class="party-line">Tél. : {{ $invoice->client->phone }}</div>
            @endif
            @if($invoice->client?->email)
            <div class="party-line">{{ $invoice->client->email }}</div>
            @endif

            <div class="party-id" style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6;">
                @if($invoice->client?->ifu)
                <strong>IFU :</strong> {{ $invoice->client->ifu }}<br>
                @endif
                @if($invoice->client?->rccm)
                <strong>RCCM :</strong> {{ $invoice->client->rccm }}<br>
                @endif
                @if($invoice->client?->tax_regime)
                <strong>Régime fiscal :</strong> {{ $invoice->client->tax_regime }}<br>
                @endif
                @if($invoice->client?->tax_division)
                <strong>Division fiscale :</strong> {{ $invoice->client->tax_division }}<br>
                @endif
                @if(!$invoice->client?->ifu && !$invoice->client?->rccm && !$invoice->client?->tax_regime)
                <span style="color:#9ca3af;">Client particulier</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Références commande / BL --}}
    @if($invoice->order_id || $invoice->delivery_note_id || $invoice->notes && str_contains($invoice->notes ?? '', 'réf'))
    <div class="refs">
        <table>
            @if($invoice->order_id && $invoice->order)
            <tr>
                <td><strong>Commande N° :</strong> {{ $invoice->order->number }}</td>
                @if($invoice->order->issued_at)
                <td><strong>Du :</strong> {{ $invoice->order->issued_at->format('d/m/Y') }}</td>
                @endif
            </tr>
            @endif
            @if($invoice->delivery_note_id && $invoice->deliveryNote)
            <tr>
                <td><strong>Bon de livraison N° :</strong> {{ $invoice->deliveryNote->number }}</td>
                @if($invoice->deliveryNote->issued_at)
                <td><strong>Du :</strong> {{ $invoice->deliveryNote->issued_at->format('d/m/Y') }}</td>
                @endif
            </tr>
            @endif
        </table>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════
         TABLEAU DES ARTICLES
    ════════════════════════════════════════════════ --}}
    @php
        // Colonnes pilotées par le paramétrage société (Documents → Colonnes affichées).
        $selected = (array) ($settings?->product_columns ?? ['reference','description','quantity','unit_price','discount','tax','total_ht','total_ttc']);
        $needDims = in_array('longueur', $selected, true) || in_array('epaisseur', $selected, true);
        $dims     = $needDims ? $invoice->productionDimensions() : [];
        $fmtDim   = fn ($v, $u) => $v !== null ? rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . ' ' . $u : '—';

        // Catalogue : key => [label, align(l|r), width, render(item)]
        $catalog = [
            'reference'   => ['Réf.',        'l', '60px', fn($it) => e($it->product?->reference ?? '—')],
            'description' => ['Désignation', 'l', '',     fn($it) => '<strong>'.e($it->description).'</strong>'
                . (($it->nb_toles > 0 && $it->metrage_par_tole > 0)
                    ? '<br><span style="font-size:8px;color:#6b7280">'
                        . number_format($it->nb_toles, 0, ',', ' ') . ' tôle(s) × '
                        . rtrim(rtrim(number_format($it->metrage_par_tole, 2, ',', ' '), '0'), ',') . ' m = '
                        . number_format($it->quantity, 2, ',', ' ') . ' ml</span>'
                    : '')],
            'longueur'    => ['Long.',       'r', '42px', fn($it) => $fmtDim($dims[$it->product_id]['length'] ?? null, 'm')],
            'epaisseur'   => ['Épais.',      'r', '42px', fn($it) => $fmtDim($dims[$it->product_id]['thickness'] ?? null, 'mm')],
            'quantity'    => ['Qté',         'r', '42px', fn($it) => number_format($it->quantity, 2, ',', ' ')],
            'unit_price'  => ['P.U. HT',     'r', '72px', fn($it) => number_format($it->unit_price, 0, ',', ' ')],
            'discount'    => ['Rem.%',       'r', '38px', fn($it) => $it->discount_percent > 0 ? number_format($it->discount_percent, 1, ',', '').'%' : '—'],
            'tax'         => ['TVA%',        'r', '36px', fn($it) => number_format($it->tax_rate_value, 0, ',', '').'%'],
            'total_ht'    => ['Montant HT',  'r', '75px', fn($it) => number_format($it->line_total_ht, 0, ',', ' ')],
            'total_ttc'   => ['Total TTC',   'r', '78px', fn($it) => number_format($it->line_total_ttc, 0, ',', ' ')],
        ];

        // Ordre d'affichage canonique, filtré par la sélection. Désignation toujours présente.
        $order = ['reference','description','longueur','epaisseur','quantity','unit_price','discount','tax','total_ht','total_ttc'];
        $columns = array_values(array_filter($order, fn($k) => $k === 'description' || in_array($k, $selected, true)));
        $colCount = count($columns) + 1; // +1 pour la colonne #
        $compact  = count($columns) > 8;
    @endphp
    <table class="items-table {{ $compact ? 'cols-extra' : '' }}">
        <thead>
            <tr>
                <th style="width:22px">#</th>
                @foreach($columns as $key)
                    <th class="{{ $catalog[$key][1] === 'r' ? 'r' : '' }}" @if($catalog[$key][2]) style="width:{{ $catalog[$key][2] }}" @endif>{{ $catalog[$key][0] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
            <tr>
                <td style="color:#9ca3af;">{{ $loop->iteration }}</td>
                @foreach($columns as $key)
                    <td class="{{ $catalog[$key][1] === 'r' ? 'r nobr' : '' }}">{!! $catalog[$key][3]($item) !!}</td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ $colCount }}" style="text-align:center; color:#9ca3af; padding:14px;">Aucune ligne de facturation</td></tr>
            @endforelse
        </tbody>
        {{-- [UI] Ligne de sous-total du tableau supprimée (redondante avec le bloc Totaux). --}}
    </table>

    {{-- ════════════════════════════════════════════════
         RÉCAP TVA + TOTAUX
    ════════════════════════════════════════════════ --}}
    <div class="bottom-wrap">

        {{-- Gauche : Observations (le Récapitulatif de TVA a été retiré — redondant avec le bloc Totaux). --}}
        <div class="tva-col">
            {{-- Notes --}}
            @if($invoice->notes)
            <div class="notes-box mt8">
                <div class="notes-lbl">Observations</div>
                <div class="notes-txt">{{ $invoice->notes }}</div>
            </div>
            @endif
        </div>

        {{-- Droite : Totaux --}}
        <div class="total-col">
            <table class="tot-table" style="border:1px solid #e5e7eb; border-radius:3px; overflow:hidden;">

                <tr>
                    <td class="lbl">Total HT</td>
                    <td class="val">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }} FCFA</td>
                </tr>

                @if($invoice->total_discount > 0)
                <tr>
                    <td class="lbl">Total remises</td>
                    <td class="val" style="color:#dc2626;">— {{ number_format($invoice->total_discount, 0, ',', ' ') }} FCFA</td>
                </tr>
                <tr>
                    <td class="lbl">Base TVA (net HT)</td>
                    <td class="val">{{ number_format($invoice->subtotal_ht - $invoice->total_discount, 0, ',', ' ') }} FCFA</td>
                </tr>
                @endif

                @foreach($tvaRecap as $rate => $data)
                <tr>
                    <td class="lbl">TVA {{ number_format($rate, 0) }}%</td>
                    <td class="val">{{ number_format($data['tva'], 0, ',', ' ') }} FCFA</td>
                </tr>
                @endforeach

                @if($invoice->global_discount_amount > 0)
                <tr>
                    <td class="lbl">Remise globale</td>
                    <td class="val" style="color:#dc2626;">— {{ number_format($invoice->global_discount_amount, 0, ',', ' ') }} FCFA</td>
                </tr>
                @endif

                <tr>
                    <td class="lbl" style="font-weight:bold;">Montant TTC</td>
                    <td class="val" style="font-weight:bold;">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</td>
                </tr>

                {{-- Retenues à la source --}}
                @if(!empty($invoice->withholding_details))
                    @foreach($invoice->withholding_details as $w)
                    <tr>
                        <td class="lbl" style="color:#b45309;">Retenue {{ $w['short_name'] ?? $w['name'] }} {{ number_format($w['rate'], 2, ',', '') }}%</td>
                        <td class="val" style="color:#b45309;">— {{ number_format($w['amount'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @endforeach
                @endif

                <tr class="grand">
                    <td class="lbl">NET À PAYER</td>
                    <td class="val">{{ number_format($invoice->net_to_pay ?: $invoice->total_ttc, 0, ',', ' ') }} FCFA</td>
                </tr>

                @if($invoice->paid_amount > 0)
                <tr class="paid">
                    <td class="lbl">Acompte versé</td>
                    <td class="val">{{ number_format($invoice->paid_amount, 0, ',', ' ') }} FCFA</td>
                </tr>
                @endif

                @if($invoice->remaining_amount > 0)
                <tr class="owed">
                    <td class="lbl">Reste à payer</td>
                    <td class="val">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</td>
                </tr>
                @elseif($invoice->paid_amount >= ($invoice->net_to_pay ?: $invoice->total_ttc) && ($invoice->net_to_pay ?: $invoice->total_ttc) > 0)
                <tr style="background:#f0fdf4;">
                    <td class="lbl" style="color:#059669; font-weight:bold;">SOLDÉE</td>
                    <td class="val" style="color:#059669;">0 FCFA</td>
                </tr>
                @endif

            </table>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════
         ARRÊTÉE LA PRÉSENTE FACTURE (mention obligatoire BF)
    ════════════════════════════════════════════════ --}}
    @if($totalWords)
    <div class="lettres">
        {{-- [UI] Libellé + montant en lettres sur UNE seule ligne --}}
        <div class="lettres-txt"><span class="lettres-lbl">Arrêtée la présente facture à la somme de :</span> {{ $totalWords }} FRANCS CFA ({{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA)</div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════
         MODE DE RÈGLEMENT + COORDONNÉES BANCAIRES
    ════════════════════════════════════════════════ --}}
    @if($bank || $settings?->payment_instructions)
    <div class="reglement">
        <div class="reglement-lbl">Modalités de règlement</div>

        @if($settings?->payment_instructions)
        <div class="reglement-row">{{ $settings->payment_instructions }}</div>
        @endif

        @if($bank)
        <div class="reglement-row" style="margin-top:4px;">
            <strong>Virement bancaire :</strong>
            <strong>{{ $bank->bank_name }}</strong>
            @if($bank->account_number) — Compte N° <strong>{{ $bank->account_number }}</strong> @endif
            @if($bank->iban) — IBAN : <strong>{{ $bank->iban }}</strong> @endif
            @if($bank->swift_code) — SWIFT : <strong>{{ $bank->swift_code }}</strong> @endif
        </div>
        @endif

        {{-- Mobile Money — spécifique Burkina Faso --}}
        @if($company?->orange_money_number ?? $settings?->orange_money_number ?? null)
        @php $om = $company->orange_money_number ?? $settings->orange_money_number ?? null; @endphp
        <div class="reglement-row">
            <span class="mobile-money">Orange Money</span>
            <strong>{{ $om }}</strong>
        </div>
        @endif

        @if($company?->moov_money_number ?? $settings?->moov_money_number ?? null)
        @php $mm = $company->moov_money_number ?? $settings->moov_money_number ?? null; @endphp
        <div class="reglement-row">
            <span class="mobile-money moov">Moov Money</span>
            <strong>{{ $mm }}</strong>
        </div>
        @endif
    </div>
    @endif

    {{-- ════════════════════════════════════════════════
         SIGNATURES : Émetteur + Espace client
    ════════════════════════════════════════════════ --}}
    <div class="sig-row">

        {{-- Espace "Bon pour accord" client --}}
        <div class="sig-cell" style="text-align:left; padding-left:0; padding-right:20px;">
            <div class="client-sig">
                <div class="client-sig-lbl">Bon pour accord — Signature et cachet du Client</div>
                <br><br>
            </div>
            <div style="font-size:7.5px; color:#9ca3af; text-align:center; margin-top:4px;">
                {{ $invoice->client?->name ?? '' }}
            </div>
        </div>

        {{-- Signature émetteur --}}
        <div class="sig-cell" style="padding-right:0;">
            @if($stampBase64)
            <img src="{{ $stampBase64 }}" class="sig-img" alt="Cachet" style="margin-bottom:2px;">
            @endif
            @if($sigBase64)
            <img src="{{ $sigBase64 }}" class="sig-img" alt="Signature">
            @endif
            <div class="sig-box">
                <strong>{{ $settings?->signature_name ?? ($company?->trade_name ?? $company?->name ?? '') }}</strong>
                {{ $settings?->signature_title ?? 'Le Directeur / Le Responsable' }}
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════
         CGV & MENTIONS LÉGALES
    ════════════════════════════════════════════════ --}}
    <div class="cgv">
        <div class="cgv-lbl">Conditions générales & Mentions légales</div>
        @if($settings?->terms_conditions)
        <div class="cgv-txt">{{ $settings->terms_conditions }}</div>
        @endif
        <div class="penalites">{{ $settings?->penalty_mentions
            ?: 'En cas de retard de paiement, des pénalités de retard au taux de 1,5% par mois seront appliquées de plein droit, sans mise en demeure préalable, conformément à la réglementation OHADA en vigueur. Une indemnité forfaitaire pour frais de recouvrement de 40 000 FCFA sera due dès le premier jour de retard. Tout paiement doit être accompagné de la présente facture.' }}</div>
    </div>

    {{-- ════════════════════════════════════════════════
         QR CODE DE VÉRIFICATION
    ════════════════════════════════════════════════ --}}
    @if($qrDataUri)
    <div class="qr-section">
        <div class="qr-img-cell">
            <img src="{{ $qrDataUri }}" alt="QR Vérification">
        </div>
        <div class="qr-text-cell">
            <div class="qr-title">Vérification d'authenticité</div>
            <div class="qr-desc">
                Scannez ce QR code pour vérifier<br>
                l'authenticité de cette facture.<br>
                Le lien est sécurisé par signature cryptographique.
            </div>
            <div class="qr-ref">
                {{ $invoice->number }}
                @if($invoice->issued_at) · {{ $invoice->issued_at->format('d/m/Y') }}@endif
                @if($company?->ifu) · IFU : {{ $company->ifu }}@endif
            </div>
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════
         PIED DE PAGE
    ════════════════════════════════════════════════ --}}
    <div class="footer">
        @if($settings?->footer_text)
            {{ $settings->footer_text }}
        @else
            <strong>{{ $company?->name ?? 'OA METAL INDUSTRIE' }}</strong>
            @if($company?->legal_form) — {{ $company->legal_form }} @endif
            @if($company?->address) | {{ $company->address }}@if($company->city), {{ $company->city }}@endif @endif
            <br>
            @if($company?->phone) Tél. : {{ $company->phone }} @endif
            @if($company?->email) | {{ $company->email }} @endif
            @if($company?->ifu) | <strong>IFU :</strong> {{ $company->ifu }} @endif
            @if($company?->rccm) | <strong>RCCM :</strong> {{ $company->rccm }} @endif
            @if($company?->nif) | <strong>NIF :</strong> {{ $company->nif }} @endif
        @endif
        <br>
        <span style="color:#d1d5db;">Document généré le {{ now()->format('d/m/Y à H:i') }}</span>
    </div>

</div>
</body>
</html>
