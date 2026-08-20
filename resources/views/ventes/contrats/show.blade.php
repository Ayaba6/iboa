@extends('layouts.erp')
@section('title', 'Contrat ' . $contrat->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.contrats.index') }}" class="hover:text-gray-700">Contrats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $contrat->number }}</span>
@endsection

@section('content')
@php
    $c    = $contrat;
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-2 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $dtL  = 'text-[11px] font-bold text-gray-500 uppercase tracking-wide';
    $dtV  = 'text-[13px] text-gray-800';
    $badges = [
        'brouillon' => 'bg-gray-100 text-gray-600', 'actif' => 'bg-emerald-100 text-emerald-800',
        'suspendu' => 'bg-amber-100 text-amber-700', 'termine' => 'bg-blue-100 text-blue-700', 'annule' => 'bg-red-100 text-red-700',
    ];
    $party = $c->contract_type === 'vente' ? $c->client : $c->supplier;
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[20px] font-bold text-emerald-900 flex items-center gap-2">
                {{ $c->number }}
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badges[$c->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($c->status) }}</span>
                @if($c->is_framework)<span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-700">Contrat cadre</span>@endif
            </h1>
            <p class="text-[11px] text-gray-500">{{ $c->description }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('ventes.contrats.pdf', $c) }}?preview=1" target="_blank" class="text-[13px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-full transition-colors">📄 PDF</a>
            @can('orders.create')
            <a href="{{ route('ventes.contrats.edit', $c) }}" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Modifier</a>
            @endcan
            <a href="{{ route('ventes.contrats.index') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Retour</a>
        </div>
    </div>

    {{-- Informations principales --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Informations principales</div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
            <div><p class="{{ $dtL }}">Type de contrat</p><p class="{{ $dtV }} font-semibold {{ $c->contract_type === 'vente' ? 'text-emerald-700' : 'text-amber-700' }}">{{ ucfirst($c->contract_type) }}</p></div>
            <div><p class="{{ $dtL }}">{{ $c->contract_type === 'vente' ? 'Client' : 'Fournisseur' }}</p><p class="{{ $dtV }} font-semibold">{{ $party?->name ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Représentant</p><p class="{{ $dtV }}">{{ $c->salesRep?->name ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Devise</p><p class="{{ $dtV }} font-mono">{{ $c->currency_code }}</p></div>
            <div><p class="{{ $dtL }}">Date contrat</p><p class="{{ $dtV }} font-mono tabular-nums">{{ $c->contract_date?->format('d/m/Y') ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Date début</p><p class="{{ $dtV }} font-mono tabular-nums">{{ $c->starts_at?->format('d/m/Y') ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Date fin</p><p class="{{ $dtV }} font-mono tabular-nums">{{ $c->ends_at?->format('d/m/Y') ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Catégorie</p><p class="{{ $dtV }}">{{ $c->category ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Projet</p><p class="{{ $dtV }} font-mono">{{ $c->project_reference ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Conditions de paiement</p><p class="{{ $dtV }}">{{ $c->payment_terms ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Incoterm</p><p class="{{ $dtV }}">{{ $c->incoterm ?? '—' }}</p></div>
            <div><p class="{{ $dtL }}">Entrepôt par défaut</p><p class="{{ $dtV }}">{{ $c->warehouse ? $c->warehouse->code.' — '.$c->warehouse->name : '—' }}</p></div>
        </div>
    </div>

    {{-- Éléments contractuels --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Éléments contractuels ({{ $c->items->count() }} ligne(s))</div>
        <table class="w-full text-[12px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-center w-10">#</th>
                <th class="{{ $th }} text-left">Article / Désignation</th>
                <th class="{{ $th }} text-left w-16">Unité</th>
                <th class="{{ $th }} text-right w-28">Qté contractuelle</th>
                <th class="{{ $th }} text-right w-28">Prix unitaire</th>
                <th class="{{ $th }} text-right w-20">Remise</th>
                <th class="{{ $th }} text-right w-36">Montant HT</th>
            </tr></thead>
            <tbody>
                @forelse($c->items as $line)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                    <td class="px-2 py-1.5 text-center text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                    <td class="px-2 py-1.5 text-gray-700">
                        <span class="font-semibold">{{ $line->product?->name ?? $line->designation }}</span>
                        @if($line->product && $line->designation !== $line->product->name)<span class="block text-[11px] text-gray-400">{{ $line->designation }}</span>@endif
                    </td>
                    <td class="px-2 py-1.5 font-mono text-gray-500">{{ $line->unit ?? '—' }}</td>
                    <td class="px-2 py-1.5 text-right font-mono tabular-nums">{{ number_format((float) $line->quantity, 0, ',', ' ') }}</td>
                    <td class="px-2 py-1.5 text-right font-mono tabular-nums">{{ number_format((float) $line->unit_price, 0, ',', ' ') }}</td>
                    <td class="px-2 py-1.5 text-right font-mono tabular-nums text-gray-500">{{ (float) $line->discount_percent ? number_format((float) $line->discount_percent, 2, ',', '').' %' : '—' }}</td>
                    <td class="px-2 py-1.5 text-right font-mono tabular-nums font-semibold text-gray-800">{{ number_format((float) $line->amount_ht, 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune ligne contractuelle.</td></tr>
                @endforelse
            </tbody>
            @if($c->items->isNotEmpty())
            <tfoot><tr class="border-t border-gray-300 bg-[#f7faf8]">
                <td colspan="6" class="px-2 py-2 text-right text-[12px] font-semibold text-gray-600">Total lignes HT</td>
                <td class="px-2 py-2 text-right font-mono tabular-nums text-[15px] font-bold text-emerald-800">{{ number_format((float) $c->total_ht, 0, ',', ' ') }} {{ $c->currency_code }}</td>
            </tr></tfoot>
            @endif
        </table>
    </div>

    {{-- Informations complémentaires | Pièces jointes | Traçabilité --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Informations complémentaires</div>
            <div class="p-4 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><p class="{{ $dtL }}">Mode de transport</p><p class="{{ $dtV }} capitalize">{{ $c->transport_mode ?? '—' }}</p></div>
                    <div><p class="{{ $dtL }}">Durée de validité</p><p class="{{ $dtV }}">{{ $c->validity_days ? $c->validity_days.' jours' : '—' }}</p></div>
                </div>
                <div><p class="{{ $dtL }}">Observations</p><p class="{{ $dtV }} whitespace-pre-line">{{ $c->observations ?: '—' }}</p></div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Pièces jointes</div>
            <div class="p-4">
                @if($c->attachments->isNotEmpty())
                <ul class="divide-y divide-gray-100 text-[12px]">
                    @foreach($c->attachments as $att)
                    <li class="flex items-center justify-between py-1.5">
                        <span class="truncate text-gray-700">📄 {{ $att->filename }}</span>
                        <span class="text-[11px] text-gray-400 flex-shrink-0 ml-2">{{ number_format($att->size / 1024) }} KB</span>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-[12px] text-gray-400">Aucune pièce jointe.</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Traçabilité</div>
            <div class="p-4 grid grid-cols-2 gap-3">
                <div><p class="{{ $dtL }}">Créé le</p><p class="{{ $dtV }} font-mono tabular-nums">{{ $c->created_at?->format('d/m/Y H:i') ?? '—' }}</p></div>
                <div><p class="{{ $dtL }}">Par</p><p class="{{ $dtV }} truncate">{{ $c->creator?->name ?? '—' }}</p></div>
                <div><p class="{{ $dtL }}">Dernière modification</p><p class="{{ $dtV }} font-mono tabular-nums">{{ $c->updated_at?->format('d/m/Y H:i') ?? '—' }}</p></div>
                <div><p class="{{ $dtL }}">Statut</p><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badges[$c->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($c->status) }}</span></div>
            </div>
        </div>
    </div>

</div>
@endsection
