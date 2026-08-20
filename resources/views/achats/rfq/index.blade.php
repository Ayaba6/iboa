@extends('layouts.erp')
@section('title', 'Demandes de devis')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.dashboard') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">RFQ</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">📋 Demandes de devis (RFQ)</h1>
            <p class="text-[13px] text-gray-500">Consulter plusieurs fournisseurs et comparer les offres avant achat.</p>
        </div>
        @can('purchase_orders.create')
        <a href="{{ route('achats.rfq.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px] inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle RFQ
        </a>
        @endcan
    </div>

    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[12px] font-medium text-gray-700 mb-1">Statut</label>
            <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px]">
                <option value="">Tous</option>
                @foreach(['brouillon'=>'Brouillon','envoyee'=>'Envoyée','recue'=>'Réponses reçues','cloturee'=>'Clôturée','annulee'=>'Annulée'] as $k=>$v)
                <option value="{{ $k }}" {{ request('status')===$k?'selected':'' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">N°</th>
                    <th class="px-3 py-1.5 text-left">Titre</th>
                    <th class="px-3 py-1.5 text-right">Fournisseurs</th>
                    <th class="px-3 py-1.5 text-right">Cotations reçues</th>
                    <th class="px-3 py-1.5 text-right">Échéance</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                    <th class="px-3 py-1.5 text-left">Gagnant</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($rfqs as $rfq)
                @php $color = $rfq->statusColor(); @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5"><a href="{{ route('achats.rfq.show', $rfq) }}" class="font-mono text-blue-700 font-semibold">{{ $rfq->number }}</a></td>
                    <td class="px-3 py-1.5 text-gray-900">{{ $rfq->title }}</td>
                    <td class="px-3 py-1.5 text-right text-gray-700">{{ $rfq->rfqSuppliers->count() }}</td>
                    <td class="px-3 py-1.5 text-right text-gray-700">{{ $rfq->rfqSuppliers->where('status','recue')->count() }}</td>
                    <td class="px-3 py-1.5 text-right text-[12px] text-gray-600">{{ $rfq->deadline?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">{{ $rfq->statusLabel() }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-[12px] text-gray-700">
                        @if($rfq->awardedQuote)
                            {{ $rfq->awardedQuote->rfqSupplier?->supplier?->name }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        <a href="{{ route('achats.rfq.show', $rfq) }}" class="text-[12px] text-blue-600 hover:underline">Détails →</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune demande de devis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $rfqs->links() }}</div>
</div>
@endsection
