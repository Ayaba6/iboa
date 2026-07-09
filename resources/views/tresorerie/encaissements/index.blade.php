@extends('layouts.erp')
@section('title', 'Encaissements clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.index') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Encaissements</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Encaissements clients</h1>
            <p class="text-[11.5px] text-gray-400">{{ $payments->total() }} encaissement(s)</p>
        </div>
        <div class="flex items-center gap-1.5 self-start flex-wrap">
            <a href="{{ route('tresorerie.encaissements.index', array_merge(request()->query(), ['export' => 1])) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-[12px] font-semibold rounded-[4px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Excel
            </a>
            <a href="{{ route('tresorerie.encaissements.export-pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-[12px] font-semibold rounded-[4px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('tresorerie.encaissements.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvel encaissement
            </a>
        </div>
    </div>

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
        <div class="bg-white rounded-[4px] border border-emerald-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total encaissé (filtré)</p>
            <p class="mt-0.5 text-[17px] font-bold text-emerald-600 tabular-nums leading-none">{{ $fmt($summary['total_amount']) }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-blue-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Ce mois-ci</p>
            <p class="mt-0.5 text-[17px] font-bold text-blue-600 tabular-nums leading-none">{{ $fmt($summary['this_month']) }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Nombre d'encaissements</p>
            <p class="mt-0.5 text-[17px] font-bold text-gray-900 tabular-nums leading-none">{{ $summary['count'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2" data-autosubmit>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-1.5">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Réf, client…"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 lg:col-span-2">

            <select name="client_id" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les clients</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ ($filters['client_id'] ?? '') == $client->id ? 'selected' : '' }}>{{ $client->trade_name ?? $client->name }}</option>
                @endforeach
            </select>

            <select name="payment_method_id" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les modes</option>
                @foreach($paymentMethods as $pm)
                    <option value="{{ $pm->id }}" {{ ($filters['payment_method_id'] ?? '') == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div class="flex gap-1.5 mt-1.5">
            <button type="submit"
                    class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if(count($filters) > 0)
            <a href="{{ route('tresorerie.encaissements.index') }}"
               class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px] transition-colors">
                ✕ Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] font-bold text-emerald-900 uppercase tracking-wide">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Numéro</th>
                        <th class="px-3 py-1.5 text-left">Date</th>
                        <th class="px-3 py-1.5 text-left">Client</th>
                        <th class="px-3 py-1.5 text-right">Montant</th>
                        <th class="px-3 py-1.5 text-left hidden md:table-cell">Mode paiement</th>
                        <th class="px-3 py-1.5 text-left hidden lg:table-cell">Référence</th>
                        <th class="px-3 py-1.5 text-center hidden lg:table-cell">Factures imputées</th>
                        <th class="px-3 py-1.5 text-center">Statut</th>
                        <th class="px-3 py-1.5 w-px"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments as $payment)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1">
                            <a href="{{ route('tresorerie.encaissements.show', $payment) }}" class="font-mono font-semibold text-emerald-700 hover:underline">{{ $payment->number }}</a>
                        </td>
                        <td class="px-3 py-1 text-gray-600 whitespace-nowrap">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1 font-medium text-gray-900">{{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}</td>
                        <td class="px-3 py-1 text-right font-semibold tabular-nums text-emerald-700 whitespace-nowrap">{{ number_format($payment->amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 hidden md:table-cell">
                            @if($payment->paymentMethod)
                                @php
                                    $pmClass = match($payment->paymentMethod->type) {
                                        'especes'      => 'bg-gray-100 text-gray-700',
                                        'virement'     => 'bg-blue-100 text-blue-700',
                                        'cheque'       => 'bg-emerald-100 text-emerald-800',
                                        'mobile_money' => 'bg-purple-100 text-purple-700',
                                        default        => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $pmClass }}">{{ $payment->paymentMethod->name }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-gray-600 hidden lg:table-cell font-mono text-[11px]">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-3 py-1 text-center hidden lg:table-cell">
                            @if($payment->allocations->count() > 0)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-green-100 text-green-700">{{ $payment->allocations->count() }} facture(s)</span>
                            @else
                                <span class="text-gray-400 text-[11px]">Non imputé</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-center">
                            @php
                                [$sl, $sc] = match($payment->status) {
                                    'confirme'   => ['Confirmé',   'bg-green-100 text-green-700'],
                                    'en_attente' => ['En attente', 'bg-yellow-100 text-yellow-700'],
                                    'rejete'     => ['Rejeté',     'bg-red-100 text-red-700'],
                                    'annule'     => ['Annulé',     'bg-gray-100 text-gray-600'],
                                    default      => [ucfirst((string) $payment->status), 'bg-gray-100 text-gray-600'],
                                };
                            @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $sc }}">{{ $sl }}</span>
                        </td>
                        <td class="px-3 py-1 text-right">
                            <a href="{{ route('tresorerie.encaissements.show', $payment) }}"
                               class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors inline-flex" title="Voir">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400 text-[12.5px]">
                            Aucun encaissement trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">
            {{ $payments->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
