<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification facture {{ $invoice->number }} — {{ config('app.name') }}</title>
    {{-- [Typo globale] Page publique autonome (pas de bundle Vite) : pile système
         sans CDN — l'ERP ne doit pas dépendre d'Internet pour ses polices. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

        .page { max-width: 680px; margin: 0 auto; padding: 32px 16px 64px; }

        /* Header */
        .header { text-align: center; margin-bottom: 28px; }
        .header-logo { font-size: 1.125rem; font-weight: 700; color: #1d4ed8; letter-spacing: -.02em; margin-bottom: 6px; }
        .header-sub { font-size: .8125rem; color: #64748b; }

        /* Badge authentique / invalide */
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

        /* Card */
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 16px; }
        .card-header { padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
                       font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .card-body { padding: 20px; }

        /* Invoice header */
        .inv-number { font-size: 1.5rem; font-weight: 800; color: #1e293b; font-family: monospace; margin-bottom: 4px; }
        .inv-meta   { font-size: .875rem; color: #64748b; line-height: 1.8; }

        /* Status badge */
        .status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: .75rem; font-weight: 600; margin-top: 8px; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; }
        .status.payee               { background: #dcfce7; color: #15803d; }
        .status.payee .status-dot   { background: #16a34a; }
        .status.partiellement_payee { background: #fef9c3; color: #854d0e; }
        .status.partiellement_payee .status-dot { background: #ca8a04; }
        .status.emise               { background: #dbeafe; color: #1d4ed8; }
        .status.emise .status-dot   { background: #2563eb; }
        .status.en_retard           { background: #fee2e2; color: #dc2626; }
        .status.en_retard .status-dot { background: #dc2626; }
        .status.annulee             { background: #f1f5f9; color: #64748b; }
        .status.annulee .status-dot { background: #94a3b8; }
        .status.brouillon           { background: #f1f5f9; color: #64748b; }
        .status.brouillon .status-dot { background: #94a3b8; }

        /* DL grid */
        .dl { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .dl dt { font-size: .75rem; font-weight: 500; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
        .dl dd { font-size: .9375rem; font-weight: 600; color: #1e293b; }
        .dl dd.mono { font-family: monospace; }
        .dl dd.green { color: #15803d; }
        .dl dd.red   { color: #dc2626; }

        /* Totals */
        .totals { border-top: 1px solid #f1f5f9; margin-top: 16px; padding-top: 16px; }
        .total-row { display: flex; justify-content: space-between; align-items: center;
                     padding: 6px 0; font-size: .9rem; border-bottom: 1px solid #f8fafc; }
        .total-row:last-child { border-bottom: none; }
        .total-row .lbl { color: #64748b; }
        .total-row .val { font-weight: 600; color: #1e293b; font-family: monospace; }
        .total-row.grand { background: #1d4ed8; border-radius: 8px; padding: 10px 14px; margin-top: 8px; }
        .total-row.grand .lbl, .total-row.grand .val { color: #fff; font-weight: 700; font-size: 1rem; }

        /* Footer */
        .footer { text-align: center; font-size: .75rem; color: #94a3b8; margin-top: 32px; line-height: 1.7; }
        .footer strong { color: #64748b; }

        /* IFU tag */
        .ifu-tag { display: inline-block; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8;
                   font-size: .6875rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; font-family: monospace; }

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
        <div class="header-sub">Système de vérification d'authenticité des factures</div>
    </div>

    {{-- Bandeau d'authenticité --}}
    @php
        $isValid = in_array($invoice->status, ['emise','envoyee','partiellement_payee','payee','en_retard']);
        $isCancelled = $invoice->status === 'annulee';

        $statusLabels = [
            'brouillon'           => 'Brouillon',
            'emise'               => 'Émise',
            'envoyee'             => 'Envoyée',
            'partiellement_payee' => 'Partiellement payée',
            'payee'               => 'Payée',
            'en_retard'           => 'En retard',
            'annulee'             => 'Annulée',
        ];
    @endphp

    @if($isCancelled)
    <div class="auth-banner invalid">
        <div class="auth-icon invalid">
            <svg fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>
        <div>
            <div class="auth-title invalid">Facture annulée</div>
            <div class="auth-desc">
                Cette facture a été émise par <strong>{{ $company?->name }}</strong>
                mais est marquée <strong>ANNULÉE</strong> dans notre système.
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
                Cette facture est authentique et a bien été émise par
                <strong>{{ $company?->trade_name ?? $company?->name }}</strong>.
                IFU émetteur : <span class="ifu-tag">{{ $company?->ifu ?? 'N/A' }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Informations facture --}}
    <div class="card">
        <div class="card-header">Détails de la facture</div>
        <div class="card-body">
            <div class="inv-number">{{ $invoice->number }}</div>
            <div class="inv-meta">
                Émise le {{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}
                @if($invoice->due_at) · Échéance : {{ $invoice->due_at->format('d/m/Y') }} @endif
            </div>
            <span class="status {{ $invoice->status }}">
                <span class="status-dot"></span>
                {{ $statusLabels[$invoice->status] ?? $invoice->status }}
            </span>

            <div class="totals">
                <div class="total-row">
                    <span class="lbl">Total HT</span>
                    <span class="val">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="total-row">
                    <span class="lbl">TVA</span>
                    <span class="val">{{ number_format($invoice->total_tax, 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="total-row grand">
                    <span class="lbl">TOTAL TTC</span>
                    <span class="val">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
                </div>
                @if($invoice->paid_amount > 0)
                <div class="total-row" style="margin-top:8px;">
                    <span class="lbl">Montant payé</span>
                    <span class="val green">{{ number_format($invoice->paid_amount, 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
                @if($invoice->remaining_amount > 0)
                <div class="total-row">
                    <span class="lbl">Reste à payer</span>
                    <span class="val red">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="card">
        <div class="card-header">Émetteur &amp; Destinataire</div>
        <div class="card-body">
            <div class="dl">
                <div>
                    <dt>Émetteur</dt>
                    <dd>{{ $company?->trade_name ?? $company?->name ?? '—' }}</dd>
                    @if($company?->ifu)
                    <dd style="margin-top:4px;"><span class="ifu-tag">IFU {{ $company->ifu }}</span></dd>
                    @endif
                    @if($company?->rccm)
                    <dd style="margin-top:2px; font-size:.8rem; color:#64748b; font-weight:400;">RCCM : {{ $company->rccm }}</dd>
                    @endif
                </div>
                <div>
                    <dt>Client</dt>
                    <dd>{{ $invoice->client?->name ?? '—' }}</dd>
                    @if($invoice->client?->ifu)
                    <dd style="margin-top:4px;"><span class="ifu-tag">IFU {{ $invoice->client->ifu }}</span></dd>
                    @endif
                    @if($invoice->client?->rccm)
                    <dd style="margin-top:2px; font-size:.8rem; color:#64748b; font-weight:400;">RCCM : {{ $invoice->client->rccm }}</dd>
                    @endif
                </div>
                <div>
                    <dt>Nombre de lignes</dt>
                    <dd>{{ $invoice->items->count() }} article(s)</dd>
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
        lors de la création du PDF de la facture.
    </div>

</div>
</body>
</html>
