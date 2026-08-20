@extends('layouts.erp')
@section('title', 'Échéances de paiement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.dashboard') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Échéances</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((float) $n, 0, ',', ' '); @endphp

<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">💰 Échéances de paiement</h1>
            <p class="text-[13px] text-gray-500">Cadenciers de paiement fournisseur — vue d'ensemble des prochaines échéances.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">Fenêtre (jours)</label>
                <select name="window" onchange="this.form.submit()" class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px]">
                    @foreach([7,15,30,60,90,180] as $w)
                    <option value="{{ $w }}" {{ $window==$w?'selected':'' }}>{{ $w }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-[4px] border-2 {{ count($overdue) > 0 ? 'border-red-300' : 'border-emerald-200' }} p-5">
            <p class="text-[12px] font-medium {{ count($overdue) > 0 ? 'text-red-600' : 'text-emerald-600' }} uppercase">⏰ En retard</p>
            <p class="mt-1 text-[15px] font-bold tabular-nums {{ count($overdue) > 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ count($overdue) }}</p>
            <p class="text-[12px] {{ count($overdue) > 0 ? 'text-red-500' : 'text-emerald-500' }} mt-0.5">{{ $fmt($totalOverdue) }} FCFA</p>
        </div>
        <div class="bg-white rounded-[4px] border border-amber-200 p-5">
            <p class="text-[12px] font-medium text-amber-600 uppercase">📅 À venir ({{ $window }} j)</p>
            <p class="mt-1 text-[15px] font-bold tabular-nums text-amber-700">{{ count($upcoming) }}</p>
            <p class="text-[12px] text-amber-500 mt-0.5">{{ $fmt($totalUpcoming) }} FCFA</p>
        </div>
    </div>

    {{-- Overdue --}}
    @if($overdue->isNotEmpty())
    <div class="bg-white rounded-[4px] border-2 border-red-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-red-200 bg-red-50">
            <h2 class="text-[13px] font-semibold text-red-800">🛑 Échéances en retard ({{ count($overdue) }})</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Facture FF</th>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-left">Échéance n°</th>
                    <th class="px-3 py-1.5 text-right">Montant</th>
                    <th class="px-3 py-1.5 text-right">Payé</th>
                    <th class="px-3 py-1.5 text-right">Reste dû</th>
                    <th class="px-3 py-1.5 text-right">Échue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($overdue as $s)
                @php $remain = $s->amount - $s->paid_amount; @endphp
                <tr class="hover:bg-red-50/30">
                    <td class="px-3 py-1.5 font-mono text-[12px]">
                        <a href="{{ route('achats.factures-fournisseurs.show', $s->supplierInvoice) }}" class="text-blue-700 hover:underline">{{ $s->supplierInvoice?->number }}</a>
                    </td>
                    <td class="px-3 py-1.5 text-[12px]">{{ $s->supplierInvoice?->supplier?->name }}</td>
                    <td class="px-3 py-1.5 text-[12px]">{{ $s->label ?? 'Éch. '.$s->installment_number }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($s->amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($s->paid_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-red-700">{{ $fmt($remain) }}</td>
                    <td class="px-3 py-1.5 text-right text-[12px] text-red-700 font-medium">{{ $s->due_date?->format('d/m/Y') }} <span class="text-red-500">(+{{ $s->due_date?->diffInDays(now()) }} j)</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Upcoming --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-[13px] font-semibold text-gray-700">📅 Échéances à venir ({{ count($upcoming) }})</h2>
        </div>
        @if($upcoming->isEmpty())
            <div class="p-8 text-center text-emerald-700 text-[13px]">✓ Aucune échéance dans la fenêtre.</div>
        @else
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Facture FF</th>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-left">Échéance</th>
                    <th class="px-3 py-1.5 text-right">Montant</th>
                    <th class="px-3 py-1.5 text-right">Reste dû</th>
                    <th class="px-3 py-1.5 text-right">Dans</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($upcoming as $s)
                @php $remain = $s->amount - $s->paid_amount; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 font-mono text-[12px]">
                        <a href="{{ route('achats.factures-fournisseurs.show', $s->supplierInvoice) }}" class="text-blue-700 hover:underline">{{ $s->supplierInvoice?->number }}</a>
                    </td>
                    <td class="px-3 py-1.5 text-[12px]">{{ $s->supplierInvoice?->supplier?->name }}</td>
                    <td class="px-3 py-1.5 text-[12px]">{{ $s->label ?? 'Éch. '.$s->installment_number }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($s->amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-medium text-amber-700">{{ $fmt($remain) }}</td>
                    <td class="px-3 py-1.5 text-right text-[12px] text-gray-600">{{ $s->due_date?->format('d/m/Y') }} <span class="text-gray-400">({{ now()->diffInDays($s->due_date, false) }} j)</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
