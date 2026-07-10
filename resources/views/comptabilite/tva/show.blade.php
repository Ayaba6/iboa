@extends('layouts.erp')
@section('title', 'Déclaration TVA ' . $tva->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.tva.index') }}" class="hover:text-gray-700">Déclarations TVA</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $tva->number }}</span>
@endsection

@section('content')
<div class="space-y-3 max-w-4xl">

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $tva->number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $tva->period_label }} — {{ ucfirst($tva->period_type) }}</p>
        </div>
        <div class="flex items-center gap-3">
            @php $colors = ['brouillon' => 'bg-gray-100 text-gray-700', 'soumis' => 'bg-blue-100 text-blue-700', 'paye' => 'bg-green-100 text-green-700']; @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $colors[$tva->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $tva->statusLabel() }}
            </span>
            @if($tva->status === 'brouillon')
            @can('accounting.validate')
            <form method="POST" action="{{ route('comptabilite.tva.submit', $tva) }}">
                @csrf
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                    Soumettre
                </button>
            </form>
            @endcan
            @endif
            @if($tva->status === 'soumis' && $tva->tva_due > 0)
            @can('accounting.validate')
            <button type="button" onclick="document.getElementById('paid-modal').classList.remove('hidden')"
                    class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                Enregistrer paiement
            </button>
            @endcan
            @endif
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">TVA Collectée</p>
            <p class="text-[17px] font-bold tabular-nums text-red-600">{{ number_format($tva->tva_collectee, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">TVA Déductible</p>
            <p class="text-[17px] font-bold tabular-nums text-blue-600">{{ number_format($tva->tva_deductible, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">TVA Due</p>
            <p class="text-[17px] font-bold tabular-nums {{ $tva->tva_due > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                {{ number_format($tva->tva_due, 0, ',', ' ') }}
            </p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">Reste à payer</p>
            <p class="text-[17px] font-bold tabular-nums {{ $tva->remaining > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $tva->remaining > 0 ? number_format($tva->remaining, 0, ',', ' ') : '✓ 0' }}
            </p>
        </div>
    </div>

    {{-- Infos --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Informations</h2>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-y-3 gap-x-8 text-sm">
            <div><dt class="text-xs text-gray-500">Période</dt><dd class="font-medium">{{ $tva->period_start?->format('d/m/Y') }} → {{ $tva->period_end?->format('d/m/Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Date déclaration</dt><dd class="font-medium">{{ $tva->declaration_date?->format('d/m/Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Date limite</dt><dd class="font-medium">{{ $tva->due_date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">Montant payé</dt><dd class="font-medium">{{ number_format($tva->amount_paid ?? 0, 0, ',', ' ') }} FCFA</dd></div>
            @if($tva->credit_tva > 0)
            <div><dt class="text-xs text-gray-500">Crédit TVA</dt><dd class="font-medium text-blue-700">{{ number_format($tva->credit_tva, 0, ',', ' ') }} FCFA</dd></div>
            @endif
            <div><dt class="text-xs text-gray-500">Créé par</dt><dd class="font-medium">{{ $tva->createdBy?->name }}</dd></div>
        </dl>
        @if($tva->notes)
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Notes</p>
            <p class="text-sm text-gray-700">{{ $tva->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Detail by account --}}
    @if($detail)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-red-50">
                <h3 class="font-semibold text-red-800">TVA Collectée — Détail par compte</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300"><tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Compte</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($detail['collectee'] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5">
                            <span class="font-mono text-violet-600 font-semibold">{{ $row->account?->code }}</span>
                            <span class="text-gray-700 ml-1">{{ $row->account?->name }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-red-600">{{ number_format($row->total, 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="px-4 py-6 text-center text-gray-400">Aucun mouvement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-blue-50">
                <h3 class="font-semibold text-blue-800">TVA Déductible — Détail par compte</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300"><tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Compte</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($detail['deductible'] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5">
                            <span class="font-mono text-violet-600 font-semibold">{{ $row->account?->code }}</span>
                            <span class="text-gray-700 ml-1">{{ $row->account?->name }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-blue-600">{{ number_format($row->total, 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="px-4 py-6 text-center text-gray-400">Aucun mouvement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

{{-- Paid modal --}}
<div id="paid-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-sm p-6">
        <h3 class="text-[16px] font-bold text-gray-900 mb-4">Enregistrer le paiement</h3>
        <form method="POST" action="{{ route('comptabilite.tva.markPaid', $tva) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Montant payé (FCFA)</label>
                <input type="number" name="amount_paid" value="{{ $tva->tva_due }}" min="0" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="document.getElementById('paid-modal').classList.add('hidden')"
                        class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">
                    Annuler
                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
