@extends('layouts.erp')
@section('title', 'Remise ' . $remise->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.remises.index') }}" class="hover:text-gray-700">Remises</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $remise->number }}</span>
@endsection

@section('content')
<div class="space-y-3 max-w-3xl">

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $remise->number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $remise->cashAccount?->name }} · {{ $remise->deposit_date?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $remise->status === 'valide' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                {{ $remise->statusLabel() }}
            </span>
            @if($remise->isEditable())
            @can('treasury.validate')
            <form method="POST" action="{{ route('tresorerie.remises.validate', $remise) }}"
                  onsubmit="return confirm('Valider cette remise ? Les soldes seront mis à jour.')">
                @csrf
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">✓ Valider</button>
            </form>
            @endcan
            @endif
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Informations</h2>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-y-3 gap-x-8 text-sm">
            <div><dt class="text-xs text-gray-500">N°</dt><dd class="font-mono font-semibold text-emerald-700">{{ $remise->number }}</dd></div>
            <div><dt class="text-xs text-gray-500">Compte bancaire</dt><dd class="font-medium">{{ $remise->cashAccount?->name }}</dd></div>
            <div><dt class="text-xs text-gray-500">Compte source</dt><dd class="font-medium">{{ $remise->sourceCashAccount?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">Date</dt><dd class="font-medium">{{ $remise->deposit_date?->format('d/m/Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Référence</dt><dd class="font-medium">{{ $remise->reference ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">Créé par</dt><dd class="font-medium">{{ $remise->createdBy?->name }}</dd></div>
            @if($remise->status === 'valide')
            <div><dt class="text-xs text-gray-500">Validé par</dt><dd class="font-medium">{{ $remise->validatedBy?->name }}</dd></div>
            <div><dt class="text-xs text-gray-500">Validé le</dt><dd class="font-medium">{{ $remise->validated_at?->format('d/m/Y H:i') }}</dd></div>
            @endif
        </dl>
    </div>

    {{-- Items --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between">
            <h2 class="font-semibold text-gray-800">Valeurs remises</h2>
            <span class="text-xs text-gray-500">{{ $remise->items->count() }} élément(s)</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300"><tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Tireur</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Échéance</th>
                <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($remise->items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2.5">
                        @php $typeColors = ['especes' => 'bg-slate-100 text-slate-700', 'cheque' => 'bg-blue-100 text-blue-700', 'effet' => 'bg-amber-100 text-amber-700', 'virement' => 'bg-green-100 text-green-700']; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $typeColors[$item->type] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $item->typeLabel() }}
                        </span>
                        @if($item->commercialEffect)
                        <a href="{{ route('tresorerie.effets.show', $item->commercialEffect) }}"
                           class="text-xs text-emerald-600 hover:text-emerald-800 ml-1">{{ $item->commercialEffect->number }}</a>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-gray-700">{{ $item->reference ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-gray-700">{{ $item->drawer ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-gray-500 text-xs">{{ $item->due_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-800">{{ number_format($item->amount, 0, ',', ' ') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                <tr>
                    <td colspan="4" class="px-3 py-1.5 text-right text-xs font-bold text-gray-600 uppercase">Total</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-emerald-800 text-base">{{ number_format($remise->total_amount, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>
@endsection
