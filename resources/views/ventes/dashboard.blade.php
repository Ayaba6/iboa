@extends('layouts.erp')
@section('title', 'Tableau de bord Ventes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Ventes</span>
@endsection

@section('content')
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    // [Audit] Objectif dérivé du CA N-1 +10%. Sans historique (N-1 = 0) et sans
    // cible commerciale saisie, il n'existe PAS d'objectif : afficher un faux
    // « 100 % » avec barre pleine induisait en erreur. On ne montre l'objectif
    // et la progression que lorsqu'une base réelle existe.
    $hasTarget = (float) ($kpis['ca_prev_year'] ?? 0) > 0;
    $objCa     = $hasTarget ? (float) $kpis['ca_prev_year'] * 1.1 : 0;
    $pctCa     = $hasTarget ? min(100, round($kpis['ca_year'] / max($objCa, 1) * 100, 1)) : null;
    // Devis en cours (brouillon + envoyé) depuis le pipeline.
    $devisEnCours = ($pipeline['brouillon']['count'] ?? 0) + ($pipeline['envoye']['count'] ?? 0);
    $devisMontant = ($pipeline['brouillon']['total'] ?? 0) + ($pipeline['envoye']['total'] ?? 0);
    $cmdALivrer   = array_sum($deliveries);
    $objCmd       = $hasTarget ? max((float) $ordersValue, $objCa) * 1.0 : 0;
    $pctCmd       = $hasTarget ? min(100, round($ordersValue / max($objCmd, 1) * 100, 1)) : null;
@endphp

<div class="space-y-4">

    <x-sales.module-nav />

    {{-- ── Entête + onglets ─────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div>
            <h1 class="text-[20px] font-bold text-gray-900 leading-tight">Tableau de bord ventes</h1>
            {{-- [UI] Barre de liens, plus de faux onglets.
                 « Prévisions » et « Activité » pointaient vers la MÊME page :
                 deux libellés, une destination. L'entrée en double est retirée,
                 et l'apparence d'onglets d'un même écran est abandonnée — cinq
                 de ces entrées sur six quittent la page. --}}
            <p class="text-[12px] text-gray-500 mt-0.5 tabular-nums">
                Exercice en cours · du {{ now()->startOfYear()->format('d/m/Y') }} au {{ now()->format('d/m/Y') }}
            </p>
            <div class="flex flex-wrap gap-3 mt-2 text-[12px] font-medium">
                <a href="{{ route('ventes.commandes.index') }}" class="text-gray-500 hover:text-emerald-700 hover:underline">Activité</a>
                <a href="{{ route('reports.ca') }}" class="text-gray-500 hover:text-emerald-700 hover:underline">Performance</a>
                <a href="{{ route('ventes.devis.index') }}" class="text-gray-500 hover:text-emerald-700 hover:underline">Pipeline</a>
                <a href="{{ route('reports.margins') }}" class="text-gray-500 hover:text-emerald-700 hover:underline">Analyse des marges</a>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ventes.devis.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[13px] font-medium px-3 py-1.5 rounded-[4px]">Devis</a>
            <a href="{{ route('ventes.commandes.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[13px] font-medium px-3 py-1.5 rounded-[4px]">Commandes</a>
            @can('quotes.create')
            <a href="{{ route('ventes.devis.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px]">+ Devis</a>
            @endcan
        </div>
    </div>

    {{-- [UI] La barre de « filtres » a été SUPPRIMÉE.
         Ses cinq contrôles étaient des <div> en lecture seule : ils avaient
         l'apparence de champs, occupaient une bande horizontale complète et ne
         filtraient rien. Une fausse affordance coûte plus cher qu'une fonction
         absente — l'utilisateur essaie, échoue, et doute du reste de l'écran.
         La période couverte est indiquée dans l'en-tête. --}}

    {{-- [UI] GRILLE UNIQUE pour tout le tableau de bord.
         ────────────────────────────────────────────────────────────────────
         Auparavant quatre grilles distinctes se succédaient :
             grid-cols-2 lg:grid-cols-3 xl:grid-cols-6   (KPI)
             lg:grid-cols-12 avec spans 5 + 4 + 3        (rangée 1)
             lg:grid-cols-12 avec spans 3 + 3 + 3 + 3    (rangée 2)
             lg:grid-cols-2                              (rangée 3)
         Aucune colonne ne s'alignait d'une rangée à l'autre : la rangée 1
         coupait à 5|9, la rangée 2 à 3|6|9, les KPI à 2|4. L'œil ne trouvait
         aucune ligne de fuite verticale — c'était la cause principale du
         désordre, avant même la typographie.

         Désormais UNE grille de 12 colonnes contient toutes les cartes, et
         toutes les largeurs retombent sur les mêmes frontières :
             KPI      col-span-2   → frontières 2 · 4 · 6 · 8 · 10
             demi     col-span-6   → frontière  6
             tiers    col-span-4   → frontières 4 · 8
         Les frontières {4, 6, 8} des contenus sont incluses dans celles des
         KPI : chaque bord de carte tombe sur un bord de carte du dessus.

         Un SEUL point de rupture (`lg`) et un SEUL espacement (`gap-4`),
         horizontal comme vertical — l'ancien `space-y-3` serrait les rangées
         (12 px) plus que les colonnes (16 px), ce qui cassait la lecture en
         grille. --}}
    <div class="grid grid-cols-2 lg:grid-cols-12 gap-4">
        {{-- CA HT + objectif --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Chiffre d'affaires HT</p>
            <p class="mt-1 text-[18px] font-bold leading-none tabular-nums text-emerald-700">{{ $fmt($kpis['ca_year']) }} <span class="text-[11px] text-gray-400">FCFA</span></p>
            @if($hasTarget)
            <p class="text-[11px] text-gray-400 mt-1">Objectif (N-1 +10%) : {{ $fmt($objCa) }} FCFA</p>
            <div class="w-full bg-gray-100 rounded h-1.5 mt-1"><div class="h-1.5 rounded bg-emerald-500" style="width: {{ $pctCa }}%"></div></div>
            <p class="text-[11px] font-semibold text-emerald-600 mt-0.5 tabular-nums">{{ $pctCa }} %</p>
            @else
            <p class="text-[11px] text-gray-400 mt-1">Objectif non défini <span class="text-gray-300">(pas d'historique N-1)</span></p>
            @endif
        </div>
        {{-- Commandes HT + objectif --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Commandes HT</p>
            <p class="mt-1 text-[18px] font-bold leading-none tabular-nums text-gray-900">{{ $fmt($ordersValue) }} <span class="text-[11px] text-gray-400">FCFA</span></p>
            @if($hasTarget)
            <p class="text-[11px] text-gray-400 mt-1">Objectif (N-1 +10%) : {{ $fmt($objCmd) }} FCFA</p>
            <div class="w-full bg-gray-100 rounded h-1.5 mt-1"><div class="h-1.5 rounded bg-sky-500" style="width: {{ $pctCmd }}%"></div></div>
            <p class="text-[11px] font-semibold text-sky-600 mt-0.5 tabular-nums">{{ $pctCmd }} %</p>
            @else
            <p class="text-[11px] text-gray-400 mt-1">Objectif non défini <span class="text-gray-300">(pas d'historique N-1)</span></p>
            @endif
        </div>
        {{-- Marge brute + sparkline --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Marge brute</p>
            <p class="mt-1 text-[18px] font-bold leading-none tabular-nums text-gray-900">{{ $fmt($margin['marge']) }} <span class="text-[11px] text-gray-400">FCFA</span></p>
            <p class="text-[11px] text-gray-400 mt-1">Taux : <span class="font-semibold {{ $margin['taux'] >= 25 ? 'text-emerald-600' : 'text-amber-600' }}">{{ number_format($margin['taux'], 2, ',', ' ') }} %</span></p>
            <div id="spark-marge" class="mt-1 h-8"></div>
            <a href="{{ route('ventes.marges') }}" class="mt-1 inline-block text-[11px] font-medium text-emerald-700 hover:underline">Par commercial / site →</a>
        </div>
        {{-- Devis en cours --}}
        <a href="{{ route('ventes.devis.index') }}" class="lg:col-span-2 bg-white rounded-[4px] border border-gray-200 hover:border-gray-300 p-4 flex items-start justify-between gap-2 transition-colors">
            <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Devis en cours</p>
                <p class="mt-1 text-[18px] font-bold leading-none tabular-nums text-gray-900">{{ $devisEnCours }}</p>
                <p class="text-[11px] text-gray-400 mt-1">Montant : {{ $fmt($devisMontant) }} FCFA</p>
            </div>
            <span class="w-9 h-9 rounded-full bg-cyan-50 text-cyan-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </span>
        </a>
        {{-- Commandes à livrer --}}
        <a href="{{ route('ventes.commandes.index') }}" class="lg:col-span-2 bg-white rounded-[4px] border border-gray-200 hover:border-gray-300 p-4 flex items-start justify-between gap-2 transition-colors">
            <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Commandes à livrer</p>
                <p class="mt-1 text-[18px] font-bold leading-none tabular-nums text-gray-900">{{ $cmdALivrer }}</p>
                <p class="text-[11px] text-gray-400 mt-1">Dont {{ $deliveries['en_retard'] }} en retard</p>
            </div>
            <span class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0m10 0a2 2 0 104 0"/></svg>
            </span>
        </a>
        {{-- Factures impayées --}}
        <a href="{{ route('ventes.factures.index') }}" class="lg:col-span-2 bg-white rounded-[4px] border {{ $alerts['invoices_unpaid'] > 0 ? 'border-orange-200' : 'border-gray-200' }} hover:border-orange-300 p-4 flex items-start justify-between gap-2 transition-colors">
            <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Factures impayées</p>
                <p class="mt-1 text-[18px] font-bold leading-none tabular-nums text-gray-900">{{ $alerts['invoices_unpaid'] }}</p>
                <p class="text-[11px] text-gray-400 mt-1">Montant : {{ $fmt($kpis['outstanding']) }} FCFA</p>
            </div>
            <span class="w-9 h-9 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </span>
        </a>

    {{-- ── Validation interne (si en attente) ───────────────────────────────── --}}
    @if($workflowKpis['total_pending'] > 0)
        <div class="col-span-2 lg:col-span-12 rounded-[4px] border border-yellow-300 bg-yellow-50 px-4 py-2 flex items-center gap-3 text-[13px]">
        <span class="font-semibold text-yellow-800">Validation interne :</span>
        <span class="text-yellow-700">{{ $workflowKpis['total_pending'] }} document(s) en attente</span>
        <a href="{{ route('ventes.bons-livraison.index', ['status' => 'en_attente_validation']) }}" class="ml-auto text-yellow-800 font-semibold hover:underline">Traiter →</a>
    </div>
    @endif

    {{-- Analyse : évolution et répartition du CA --}}
        <div class="col-span-2 lg:col-span-6 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-2">Évolution du chiffre d'affaires (HT)</h2>
            <div id="chart-ca-evolution" class="h-[260px]"></div>
        </div>
        <div class="col-span-2 lg:col-span-6 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-2">Répartition du CA par famille d'articles</h2>
            @if($caByFamily->isEmpty())
                <div class="h-[260px] flex items-center justify-center text-gray-400 text-[13px]">Aucune vente.</div>
            @else
                <div id="chart-famille" class="min-h-[260px]"></div>
            @endif
        </div>

    {{-- Répartitions : statuts, échéances, pipeline --}}
        {{-- [UI pro] Donuts : légende SOUS le graphique (position right dans une
             colonne de 3/12 écrasait le donut à ~60px et tronquait la légende). --}}
        <div class="col-span-2 lg:col-span-4 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-2">Commandes par statut</h2>
            @if($ordersStatus->isEmpty())
                <div class="h-[260px] flex items-center justify-center text-gray-400 text-[13px]">Aucune commande.</div>
            @else
                <div id="chart-statut" class="min-h-[260px]"></div>
            @endif
        </div>
        <div class="col-span-2 lg:col-span-4 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-2">Commandes à livrer par échéance</h2>
            <div id="chart-echeance" class="min-h-[260px]"></div>
        </div>
        <div class="col-span-2 lg:col-span-4 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-2">Devis par statut (pipeline)</h2>
            <div id="chart-pipeline" class="min-h-[260px]"></div>
        </div>
        <div class="col-span-2 lg:col-span-6 bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]"><h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Top 5 clients (CA HT)</h2></div>
            <table class="w-full text-[13px]">
                <tbody class="divide-y divide-gray-50">
                    @forelse($topClients as $i => $c)
                    <tr>
                        <td class="px-3 py-2 text-gray-400 tabular-nums w-6">{{ $i + 1 }}</td>
                        <td class="px-2 py-2">{{ $c->name }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ $fmt($c->total_ht) }}</td>
                    </tr>
                    @empty
                    <tr><td class="px-3 py-6 text-center text-gray-400 text-[13px]">Aucune vente.</td></tr>
                    @endforelse
                </tbody>
                @if($topClients->isNotEmpty())
                <tfoot><tr class="border-t-2 border-gray-200 bg-gray-50/60">
                    <td class="px-3 py-2 font-bold" colspan="2">Total</td>
                    <td class="px-3 py-2 text-right tabular-nums font-bold whitespace-nowrap">{{ $fmt($topClients->sum('total_ht')) }}</td>
                </tr></tfoot>
                @endif
            </table>
        </div>
        <div class="col-span-2 lg:col-span-6 bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]"><h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Top 5 articles (CA HT)</h2></div>
            <table class="w-full text-[13px]">
                <tbody class="divide-y divide-gray-50">
                    @forelse($topProducts as $i => $p)
                    <tr>
                        <td class="px-3 py-2 text-gray-400 tabular-nums w-6">{{ $i + 1 }}</td>
                        <td class="px-2 py-2">{{ $p->name }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ $fmt($p->total_ht) }}</td>
                    </tr>
                    @empty
                    <tr><td class="px-3 py-6 text-center text-gray-400 text-[13px]">Aucune vente.</td></tr>
                    @endforelse
                </tbody>
                @if($topProducts->isNotEmpty())
                <tfoot><tr class="border-t-2 border-gray-200 bg-gray-50/60">
                    <td class="px-3 py-2 font-bold" colspan="2">Total</td>
                    <td class="px-3 py-2 text-right tabular-nums font-bold whitespace-nowrap">{{ $fmt($topProducts->sum('total_ht')) }}</td>
                </tr></tfoot>
                @endif
            </table>
        </div>

    {{-- À traiter : activités et alertes --}}
        <div class="col-span-2 lg:col-span-6 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-3">Activités commerciales <span class="text-[11px] font-normal text-gray-400">— 30 jours</span></h2>
            @php
                // Classes complètes (pas d'interpolation : Tailwind JIT purgerait)
                $actIcons = [
                    'Visites clients' => ['bg-emerald-50 text-emerald-600', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    'Appels'          => ['bg-sky-50 text-sky-600', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                    'Emails'          => ['bg-amber-50 text-amber-600', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    'Rendez-vous'     => ['bg-violet-50 text-violet-600', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    'Tâches en cours' => ['bg-teal-50 text-teal-600', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                ];
                $actVals = ['Visites clients' => $activities['visites'], 'Appels' => $activities['appels'], 'Emails' => $activities['emails'], 'Rendez-vous' => $activities['rdv'], 'Tâches en cours' => $activities['taches']];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                @foreach($actVals as $lab => $val)
                @php [$cls, $path] = $actIcons[$lab]; @endphp
                <div class="flex items-center gap-2 p-2">
                    <span class="w-9 h-9 rounded-full {{ $cls }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/></svg>
                    </span>
                    <div>
                        <div class="text-[11px] text-gray-500 leading-tight">{{ $lab }}</div>
                        <div class="text-[15px] font-bold tabular-nums text-gray-800 leading-tight">{{ $val }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="col-span-2 lg:col-span-6 bg-white rounded-[4px] border border-gray-200 p-4">
            <h2 class="text-[13px] font-bold text-gray-800 mb-3">Alertes</h2>
            <ul class="space-y-2 text-[13px]">
                <li class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-red-500"></span>{{ $alerts['orders_late'] }} commande(s) en retard de livraison</li>
                <li class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ $alerts['invoices_unpaid'] }} facture(s) impayée(s)</li>
                <li class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-500"></span>Stock faible sur {{ $alerts['low_stock'] }} article(s)</li>
            </ul>
            <div class="mt-3 text-right">
                <a href="{{ route('ventes.factures.index') }}" class="text-[12px] font-semibold text-emerald-700 hover:underline">Voir toutes les alertes →</a>
            </div>
        </div>
    </div>

    {{-- ── Barre de contexte X3 ─────────────────────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">CA HT {{ now()->year }} : <span class="text-white font-semibold tabular-nums">{{ $fmt($kpis['ca_year']) }} FCFA</span></span>
        <span class="border-l border-white/10 pl-6">Marge : <span class="text-white font-semibold tabular-nums">{{ number_format($margin['taux'], 1, ',', ' ') }} %</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection

@push('scripts')
<script>
(window.__pendingApex = window.__pendingApex || []).push(function () {
    const fmt   = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v || 0);
    const green = ['#059669', '#10b981', '#34d399', '#6ee7b7', '#a7f3d0', '#d1fae5'];
    const multi = ['#059669', '#2563eb', '#f59e0b', '#8b5cf6', '#14b8a6', '#9ca3af'];
    window.__turboCleanups = window.__turboCleanups || [];
    const mount = (sel, opt) => { const el = document.querySelector(sel); if (!el) return; const c = new ApexCharts(el, opt); c.render(); window.__turboCleanups.push(() => { try { c.destroy(); } catch (e) {} }); };

    // Évolution CA — barres CA HT + barres N-1 + courbe de tendance (maquette X3)
    mount('#chart-ca-evolution', {
        chart: { height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'CA HT', type: 'column', data: @json($caComparison['current']) },
            { name: 'CA HT année précédente', type: 'column', data: @json($caComparison['previous']) },
            { name: 'Tendance', type: 'line', data: @json($caComparison['current']) },
        ],
        xaxis: { categories: @json($caComparison['labels']), labels: { style: { fontSize: '10px', colors: '#94a3b8' } } },
        yaxis: { labels: { style: { fontSize: '10px', colors: '#94a3b8' }, formatter: fmt } },
        colors: ['#059669', '#cbd5e1', '#34d399'],
        stroke: { width: [0, 0, 2.5], curve: 'smooth' },
        markers: { size: [0, 0, 3], strokeWidth: 0 },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '58%' } },
        dataLabels: { enabled: false }, grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
        legend: { position: 'top', fontSize: '11px', markers: { radius: 2 } },
        tooltip: { shared: true, y: { formatter: v => fmt(v) + ' F' } },
    });

    @if($caByFamily->isNotEmpty())
    mount('#chart-famille', {
        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
        series: @json($caByFamily->pluck('ca')->map(fn($v) => (int) $v)),
        labels: @json($caByFamily->pluck('famille')),
        colors: multi,
        legend: { position: 'bottom', horizontalAlign: 'left', fontSize: '11px', itemMargin: { horizontal: 8, vertical: 2 }, markers: { radius: 2 } },
        dataLabels: { enabled: true, formatter: v => Math.round(v) + ' %' },
        plotOptions: { pie: { donut: { size: '68%' } } },
    });
    @endif

    // [UI pro] Donut compact : légende en bas (droite = donut écrasé dans une
    // colonne 3/12), segments à zéro masqués (le « 0 (0,00 %) » ×5 noyait le réel).
    const donut = (sel, labels, counts, height) => {
        const pairs = labels.map((l, i) => [l, Number(counts[i]) || 0]).filter(p => p[1] > 0);
        if (!pairs.length) return;
        const total = pairs.reduce((a, p) => a + p[1], 0);
        mount(sel, {
            chart: { type: 'donut', height: height, fontFamily: 'inherit' },
            series: pairs.map(p => p[1]), labels: pairs.map(p => p[0]),
            colors: multi, dataLabels: { enabled: false },
            legend: {
                position: 'bottom', horizontalAlign: 'left', fontSize: '11px',
                itemMargin: { horizontal: 8, vertical: 2 }, markers: { radius: 2 },
                formatter: (name, opts) => {
                    const v = opts.w.globals.series[opts.seriesIndex];
                    return name + '  ' + v + ' (' + (v / total * 100).toFixed(1).replace('.', ',') + ' %)';
                },
            },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 600 } } } } },
        });
    };

    @if($ordersStatus->isNotEmpty())
    donut('#chart-statut', @json($ordersStatus->pluck('label')), @json($ordersStatus->pluck('count')), 290);
    @endif

    mount('#chart-echeance', {
        chart: { type: 'bar', height: 290, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Commandes', data: [{{ $deliveries['en_retard'] }}, {{ $deliveries['aujourd_hui'] }}, {{ $deliveries['cette_semaine'] }}, {{ $deliveries['semaine_prochaine'] }}, {{ $deliveries['plus_tard'] }}] }],
        xaxis: { categories: ['En retard', 'Aujourd\'hui', 'Cette semaine', 'Semaine proch.', 'Plus tard'], labels: { style: { fontSize: '10px', colors: '#94a3b8' } } },
        plotOptions: { bar: { horizontal: true, borderRadius: 3, distributed: true, barHeight: '60%' } },
        colors: ['#ef4444', '#f59e0b', '#eab308', '#10b981', '#9ca3af'],
        dataLabels: { enabled: true }, legend: { show: false }, grid: { borderColor: '#f1f5f9' },
    });

    donut('#chart-pipeline',
        @json(collect($pipeline)->map(fn($s) => $s['label'])->values()),
        @json(collect($pipeline)->map(fn($s) => $s['count'])->values()), 290);

    mount('#spark-marge', {
        chart: { type: 'area', height: 32, sparkline: { enabled: true } },
        series: [{ data: @json($monthly->pluck('total_ht')->map(fn($v) => (int) $v)) }],
        stroke: { curve: 'smooth', width: 1.5 }, colors: ['#059669'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
        tooltip: { enabled: false },
    });
});
</script>
@endpush
