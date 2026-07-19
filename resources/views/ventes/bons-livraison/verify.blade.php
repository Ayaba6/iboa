<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification BL {{ $deliveryNote->number }} — {{ config('app.name') }}</title>
    {{-- [Typo globale] Page publique autonome : pile système sans CDN --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

        .page { max-width: 680px; margin: 0 auto; padding: 32px 16px 64px; }

        .header { text-align: center; margin-bottom: 28px; }
        .header-logo { font-size: 1.125rem; font-weight: 700; color: #0f766e; letter-spacing: -.02em; margin-bottom: 6px; }
        .header-sub  { font-size: .8125rem; color: #64748b; }

        .auth-banner { border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px; }
        .auth-banner.valid   { background: #f0fdf4; border: 1.5px solid #86efac; }
        .auth-banner.invalid { background: #fff1f2; border: 1.5px solid #fca5a5; }
        .auth-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .auth-icon.valid   { background: #dcfce7; }
        .auth-icon.invalid { background: #fee2e2; }
        .auth-icon svg { width: 24px; height: 24px; }
        .auth-title { font-size: 1.125rem; font-weight: 700; margin-bottom: 2px; }
        .auth-title.valid   { color: #15803d; }
        .auth-title.invalid { color: #be123c; }
        .auth-desc { font-size: .8125rem; color: #475569; line-height: 1.5; }

        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 16px; }
        .card-header { padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
                       font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .card-body { padding: 20px; }

        .doc-number { font-size: 1.5rem; font-weight: 800; color: #1e293b; font-family: monospace; margin-bottom: 4px; }
        .doc-meta   { font-size: .875rem; color: #64748b; line-height: 1.8; }

        .status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: .75rem; font-weight: 600; margin-top: 8px; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; }
        .status.valide        { background: #dcfce7; color: #15803d; }
        .status.valide .status-dot    { background: #16a34a; }
        .status.brouillon     { background: #f1f5f9; color: #64748b; }
        .status.brouillon .status-dot { background: #94a3b8; }
        .status.annule        { background: #fee2e2; color: #dc2626; }
        .status.annule .status-dot    { background: #dc2626; }

        .dl { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .dl dt { font-size: .75rem; font-weight: 500; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
        .dl dd { font-size: .9375rem; font-weight: 600; color: #1e293b; }

        .items-list { border-top: 1px solid #f1f5f9; margin-top: 16px; padding-top: 12px; }
        .item-row { display: flex; justify-content: space-between; align-items: center;
                    padding: 7px 0; font-size: .875rem; border-bottom: 1px solid #f8fafc; }
        .item-row:last-child { border-bottom: none; }
        .item-name { color: #1e293b; font-weight: 500; }
        .item-qty  { color: #0f766e; font-weight: 700; font-family: monospace; }

        .ifu-tag { display: inline-block; background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e;
                   font-size: .6875rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; font-family: monospace; }

        .footer { text-align: center; font-size: .75rem; color: #94a3b8; margin-top: 32px; line-height: 1.7; }
        .footer strong { color: #64748b; }

        @media (max-width: 480px) {
            .dl { grid-template-columns: 1fr; }
            .auth-banner { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        @if($company?->trade_name || $company?->name)
        <div class="header-logo">{{ $company->trade_name ?? $company->name }}</div>
        @endif
        <div class="header-sub">Système de vérification d'authenticité des bons de livraison</div>
    </div>

    @php
        $isCancelled = $deliveryNote->status === 'annule';
        $statusLabels = [
            'brouillon' => 'Brouillon',
            'valide'    => 'Validé',
            'annule'    => 'Annulé',
        ];
    @endphp

    {{-- Bandeau d'authenticité --}}
    @if($isCancelled)
    <div class="auth-banner invalid">
        <div class="auth-icon invalid">
            <svg fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>
        <div>
            <div class="auth-title invalid">Bon de livraison annulé</div>
            <div class="auth-desc">
                Ce document a été émis par <strong>{{ $company?->name }}</strong>
                mais est marqué <strong>ANNULÉ</strong> dans notre système.
            </div>
        </div>
    </div>
    @else
    <div class="auth-banner valid">
        <div class="auth-icon valid">
            <svg fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <div class="auth-title valid">Document authentique</div>
            <div class="auth-desc">
                Ce bon de livraison est authentique et a bien été émis par
                <strong>{{ $company?->trade_name ?? $company?->name }}</strong>.
                @if($company?->ifu) IFU émetteur : <span class="ifu-tag">{{ $company->ifu }}</span> @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Informations BL --}}
    <div class="card">
        <div class="card-header">Détails du bon de livraison</div>
        <div class="card-body">
            <div class="doc-number">{{ $deliveryNote->number }}</div>
            <div class="doc-meta">
                Émis le {{ $deliveryNote->issued_at?->format('d/m/Y') ?? '—' }}
                @if($deliveryNote->order) · Commande : {{ $deliveryNote->order->number }} @endif
                @if($deliveryNote->warehouse) · Entrepôt : {{ $deliveryNote->warehouse->name }} @endif
            </div>
            <span class="status {{ $deliveryNote->status }}">
                <span class="status-dot"></span>
                {{ $statusLabels[$deliveryNote->status] ?? $deliveryNote->status }}
            </span>

            {{-- Lignes --}}
            @if($deliveryNote->items->count())
            <div class="items-list">
                @foreach($deliveryNote->items as $item)
                <div class="item-row">
                    <span class="item-name">{{ $item->description }}</span>
                    <span class="item-qty">
                        {{ number_format($item->quantity_delivered ?? $item->quantity, 2, ',', ' ') }}
                        {{ $item->product?->unit?->abbreviation ?? 'pcs' }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Expéditeur / Destinataire --}}
    <div class="card">
        <div class="card-header">Expéditeur &amp; Destinataire</div>
        <div class="card-body">
            <div class="dl">
                <div>
                    <dt>Expéditeur</dt>
                    <dd>{{ $company?->trade_name ?? $company?->name ?? '—' }}</dd>
                    @if($company?->ifu)
                    <dd style="margin-top:4px;"><span class="ifu-tag">IFU {{ $company->ifu }}</span></dd>
                    @endif
                    @if($company?->rccm)
                    <dd style="margin-top:2px; font-size:.8rem; color:#64748b; font-weight:400;">RCCM : {{ $company->rccm }}</dd>
                    @endif
                </div>
                <div>
                    <dt>Destinataire</dt>
                    <dd>{{ $deliveryNote->client?->name ?? '—' }}</dd>
                    @if($deliveryNote->client?->ifu)
                    <dd style="margin-top:4px;"><span class="ifu-tag">IFU {{ $deliveryNote->client->ifu }}</span></dd>
                    @endif
                    @if($deliveryNote->client?->rccm)
                    <dd style="margin-top:2px; font-size:.8rem; color:#64748b; font-weight:400;">RCCM : {{ $deliveryNote->client->rccm }}</dd>
                    @endif
                </div>
                <div>
                    <dt>Nombre de lignes</dt>
                    <dd>{{ $deliveryNote->items->count() }} article(s)</dd>
                </div>
                <div>
                    <dt>Vérification le</dt>
                    <dd>{{ now()->format('d/m/Y à H:i') }}</dd>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <strong>{{ $company?->trade_name ?? $company?->name ?? config('app.name') }}</strong><br>
        @if($company?->address) {{ $company->address }}@if($company->city), {{ $company->city }}@endif<br> @endif
        @if($company?->ifu) IFU : {{ $company->ifu }} @endif
        @if($company?->rccm) · RCCM : {{ $company->rccm }} @endif
        <br><br>
        Ce lien de vérification est sécurisé et à usage unique. Il a été généré automatiquement
        lors de la création du PDF du bon de livraison.
    </div>

</div>
</body>
</html>
