{{--
    Dashboard — Tables :
    Factures à encaisser (avec onglet Paiements reçus) + Commandes récentes.
--}}
{{-- ══════════════════════════════════════════════════════════════
     TABLES — Factures à encaisser + Commandes récentes
     avec onglets Factures / Paiements reçus dans la 1ère colonne
══════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 fade-up" style="animation-delay:.32s">

    {{-- Factures / Paiements (onglets) --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden"
         x-data="{ tab: 'factures' }">

        {{-- En-tête avec onglets --}}
        <div class="px-5 pt-4 pb-0 border-b border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold text-gray-900">Suivi paiements</h3>
                <a href="{{ route('ventes.factures.index') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800">Tout voir →</a>
            </div>
            <div class="flex gap-0">
                <button @click="tab='factures'"
                        :class="tab==='factures' ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-gray-400 hover:text-gray-600'"
                        class="px-4 py-2 text-xs font-semibold transition-colors">
                    Factures à encaisser
                    @if($facturesAEncaisser->count() > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-100 text-rose-600 text-xs font-black">{{ $facturesAEncaisser->count() }}</span>
                    @endif
                </button>
                <button @click="tab='paiements'"
                        :class="tab==='paiements' ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-gray-400 hover:text-gray-600'"
                        class="px-4 py-2 text-xs font-semibold transition-colors">
                    Paiements reçus
                </button>
            </div>
        </div>

        {{-- Onglet Factures --}}
        <div x-show="tab==='factures'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @if($facturesAEncaisser->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">Tout est encaissé ✓</p>
            </div>
            @else
            <div class="tbl-rx">
            <table class="w-full min-w-[420px] text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Facture</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Échéance</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Reste dû</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($facturesAEncaisser as $facture)
                    @php
                        $overdue = $facture->due_at?->isPast();
                        $days    = $overdue ? now()->diffInDays($facture->due_at) : 0;
                    @endphp
                    <tr class="data-row border-b border-gray-50 last:border-0 {{ $overdue ? 'bg-rose-50/20' : '' }}">
                        <td class="px-5 py-3">
                            <a href="{{ route('ventes.factures.show', $facture) }}"
                               class="font-mono text-xs font-bold text-emerald-700 hover:text-emerald-900">{{ $facture->number }}</a>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-[#eef5f0] flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-black text-emerald-600">{{ strtoupper(substr(optional($facture->client)->name ?? '?', 0, 2)) }}</span>
                                </div>
                                <span class="text-xs text-gray-700 font-medium truncate max-w-[100px]">{{ optional($facture->client)->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center">
                            @if($facture->due_at)
                            <span class="text-xs font-semibold {{ $overdue ? 'text-rose-600' : 'text-gray-600' }}">{{ $facture->due_at->format('d/m/Y') }}</span>
                            @if($overdue)<br><span class="text-xs text-rose-400">+{{ $days }}j</span>@endif
                            @else<span class="text-gray-300 text-xs">—</span>@endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-xs font-black {{ $overdue ? 'text-rose-600' : 'text-gray-800' }} tabular-nums whitespace-nowrap">{{ number_format($facture->remaining_amount, 0, ',', ' ') }} F</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>{{-- /tbl-rx factures --}}
            @endif
        </div>

        {{-- Onglet Paiements reçus --}}
        <div x-show="tab==='paiements'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @if($derniersEncaissements->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">Aucun paiement ce mois</p>
            </div>
            @else
            <div class="tbl-rx">
            <table class="w-full min-w-[380px] text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Moyen</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($derniersEncaissements as $p)
                    <tr class="data-row border-b border-gray-50 last:border-0">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-black text-emerald-600">{{ strtoupper(substr(optional($p->client)->name ?? '?', 0, 2)) }}</span>
                                </div>
                                <span class="text-xs text-gray-700 font-medium truncate max-w-[90px]">{{ optional($p->client)->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                {{ optional($p->paymentMethod)->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y') }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-xs font-black text-emerald-700 tabular-nums whitespace-nowrap">{{ number_format($p->amount, 0, ',', ' ') }} F</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>{{-- /tbl-rx paiements --}}
            @endif
        </div>
    </div>

    {{-- Dernières commandes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#eef5f0] flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Dernières commandes</h3>
                    <p class="text-xs text-gray-400">{{ $nbCommandesEnCours }} en cours</p>
                </div>
            </div>
            <a href="{{ route('ventes.commandes.index') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800">Tout voir →</a>
        </div>

        @if($dernieresCommandes->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 gap-3">
            <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">Aucune commande</p>
        </div>
        @else
        <div class="tbl-rx">
        <table class="w-full min-w-[420px] text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Commande</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Montant</th>
                    <th class="px-5 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dernieresCommandes as $commande)
                @php
                    [$sc, $sl] = match($commande->status) {
                        'brouillon'           => ['bg-gray-100 text-gray-500',       'Brouillon'],
                        'confirme'            => ['bg-blue-100 text-blue-700',       'Confirmée'],
                        'en_preparation'      => ['bg-amber-100 text-amber-700',     'En prépa.'],
                        'partiellement_livre' => ['bg-orange-100 text-orange-700',   'Part. livrée'],
                        'livre'               => ['bg-emerald-100 text-emerald-700', 'Livrée'],
                        'facture'             => ['bg-emerald-100 text-emerald-800',   'Facturée'],
                        'annule'              => ['bg-red-100 text-red-600',         'Annulée'],
                        default               => ['bg-gray-100 text-gray-500',       ucfirst($commande->status)],
                    };
                @endphp
                <tr class="data-row border-b border-gray-50 last:border-0">
                    <td class="px-5 py-3">
                        <a href="{{ route('ventes.commandes.show', $commande) }}" class="font-mono text-xs font-bold text-emerald-700 hover:text-emerald-900">{{ $commande->number }}</a>
                        @if($commande->issued_at)<p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($commande->issued_at)->format('d/m/Y') }}</p>@endif
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-violet-50 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-black text-violet-600">{{ strtoupper(substr(optional($commande->client)->name ?? '?', 0, 2)) }}</span>
                            </div>
                            <span class="text-xs text-gray-700 font-medium truncate max-w-[90px]">{{ optional($commande->client)->name ?? '—' }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-right">
                        <span class="text-xs font-black text-gray-800 tabular-nums whitespace-nowrap">{{ number_format($commande->total_ttc, 0, ',', ' ') }} F</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $sc }}">{{ $sl }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>{{-- /tbl-rx commandes --}}
        @endif
    </div>
</div>

