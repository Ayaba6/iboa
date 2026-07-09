@extends('layouts.erp')
@section('title', 'Mes validations')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Mes validations</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- KPI --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-4 py-3">
            <p class="text-xs text-gray-500">En attente de moi</p>
            <p class="text-lg font-bold text-amber-600 tabular-nums">{{ $pending->total() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border {{ $lateCount > 0 ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white' }} px-4 py-3">
            <p class="text-xs {{ $lateCount > 0 ? 'text-red-600' : 'text-gray-500' }}">En retard (> 48 h)</p>
            <p class="text-lg font-bold {{ $lateCount > 0 ? 'text-red-700' : 'text-gray-900' }} tabular-nums">{{ $lateCount }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-4 py-3">
            <p class="text-xs text-gray-500">Mes soumissions en cours</p>
            <p class="text-lg font-bold text-blue-600 tabular-nums">{{ $mySubmissions->count() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-4 py-3">
            <p class="text-xs text-gray-500">Mes actions récentes</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums">{{ $history->total() }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mes validations</h1>
        <p class="text-sm text-gray-500 mt-0.5">Tous les documents qui attendent votre action, selon vos habilitations</p>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° document, client, demandeur…"
                   class="xl:col-span-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">

            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                <option value="">Tous les types</option>
                @foreach($types as $t)
                <option value="{{ $t }}" {{ ($filters['type'] ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" title="Soumis à partir du"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" title="Soumis jusqu'au"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">

            <input type="number" name="amount_min" value="{{ $filters['amount_min'] ?? '' }}" min="0" step="1000"
                   placeholder="Montant min (F)"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-right focus:ring-2 focus:ring-emerald-500">
        </div>
        <div class="flex flex-wrap items-center gap-3 mt-3">
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="late" value="1" {{ ($filters['late'] ?? '') === '1' ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-red-600 border-gray-300 focus:ring-red-500">
                <span>⏰ En retard uniquement (&gt; 48 h)</span>
            </label>
            <div class="ml-auto flex items-center gap-2">
                <button type="submit"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(array_filter($filters))
                <a href="{{ route('validations.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-4 py-2 rounded-lg transition-colors">
                    Réinitialiser
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- En attente de ma validation — liste style SAGE X3 --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
            <span>En attente de ma validation</span>
            <span class="normal-case tracking-normal font-semibold text-gray-500">{{ $pending->total() }} document(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                        <th class="text-left font-bold px-3 py-2 text-[11px] uppercase w-32">Type document</th>
                        <th class="text-left font-bold px-3 py-2 text-[11px] uppercase w-36">Référence</th>
                        <th class="text-left font-bold px-3 py-2 text-[11px] uppercase hidden md:table-cell">Tiers / Fournisseur</th>
                        <th class="text-right font-bold px-3 py-2 text-[11px] uppercase hidden sm:table-cell w-32">Montant</th>
                        <th class="text-left font-bold px-3 py-2 text-[11px] uppercase hidden lg:table-cell">Demandeur</th>
                        <th class="text-left font-bold px-3 py-2 text-[11px] uppercase hidden lg:table-cell w-40">Soumis le</th>
                        <th class="text-left font-bold px-3 py-2 text-[11px] uppercase hidden xl:table-cell w-24">Niveau</th>
                        <th class="px-3 py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $p)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $p['is_late'] ? '!bg-red-50/40' : '' }}">
                        <td class="px-3 py-1.5 text-gray-700">{{ $p['type'] }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <a href="{{ $p['url'] }}" class="font-mono font-semibold text-emerald-800 hover:underline">{{ $p['number'] }}</a>
                            @if($p['is_late'])
                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded-[3px] text-[10px] font-bold bg-red-100 text-red-700">RETARD</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-700 hidden md:table-cell">{{ $p['tiers'] ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900 hidden sm:table-cell whitespace-nowrap">
                            {{ $p['amount'] !== null && $p['amount'] > 0 ? number_format($p['amount'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 hidden lg:table-cell">{{ $p['requester'] ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 hidden lg:table-cell whitespace-nowrap">
                            {{ $p['submitted_at']?->format('d/m/Y H:i') ?? '—' }}
                            @if($p['submitted_at'])
                            <span class="text-gray-400 text-[11px]">({{ $p['submitted_at']->diffForHumans(short: true) }})</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden xl:table-cell">{{ $p['level'] }}</td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ $p['url'] }}"
                               class="inline-flex items-center gap-1 px-3 py-1 border border-emerald-500 text-emerald-700 bg-white hover:bg-emerald-50 rounded-full text-[12px] font-semibold transition-colors">
                                Traiter →
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <svg class="w-10 h-10 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-600">Rien en attente — tout est à jour ✓</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pending->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">
            {{ $pending->links() }}
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Mes soumissions en cours --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-4 py-2 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
                Mes soumissions en attente
            </div>
            @if($mySubmissions->isEmpty())
            <p class="px-5 py-8 text-center text-gray-400 text-sm">Aucune soumission en cours.</p>
            @else
            <ul class="divide-y divide-gray-100">
                @foreach($mySubmissions as $s)
                <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ $s['type'] }}</span>
                        <a href="{{ $s['url'] }}" class="font-mono text-sm font-semibold text-blue-600 hover:text-blue-800">{{ $s['number'] }}</a>
                    </div>
                    <span class="text-xs text-gray-400">{{ $s['submitted_at']?->diffForHumans() }}</span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- Mon historique --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-4 py-2 border-b border-gray-200 bg-[#eef5f0] flex items-center justify-between text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
                <span>Historique de mes actions</span>
                <span class="normal-case tracking-normal font-semibold text-gray-500">{{ $history->total() }} action(s)</span>
            </div>
            @php
                $docLabels = [
                    'quote'         => 'Devis',
                    'order'         => 'Commande',
                    'delivery_note' => 'Bon de livraison',
                    'invoice'       => 'Facture',
                    'credit_note'   => 'Avoir',
                ];
            @endphp
            @if($history->isEmpty())
            <p class="px-5 py-8 text-center text-gray-400 text-sm">Aucune validation effectuée.</p>
            @else
            <ul class="divide-y divide-gray-100">
                @foreach($history as $h)
                <li class="px-5 py-3 flex items-center justify-between text-sm hover:bg-gray-50">
                    <div class="flex items-center gap-2 min-w-0">
                        @if($h->action === \App\Models\CommercialValidation::ACTION_VALIDATION)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 flex-shrink-0">Validé</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 flex-shrink-0">Refusé</span>
                        @endif
                        <span class="text-gray-700 truncate">{{ $docLabels[$h->document_type] ?? ucfirst($h->document_type) }} #{{ $h->document_id }}</span>
                        @if($h->motif)
                        <span class="text-xs text-gray-400 truncate" title="{{ $h->motif }}">— {{ $h->motif }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400 flex-shrink-0 ml-2">{{ $h->created_at->format('d/m H:i') }}</span>
                </li>
                @endforeach
            </ul>
            @if($history->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $history->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>

</div>
@endsection
