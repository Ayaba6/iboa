@extends('layouts.erp')
@section('title', 'Relevé client')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.tiers.index') }}" class="hover:text-gray-700">Tiers</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Relevé client</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1 leading-tight min-h-[26px]';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[13px] bg-gray-50 text-gray-600';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'text-[13px] font-bold text-emerald-700 mb-3';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $fmt   = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $statutBadges = [
        'solde_initial' => ['Solde initial',          'bg-gray-100 text-gray-600'],
        'non_echue'     => ['Non échue',              'bg-blue-100 text-blue-700'],
        'echue'         => ['Échue',                  'bg-red-100 text-red-700'],
        'lettree'       => ['Lettrée',                'bg-emerald-100 text-emerald-700'],
        'partielle'     => ['Partiellement lettrée',  'bg-amber-100 text-amber-700'],
        'non_lettree'   => ['Non lettrée',            'bg-orange-100 text-orange-700'],
    ];
    $totAgees = array_sum($agees);
@endphp
<div class="space-y-3" x-data="{ tab: 'criteres', statutF: '' }">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Relevé client</h1>
        <div class="flex items-center gap-1.5">
            <button type="submit" form="releve-filter"
                    class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Actualiser</button>
            <button type="button" onclick="window.print()"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
            @php $exportQ = ['client_id' => $client?->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]; @endphp
            <a href="{{ route('clients.releve.export-pdf', $exportQ) }}"
               class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Exporter PDF</a>
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Exporter ▾</button>
                <div x-show="open" x-cloak class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-[4px] shadow-lg z-20 min-w-[190px]">
                    <a href="{{ route('clients.releve.export-excel', $exportQ) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50">Excel (.xlsx)</a>
                    <a href="{{ route('clients.releve', ['client_id' => 'all', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50 border-t border-gray-100">Relevé tous clients</a>
                </div>
            </div>
            @if($client)
                @if($client->email)
                <form method="POST" action="{{ route('comptabilite.tiers.releve-client.send') }}"
                      onsubmit="return confirm('Envoyer le relevé PDF à {{ addslashes($client->email) }} ?')">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                    <button type="submit"
                            class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Envoyer ✉</button>
                </form>
                @else
                <button type="button" disabled title="Ce client n'a pas d'adresse email renseignée — complétez la fiche client."
                        class="text-[14px] font-semibold text-gray-400 border border-gray-200 bg-gray-50 px-5 py-2 rounded-[4px] cursor-not-allowed">Envoyer ✉</button>
                @endif
            @endif
            @if($client)
            <a href="{{ route('clients.show', $client) }}"
               class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Fiche client</a>
            @endif
            <a href="{{ route('comptabilite.tiers.index') }}"
               class="text-[14px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-5 py-2 rounded-[4px] transition-colors">✕ Fermer</a>
        </div>
    </div>

    {{-- Onglets-ancres --}}
    <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
        @foreach(['criteres' => 'Critères', 'synthese' => 'Synthèse', 'releve' => 'Relevé', 'echeances' => 'Échéances', 'notes' => 'Notes'] as $tk => $tl)
        <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
        @endforeach
    </nav>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-2 rounded-[4px] text-[13px]">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded-[4px] text-[13px]">{{ session('error') }}</div>
    @endif

    {{-- 1. Sélection du tiers --}}
    <form method="GET" id="releve-filter" data-anchor="sec-criteres" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden scroll-mt-24">
        <span id="sec-criteres"></span>
        <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">1. Sélection du tiers / client</div>
        <div class="p-4 grid grid-cols-12 gap-x-3 gap-y-3">
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                <input type="text" value="01" class="{{ $inpRo }} font-mono" readonly>
            </div>
            <div class="col-span-6 sm:col-span-4">
                <label class="{{ $lbl }}">Client <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select name="client_id" class="{{ $lk }}">
                        @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($client?->id === $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Compte collectif</label>
                <input type="text" value="{{ $client?->compte_collectif ?: '411100' }}" class="{{ $inpRo }} font-mono" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Clients — conventions générales</p>
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Devise</label>
                <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Date d'arrêté <span class="text-red-500">*</span></label>
                <input type="date" name="date_arrete" value="{{ $dateArrete->toDateString() }}" class="{{ $inp }}">
            </div>

            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Période du <span class="text-red-500">*</span></label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Période au <span class="text-red-500">*</span></label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Échéances uniquement</label>
                <div class="relative">
                    <select name="only_due" class="{{ $lk }}">
                        <option value="0" @selected(! $onlyDue)>Non</option>
                        <option value="1" @selected($onlyDue)>Oui — échues seules</option>
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Lettrées / Non lettrées</label>
                <div class="relative">
                    <select name="lettrage" class="{{ $lk }}">
                        <option value="" @selected(! $lettrage)>Toutes</option>
                        <option value="lettrees" @selected($lettrage === 'lettrees')>Lettrées</option>
                        <option value="non_lettrees" @selected($lettrage === 'non_lettrees')>Non lettrées</option>
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Commercial</label>
                <input type="text" value="{{ $client?->assigned_to ? \App\Models\User::find($client->assigned_to)?->name : '—' }}" class="{{ $inpRo }}" readonly>
            </div>
        </div>
    </form>

    @if($client)
    {{-- 2. Synthèse — 8 KPIs --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden scroll-mt-24" id="sec-synthese">
        <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">2. Synthèse — situation du compte</div>
        <div class="p-3 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
        @foreach([
            ['Solde initial', $stats['solde_initial'], 'text-gray-900', 'border-gray-300', 'text-gray-400', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['Débit période', $stats['debit'], 'text-blue-700', 'border-blue-200', 'text-blue-400', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
            ['Crédit période', $stats['credit'], 'text-emerald-700', 'border-emerald-200', 'text-emerald-400', 'M17 13l-5 5m0 0l-5-5m5 5V6'],
            ['Solde final', $stats['solde_final'], $stats['solde_final'] > 0 ? 'text-gray-900' : 'text-emerald-700', 'border-gray-400', 'text-gray-400', 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
            ['Non échues', $stats['non_echues'], 'text-blue-700', 'border-blue-200', 'text-blue-400', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['Échues', $stats['echues'], $stats['echues'] > 0 ? 'text-red-600' : 'text-gray-400', $stats['echues'] > 0 ? 'border-red-300' : 'border-gray-300', $stats['echues'] > 0 ? 'text-orange-400' : 'text-gray-300', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['Encours autorisé', $stats['encours_aut'], 'text-emerald-700', 'border-emerald-200', 'text-emerald-400', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['Dépassement éventuel', $stats['depassement'], $stats['depassement'] > 0 ? 'text-red-600' : 'text-gray-400', $stats['depassement'] > 0 ? 'border-red-400' : 'border-gray-300', $stats['depassement'] > 0 ? 'text-red-400' : 'text-gray-300', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ] as [$kLbl, $kVal, $kTxt, $kBd, $kIco, $kPath])
        <div class="bg-gray-50/60 rounded-[4px] border {{ $kBd }} px-3 py-2.5 flex flex-col min-h-[76px]">
            <div class="flex items-start gap-1.5">
                <svg class="w-4 h-4 flex-shrink-0 mt-px {{ $kIco }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kPath }}"/>
                </svg>
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide leading-tight">{{ $kLbl }}</p>
            </div>
            <p class="mt-auto text-[16px] font-bold tabular-nums {{ $kTxt }} leading-none">{{ $fmt($kVal) }}</p>
            <p class="mt-1 text-[10px] text-gray-400 font-medium">XOF</p>
        </div>
        @endforeach
        </div>
    </div>

    {{-- 3. Détail des écritures / relevé --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden scroll-mt-24" id="sec-releve">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gray-50">
            <span class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">3. Détail des écritures / relevé</span>
            <span class="flex items-center gap-2">
                <div class="relative">
                    <select x-model="statutF"
                            class="appearance-none h-7 py-0 pl-2 pr-6 border border-gray-300 rounded-[3px] text-[12px] bg-white focus:ring-1 focus:ring-emerald-400">
                        <option value="">Tous les statuts</option>
                        <option value="non_echue">Non échues</option>
                        <option value="echue">Échues</option>
                        <option value="lettree">Lettrées</option>
                        <option value="partielle">Partiellement lettrées</option>
                        <option value="non_lettree">Non lettrées</option>
                    </select>
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>
                </div>
                <span class="text-[12px] text-gray-500">{{ $rows->count() }} résultat(s)</span>
            </span>
        </div>
        <div class="overflow-x-auto">
        <table class="min-w-full text-[13px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">Date</th>
                    <th class="px-3 py-1.5 text-left">Journal</th>
                    <th class="px-3 py-1.5 text-left">Pièce</th>
                    <th class="px-3 py-1.5 text-left">Référence</th>
                    <th class="px-3 py-1.5 text-left">Libellé</th>
                    <th class="px-3 py-1.5 text-left">Échéance</th>
                    <th class="px-3 py-1.5 text-right">Débit (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Crédit (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Solde (XOF)</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {{-- Ligne solde initial --}}
                <tr class="bg-gray-50/70">
                    <td class="px-3 py-1 tabular-nums text-gray-500">{{ \Carbon\Carbon::parse($dateFrom)->subDay()->format('d/m/Y') }}</td>
                    <td class="px-3 py-1"><span class="font-mono text-[11px] bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded-[3px]">OD</span></td>
                    <td class="px-3 py-1 font-mono text-[12px] text-gray-500">SOLDE INITIAL</td>
                    <td class="px-3 py-1 text-gray-400">—</td>
                    <td class="px-3 py-1 text-gray-600">Solde au {{ \Carbon\Carbon::parse($dateFrom)->subDay()->format('d/m/Y') }}</td>
                    <td class="px-3 py-1 text-gray-400">—</td>
                    <td class="px-3 py-1 text-right tabular-nums">{{ $stats['solde_initial'] > 0 ? $fmt($stats['solde_initial']) : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums">{{ $stats['solde_initial'] < 0 ? $fmt(abs($stats['solde_initial'])) : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium">{{ $fmt($stats['solde_initial']) }}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-gray-100 text-gray-600">Solde initial</span>
                    </td>
                </tr>
                @forelse($rows as $r)
                @php [$sLbl, $sCls] = $statutBadges[$r->statut] ?? ['?', 'bg-gray-100 text-gray-500']; @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors"
                    x-show="!statutF || statutF === '{{ $r->statut }}'">
                    <td class="px-3 py-1 tabular-nums text-gray-600">{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                    <td class="px-3 py-1"><span class="font-mono text-[11px] bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded-[3px]">{{ $r->journal }}</span></td>
                    <td class="px-3 py-1">
                        <a href="{{ $r->url }}" class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[12.5px]">{{ $r->piece }}</a>
                    </td>
                    <td class="px-3 py-1 font-mono text-[11.5px] text-gray-500">{{ $r->reference }}</td>
                    <td class="px-3 py-1 text-gray-700 max-w-xs truncate">{{ $r->libelle }}</td>
                    <td class="px-3 py-1 tabular-nums text-gray-500 text-[12px]">{{ $r->echeance ? \Carbon\Carbon::parse($r->echeance)->format('d/m/Y') : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums {{ $r->debit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">{{ $r->debit > 0 ? $fmt($r->debit) : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums {{ $r->credit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">{{ $r->credit > 0 ? $fmt($r->credit) : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium {{ $r->solde < 0 ? 'text-red-600' : '' }}">{{ $r->solde < 0 ? '-' : '' }}{{ $fmt(abs($r->solde)) }}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium whitespace-nowrap {{ $sCls }}">{{ $sLbl }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucun mouvement sur la période avec ces filtres.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-[#edf0f2] border-t-2 border-gray-300 font-bold text-gray-900">
                    <td colspan="6" class="px-3 py-1.5 text-[11px] uppercase text-gray-500">Total période</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($stats['debit']) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($stats['credit']) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums {{ $stats['solde_final'] < 0 ? 'text-red-600' : '' }}">{{ $fmt($stats['solde_final']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>

    {{-- 4. Balance âgée + 5. Historique --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 items-start">
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden scroll-mt-24" id="sec-echeances">
            <div class="px-4 py-2 border-b border-gray-200 bg-[#eef5f0]">
                <h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">4. Balance âgée (au {{ $dateArrete->format('d/m/Y') }})</h2>
            </div>
            <table class="w-full text-[13px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Libellé</th>
                        <th class="px-3 py-1.5 text-right">Non échu</th>
                        <th class="px-3 py-1.5 text-right">0 – 30 j</th>
                        <th class="px-3 py-1.5 text-right">31 – 60 j</th>
                        <th class="px-3 py-1.5 text-right">61 – 90 j</th>
                        <th class="px-3 py-1.5 text-right">+ 90 j</th>
                        <th class="px-3 py-1.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-100">
                        <td class="px-3 py-1.5 font-medium text-gray-700">Montant (XOF)</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($agees['non_echu']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $agees['j0_30'] > 0 ? 'text-amber-600 font-medium' : '' }}">{{ $fmt($agees['j0_30']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $agees['j31_60'] > 0 ? 'text-orange-600 font-medium' : '' }}">{{ $fmt($agees['j31_60']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $agees['j61_90'] > 0 ? 'text-red-600 font-medium' : '' }}">{{ $fmt($agees['j61_90']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $agees['j90p'] > 0 ? 'text-red-700 font-bold' : '' }}">{{ $fmt($agees['j90p']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold">{{ $fmt($totAgees) }}</td>
                    </tr>
                    <tr class="bg-gray-50/60">
                        <td class="px-3 py-1.5 text-gray-500 text-[12px]">% du total</td>
                        @foreach(['non_echu', 'j0_30', 'j31_60', 'j61_90', 'j90p'] as $bucket)
                        <td class="px-3 py-1.5 text-right tabular-nums text-[12px] text-gray-500">
                            {{ $totAgees > 0 ? number_format($agees[$bucket] / $totAgees * 100, 2, ',', ' ') : '0,00' }} %
                        </td>
                        @endforeach
                        <td class="px-3 py-1.5 text-right tabular-nums text-[12px] font-semibold text-gray-700">100,00 %</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden scroll-mt-24" id="sec-notes">
            <div class="px-4 py-2 border-b border-gray-200 bg-[#eef5f0]">
                <h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">5. Historique / commentaires</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($historique as $h)
                <div class="px-3 py-2 flex items-start gap-3">
                    <span class="text-[11px] text-gray-400 tabular-nums whitespace-nowrap pt-0.5">{{ $h->created_at->format('d/m/Y H:i') }}</span>
                    <span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap pt-0.5">{{ $h->user_name ?? 'Système' }}</span>
                    <span class="text-[12.5px] text-gray-600">
                        @if($h->action === 'commentaire')
                            {{ $h->new_values['text'] ?? '' }}
                        @elseif($h->action === 'releve_envoye')
                            Relevé envoyé par email ({{ $h->new_values['email'] ?? '' }}).
                        @else
                            {{ ucfirst($h->action ?? 'modification') }} de la fiche client.
                        @endif
                    </span>
                </div>
                @empty
                <p class="px-4 py-6 text-center text-gray-400 text-[13px]">Aucun événement récent sur ce client.</p>
                @endforelse
                @if($client?->notes)
                <div class="px-3 py-2 bg-amber-50/50">
                    <p class="text-[11px] font-bold text-amber-700 uppercase">Note fiche client</p>
                    <p class="text-[12.5px] text-gray-700 mt-0.5">{{ $client->notes }}</p>
                </div>
                @endif
            </div>
            {{-- Ajouter un commentaire (maquette) --}}
            <form method="POST" action="{{ route('comptabilite.tiers.releve-client.comment') }}"
                  class="px-3 py-2 border-t border-gray-100 bg-gray-50/60 flex items-center gap-2">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="text" name="text" required minlength="3" maxlength="500" placeholder="Ajouter un commentaire…"
                       class="flex-1 h-8 px-2 border border-gray-400 rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
                <button type="submit"
                        class="h-8 px-3 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px] transition-colors">Ajouter</button>
            </form>
        </div>
    </div>

    {{-- Barre de contexte pied de page --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Client : <span class="text-white font-semibold">{{ $client->code }}</span></span>
        <span class="border-l border-white/10 pl-6">Période : <span class="text-white font-semibold">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    @else
    <div class="bg-white rounded-[4px] border border-gray-300 py-16 text-center text-gray-400">
        <p class="text-sm font-medium">Aucun client dans le référentiel.</p>
    </div>
    @endif

</div>
@endsection
