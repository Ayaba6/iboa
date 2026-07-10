@extends('layouts.erp')
@section('title', 'Échéancier clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.encaissements.index') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Échéancier clients</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Échéancier clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">Versements à recevoir — en retard et à venir</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Fenêtre :</label>
            <form method="GET" class="flex items-center gap-2">
                <select name="window" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm bg-white focus:ring-1 focus:ring-emerald-400">
                    @foreach([7,14,30,60,90] as $w)
                        <option value="{{ $w }}" {{ $window == $w ? 'selected' : '' }}>{{ $w }} jours</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-rose-50 border border-rose-200 rounded-[4px] p-4">
            <p class="text-xs font-semibold text-rose-600 uppercase tracking-wide">En retard</p>
            <p class="text-[16px] font-bold text-rose-700 mt-1">{{ number_format($totalOverdue, 0, ',', ' ') }} FCFA</p>
            <p class="text-xs text-rose-500 mt-0.5">{{ $overdue->count() }} échéance(s) dépassée(s)</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4">
            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">À venir ({{ $window }}j)</p>
            <p class="text-[16px] font-bold text-amber-700 mt-1">{{ number_format($totalUpcoming, 0, ',', ' ') }} FCFA</p>
            <p class="text-xs text-amber-500 mt-0.5">{{ $upcoming->count() }} échéance(s) prévue(s)</p>
        </div>
    </div>

    {{-- En retard --}}
    @if($overdue->count())
    <div class="bg-white rounded-[4px] border border-rose-200 overflow-hidden">
        <div class="px-3 py-1.5 bg-rose-50 border-b border-rose-200 flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="text-sm font-bold text-rose-700">Échéances en retard</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-rose-700 text-white">
                <tr>
                    <th class="px-3 py-2.5 text-left font-semibold">Facture</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Client</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Échéance</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Retard</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Montant</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Reste dû</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rose-50">
                @foreach($overdue as $r)
                @php $days = (int) $r['due_date']->diffInDays(now()->startOfDay()); @endphp
                <tr class="hover:bg-rose-50">
                    <td class="px-3 py-2.5">
                        <a href="{{ route('ventes.factures.show', $r['invoice_id']) }}" class="text-emerald-800 hover:underline font-medium">
                            {{ $r['number'] ?? '—' }}
                        </a>
                        @if($r['label'])
                        <div class="text-xs text-gray-400">{{ $r['label'] }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ $r['client'] }}</td>
                    <td class="px-3 py-2.5 text-center text-rose-700 font-medium">{{ $r['due_date']->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                            {{ $days }} j
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($r['amount'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-bold text-rose-700">{{ number_format($r['remaining'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-center">
                        <a href="{{ route('tresorerie.encaissements.create', ['client_id' => $r['client_id']]) }}"
                           class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-[4px] font-medium transition-colors">
                            Encaisser
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- À venir --}}
    @if($upcoming->count())
    <div class="bg-white rounded-[4px] border border-amber-200 overflow-hidden">
        <div class="px-3 py-1.5 bg-amber-50 border-b border-amber-200 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h2 class="text-sm font-bold text-amber-700">Prochaines échéances ({{ $window }} jours)</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-amber-600 text-white">
                <tr>
                    <th class="px-3 py-2.5 text-left font-semibold">Facture</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Client</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Échéance</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Dans</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Montant</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Reste dû</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-amber-50">
                @foreach($upcoming as $r)
                @php $days = (int) now()->startOfDay()->diffInDays($r['due_date']); @endphp
                <tr class="hover:bg-amber-50">
                    <td class="px-3 py-2.5">
                        <a href="{{ route('ventes.factures.show', $r['invoice_id']) }}" class="text-emerald-800 hover:underline font-medium">
                            {{ $r['number'] ?? '—' }}
                        </a>
                        @if($r['label'])
                        <div class="text-xs text-gray-400">{{ $r['label'] }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ $r['client'] }}</td>
                    <td class="px-3 py-2.5 text-center font-medium text-gray-700">{{ $r['due_date']->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-amber-100 text-amber-800">
                            {{ $days === 0 ? "Aujourd'hui" : 'J-' . $days }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($r['amount'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-bold text-amber-700">{{ number_format($r['remaining'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-center">
                        <a href="{{ route('tresorerie.encaissements.create', ['client_id' => $r['client_id']]) }}"
                           class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-[4px] font-medium transition-colors">
                            Encaisser
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($overdue->isEmpty() && $upcoming->isEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-gray-500">Aucune échéance en retard ou prévue dans les {{ $window }} prochains jours.</p>
    </div>
    @endif

</div>
@endsection
