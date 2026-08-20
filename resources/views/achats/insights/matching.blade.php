@extends('layouts.erp')
@section('title', '3-way matching')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.dashboard') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">3-way matching</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">
    <div>
        <h1 class="text-[15px] font-bold text-gray-900">🔗 3-way matching</h1>
        <p class="text-[13px] text-gray-500">Détection automatique des écarts entre commande (PO), réception et facture fournisseur.</p>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-[13px] text-blue-800">
        <p class="font-medium mb-1">💡 Pourquoi le 3-way matching ?</p>
        <ul class="list-disc list-inside text-blue-700 text-[12px] space-y-0.5">
            <li><strong>Quantités</strong> : qté commandée ≠ qté reçue → manque/excédent à la livraison.</li>
            <li><strong>Quantités</strong> : qté reçue ≠ qté facturée → fournisseur facture plus/moins que ce que vous avez reçu.</li>
            <li><strong>Montants</strong> : prix unitaire facturé ≠ prix négocié dans le PO → contrôle des conditions tarifaires.</li>
            <li>C'est <strong>la première ligne de défense</strong> contre les erreurs et les fraudes fournisseurs.</li>
        </ul>
    </div>

    {{-- Écarts quantitatifs --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between {{ $matching['qty_count'] > 0 ? 'bg-amber-50' : 'bg-emerald-50' }}">
            <h2 class="text-[13px] font-semibold {{ $matching['qty_count'] > 0 ? 'text-amber-800' : 'text-emerald-800' }}">
                Écarts quantitatifs ({{ $matching['qty_count'] }})
            </h2>
        </div>
        @if($matching['qty_count'] === 0)
            <div class="p-8 text-center text-emerald-700 text-[13px]">✓ Aucun écart quantitatif détecté.</div>
        @else
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">PO</th>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-right">Commandé</th>
                    <th class="px-3 py-1.5 text-right">Reçu</th>
                    <th class="px-3 py-1.5 text-right">Facturé</th>
                    <th class="px-3 py-1.5 text-right">Écart R-C</th>
                    <th class="px-3 py-1.5 text-right">Écart F-R</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($matching['qty_discrepancies'] as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 font-mono text-[12px]">
                        <a href="{{ route('achats.commandes.show', $r->po_id) }}" class="text-blue-700 hover:underline">{{ $r->po_number }}</a>
                        <div class="text-gray-400">{{ \Carbon\Carbon::parse($r->ordered_at)->format('d/m/Y') }}</div>
                    </td>
                    <td class="px-3 py-1.5 text-[12px]">{{ $r->supplier_name }}</td>
                    <td class="px-3 py-1.5 text-[12px]">
                        <span class="font-mono text-blue-700">{{ $r->product_ref }}</span>
                        <p class="text-gray-900">{{ $r->product_name }}</p>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($r->ordered_qty, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($r->received_qty, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($r->invoiced_qty, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $r->recv_minus_ordered != 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                        {{ $r->recv_minus_ordered != 0 ? ($r->recv_minus_ordered > 0 ? '+' : '') . number_format($r->recv_minus_ordered, 2, ',', ' ') : '0' }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $r->invoiced_minus_received != 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                        {{ $r->invoiced_minus_received != 0 ? ($r->invoiced_minus_received > 0 ? '+' : '') . number_format($r->invoiced_minus_received, 2, ',', ' ') : '0' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Écarts montants --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between {{ $matching['amount_count'] > 0 ? 'bg-orange-50' : 'bg-emerald-50' }}">
            <h2 class="text-[13px] font-semibold {{ $matching['amount_count'] > 0 ? 'text-orange-800' : 'text-emerald-800' }}">
                Écarts de montants PO vs facture ({{ $matching['amount_count'] }})
            </h2>
        </div>
        @if($matching['amount_count'] === 0)
            <div class="p-8 text-center text-emerald-700 text-[13px]">✓ Aucun écart de montant détecté.</div>
        @else
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Facture FF</th>
                    <th class="px-3 py-1.5 text-left">PO liée</th>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-right">Montant PO</th>
                    <th class="px-3 py-1.5 text-right">Montant facturé</th>
                    <th class="px-3 py-1.5 text-right">Écart</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($matching['amount_discrepancies'] as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 font-mono text-[12px]">
                        <a href="{{ route('achats.factures-fournisseurs.show', $r->invoice_id) }}" class="text-blue-700 hover:underline">{{ $r->invoice_number }}</a>
                        <div class="text-gray-400">{{ $r->supplier_invoice_number }}</div>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[12px]">
                        <a href="{{ route('achats.commandes.show', $r->po_id) }}" class="text-blue-700 hover:underline">{{ $r->po_number }}</a>
                    </td>
                    <td class="px-3 py-1.5 text-[12px]">{{ $r->supplier_name }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($r->po_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($r->inv_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $r->gap > 0 ? 'text-red-700' : 'text-emerald-700' }}">
                        {{ $r->gap > 0 ? '+' : '' }}{{ $fmt($r->gap) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
