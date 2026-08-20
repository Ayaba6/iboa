@extends('layouts.erp')
@section('title', $representant->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('representants.index') }}" class="hover:text-gray-700">Représentants</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $representant->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-[16px] font-bold text-gray-900">{{ $representant->name }}</h1>
                @if($representant->is_active)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-50 text-green-700 border border-green-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Actif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactif
                    </span>
                @endif
            </div>
            @if($representant->code)
                <p class="text-sm text-gray-400 font-mono mt-1">{{ $representant->code }}</p>
            @endif
        </div>
        @can('update', $representant)
        <div class="flex items-center gap-2">
            <a href="{{ route('representants.edit', $representant) }}"
               class="inline-flex items-center gap-2 px-3 py-2.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Modifier
            </a>
            <form action="{{ route('representants.destroy', $representant) }}" method="POST"
                  data-confirm="Supprimer le représentant {{ addslashes($representant->name ?? $representant->code ?? '') }} ?">
                @csrf @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2.5 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium rounded-[4px] transition-colors">
                    Supprimer
                </button>
            </form>
        </div>
        @endcan
    </div>

    {{-- Infos + Totaux commissions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Infos rep --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Informations</h2>
            <dl class="space-y-2 text-sm">
                @if($representant->email)
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900 font-medium">{{ $representant->email }}</dd>
                </div>
                @endif
                @if($representant->phone)
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Téléphone</dt>
                    <dd class="text-gray-900 font-medium">{{ $representant->phone }}</dd>
                </div>
                @endif
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Taux de commission</dt>
                    <dd class="text-gray-900 font-bold">{{ number_format($representant->commission_rate, 2, ',', ' ') }} %</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Clients rattachés</dt>
                    <dd class="text-gray-900 font-medium">{{ $clients->count() }}</dd>
                </div>
                @if($representant->user)
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Compte utilisateur</dt>
                    <dd class="text-gray-900 font-medium">{{ $representant->user->name }}</dd>
                </div>
                @endif
            </dl>
            @if($representant->notes)
            <div class="mt-3 pt-3 border-t border-gray-100 text-sm text-gray-600">{{ $representant->notes }}</div>
            @endif
        </div>

        {{-- KPI commissions --}}
        <div class="lg:col-span-2 grid grid-cols-3 gap-3">
            <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4 text-center">
                <p class="text-xs text-amber-600 font-medium mb-1">Calculées</p>
                <p class="text-[17px] font-bold text-amber-700 tabular-nums">{{ number_format($totals['calculee'], 0, ',', ' ') }}</p>
                <p class="text-xs text-amber-500 mt-0.5">FCFA</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-center">
                <p class="text-xs text-blue-600 font-medium mb-1">Validées</p>
                <p class="text-[17px] font-bold text-blue-700 tabular-nums">{{ number_format($totals['validee'], 0, ',', ' ') }}</p>
                <p class="text-xs text-blue-500 mt-0.5">FCFA</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-[4px] p-4 text-center">
                <p class="text-xs text-green-600 font-medium mb-1">Payées</p>
                <p class="text-[17px] font-bold text-green-700 tabular-nums">{{ number_format($totals['payee'], 0, ',', ' ') }}</p>
                <p class="text-xs text-green-500 mt-0.5">FCFA</p>
            </div>
        </div>
    </div>

    {{-- Clients rattachés --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Portefeuille clients ({{ $clients->count() }})</h2>
        </div>
        @if($clients->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-400 text-center">Aucun client rattaché à ce représentant.</p>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Contact</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Solde</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($clients as $client)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2.5">
                        <a href="{{ route('clients.show', $client) }}" class="font-medium text-gray-900 hover:text-blue-600">{{ $client->name }}</a>
                        <p class="text-xs text-gray-400 font-mono">{{ $client->code }}</p>
                    </td>
                    <td class="px-3 py-2.5 text-gray-500 hidden md:table-cell">{{ $client->email ?: $client->phone ?: '—' }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums {{ $client->balance > 0 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                        {{ number_format(abs($client->balance), 0, ',', ' ') }} FCFA
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Commissions --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-4">
            <h2 class="text-sm font-semibold text-gray-800">Historique des commissions</h2>
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="period" value="{{ $period }}"
                       class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500">
                <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-3 py-1.5 rounded-[4px] transition-colors">Filtrer</button>
                @if(request()->has('period'))
                <a href="{{ route('representants.show', $representant) }}" class="text-sm text-gray-500 hover:text-gray-700">Tout voir</a>
                @endif
            </form>
        </div>

        @if($commissions->isEmpty())
            <p class="px-5 py-8 text-sm text-gray-400 text-center">Aucune commission enregistrée pour cette période.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Période</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Paiement</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Base</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Taux</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Commission</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        @can('update', $representant)
                        <th class="px-3 py-2.5"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($commissions as $commission)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5 font-mono text-gray-600 text-xs">{{ $commission->period }}</td>
                        <td class="px-3 py-2.5 font-medium text-gray-900">{{ $commission->client?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-gray-500 hidden md:table-cell">
                            @if($commission->payment)
                                {{ $commission->payment->number }}<br>
                                <span class="text-xs text-gray-400">{{ $commission->payment->payment_date?->format('d/m/Y') }}</span>
                            @else —
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($commission->base_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-500">{{ number_format($commission->commission_rate, 2, ',', ' ') }} %</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($commission->commission_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-center">
                            @php
                                $statusStyles = [
                                    'calculee' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    'validee'  => 'bg-blue-50 text-blue-700 border-blue-100',
                                    'payee'    => 'bg-green-50 text-green-700 border-green-100',
                                ];
                                $statusLabels = ['calculee' => 'Calculée', 'validee' => 'Validée', 'payee' => 'Payée'];
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium border {{ $statusStyles[$commission->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusLabels[$commission->status] ?? $commission->status }}
                            </span>
                        </td>
                        @can('update', $representant)
                        <td class="px-3 py-2.5">
                            <form method="POST" action="{{ route('representants.commissions.status', $commission) }}">
                                @csrf @method('PATCH')
                                <select name="status" onchange="this.form.submit()"
                                        class="text-xs border border-gray-200 rounded px-2 py-1 focus:ring-1 focus:ring-emerald-500">
                                    @foreach(\App\Models\Commission::statusOptions() as $val => $label)
                                        <option value="{{ $val }}" {{ $commission->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($commissions->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $commissions->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
