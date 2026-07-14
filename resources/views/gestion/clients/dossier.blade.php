@extends('layouts.erp')
@section('title', 'Dossier — '.$client->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $client->name }} — Dossier</span>
@endsection

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';

    // Libellés + couleurs par TYPE de document — chaque document porte son propre statut.
    $badge = function (string $label, string $tone) {
        $map = [
            'gray' => 'bg-gray-100 text-gray-600', 'blue' => 'bg-blue-100 text-blue-700',
            'emerald' => 'bg-emerald-100 text-emerald-700', 'amber' => 'bg-amber-100 text-amber-700',
            'red' => 'bg-red-100 text-red-700', 'violet' => 'bg-violet-100 text-violet-700',
        ];
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-semibold '.($map[$tone] ?? $map['gray']).'">'.$label.'</span>';
    };
    $quoteS = ['brouillon'=>['Brouillon','gray'],'envoye'=>['Envoyé','blue'],'accepte'=>['Accepté','emerald'],'converti'=>['Converti','violet'],'refuse'=>['Refusé','red'],'expire'=>['Expiré','amber'],'annule'=>['Annulé','red']];
    $orderS = ['brouillon'=>['Brouillon','gray'],'soumis'=>['À confirmer','amber'],'confirme'=>['Confirmée','blue'],'en_preparation'=>['En préparation','blue'],'partiellement_livre'=>['Part. livrée','amber'],'livre'=>['Livrée','emerald'],'facture'=>['Facturée','violet'],'annule'=>['Annulée','red']];
    $ofS    = ['brouillon'=>['Brouillon','gray'],'lance'=>['Lancé','blue'],'en_cours'=>['En cours','blue'],'termine_partiellement'=>['Terminé part.','amber'],'termine'=>['Terminé','emerald'],'suspendu'=>['Suspendu','amber'],'annule'=>['Annulé','red']];
    $blS    = ['brouillon'=>['Brouillon','gray'],'en_attente_validation'=>['À valider','amber'],'valide'=>['Livré','emerald'],'annule'=>['Annulé','red']];
    $invS   = ['brouillon'=>['Brouillon','gray'],'emise'=>['Émise','blue'],'envoyee'=>['Envoyée','blue'],'partiellement_payee'=>['Part. payée','amber'],'payee'=>['Payée','emerald'],'en_retard'=>['En retard','red'],'annulee'=>['Annulée','red']];
    $cnS    = ['brouillon'=>['Brouillon','gray'],'valide'=>['Validé','violet'],'applique'=>['Appliqué','emerald'],'annule'=>['Annulé','red']];
    $show = fn ($map, $s) => $badge(($map[$s][0] ?? ucfirst((string) $s)), ($map[$s][1] ?? 'gray'));
@endphp
<div class="max-w-6xl space-y-4">

    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Dossier client — {{ $client->name }}</h1>
            <p class="text-sm text-gray-500">Parcours commercial bout-en-bout — chaque document porte son propre statut.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('clients.credit.index', $client) }}" class="h-8 inline-flex items-center px-3 border border-gray-300 rounded-[3px] text-[13px] hover:bg-gray-50">Crédit</a>
            <a href="{{ route('clients.show', $client) }}" class="h-8 inline-flex items-center px-3 border border-gray-300 rounded-[3px] text-[13px] hover:bg-gray-50">Fiche</a>
        </div>
    </div>

    {{-- Synthèse financière — statuts corrects --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">CA facturé</p><p class="text-lg font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($totalInvoiced) }}</p></div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Encours</p><p class="text-lg font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($outstanding) }}</p></div>
        <div class="bg-white border {{ $overdue > 0 ? 'border-red-200' : 'border-gray-200' }} rounded-[4px] p-4"><p class="text-xs {{ $overdue > 0 ? 'text-red-600' : 'text-gray-500' }} uppercase">En retard</p><p class="text-lg font-bold {{ $overdue > 0 ? 'text-red-700' : 'text-gray-900' }} tabular-nums mt-1">{{ $fmt($overdue) }}</p></div>
        <div class="bg-white border border-emerald-200 rounded-[4px] p-4"><p class="text-xs text-emerald-600 uppercase">Crédit dispo</p><p class="text-lg font-bold text-emerald-700 tabular-nums mt-1">{{ $fmt($client->available_credit) }}</p></div>
        <div class="bg-white border {{ $client->is_blocked ? 'border-red-200' : 'border-gray-200' }} rounded-[4px] p-4"><p class="text-xs {{ $client->is_blocked ? 'text-red-600' : 'text-gray-500' }} uppercase">Statut crédit</p><p class="text-sm font-bold mt-1 {{ $client->is_blocked ? 'text-red-700' : 'text-emerald-700' }}">{{ $client->is_blocked ? '● Bloqué' : '✓ Actif' }}</p></div>
    </div>

    {{-- Parcours par commande : chaîne documentaire avec statuts individuels --}}
    <div>
        <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Parcours des commandes (chaîne documentaire)</div>
        <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white"><tr>
                    <th class="{{ $th }} text-left">Commande</th>
                    <th class="{{ $th }} text-left">Devis</th>
                    <th class="{{ $th }} text-left">Commande</th>
                    <th class="{{ $th }} text-left">OF</th>
                    <th class="{{ $th }} text-left">Livraison</th>
                    <th class="{{ $th }} text-left">Facture</th>
                    <th class="{{ $th }} text-right">Reste dû</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $o)
                    @php $inv = $o->invoices->first(); @endphp
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-3 py-2 font-mono text-emerald-800 whitespace-nowrap">{{ $o->number }}</td>
                        <td class="px-3 py-2">{!! $o->quote ? $show($quoteS, $o->quote->status) : '<span class="text-gray-300">—</span>' !!}</td>
                        <td class="px-3 py-2">{!! $show($orderS, $o->status) !!}</td>
                        <td class="px-3 py-2">
                            @forelse($o->productionOrders as $of){!! $show($ofS, $of->status) !!}@if(!$loop->last)<br>@endif
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-3 py-2">
                            @forelse($o->deliveryNotes as $bl){!! $show($blS, $bl->status) !!}@if(!$loop->last)<br>@endif
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-3 py-2">
                            @forelse($o->invoices as $f){!! $show($invS, $f->status) !!}@if(!$loop->last)<br>@endif
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums {{ $inv && (float) $inv->remaining_amount > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $inv ? $fmt($inv->remaining_amount) : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune commande.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Factures --}}
        <div>
            <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Factures</div>
            <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#3b4248] text-white"><tr>
                        <th class="{{ $th }} text-left">N°</th><th class="{{ $th }} text-left">Statut</th>
                        <th class="{{ $th }} text-right">TTC</th><th class="{{ $th }} text-right">Reste dû</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($invoices->where('type', '!=', 'avoir') as $f)
                        <tr>
                            <td class="px-3 py-1.5 font-mono text-emerald-800">{{ $f->number }}</td>
                            <td class="px-3 py-1.5">{!! $show($invS, $f->status) !!}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($f->total_ttc) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ (float) $f->remaining_amount > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $fmt($f->remaining_amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Aucune facture.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Avoirs --}}
        <div>
            <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Avoirs</div>
            <div class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] overflow-x-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#3b4248] text-white"><tr>
                        <th class="{{ $th }} text-left">N°</th><th class="{{ $th }} text-left">Statut</th>
                        <th class="{{ $th }} text-right">TTC</th><th class="{{ $th }} text-right">Solde</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($creditNotes as $cn)
                        <tr>
                            <td class="px-3 py-1.5 font-mono text-emerald-800">{{ $cn->number }}</td>
                            <td class="px-3 py-1.5">{!! $show($cnS, $cn->status) !!}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($cn->total_ttc) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($cn->remaining_credit) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Aucun avoir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Commandes : <span class="text-white font-semibold">{{ $orders->count() }}</span></span>
        <span class="border-l border-white/10 pl-6">Factures : <span class="text-white font-semibold">{{ $invoices->where('type','!=','avoir')->count() }}</span></span>
        <span class="ml-auto tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
