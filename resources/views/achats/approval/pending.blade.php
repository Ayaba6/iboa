@extends('layouts.erp')
@section('title', 'PO en attente d\'approbation')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.dashboard') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Approbations</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">
    <div>
        <h1 class="text-[15px] font-bold text-gray-900">✋ PO en attente d'approbation</h1>
        <p class="text-[13px] text-gray-500">{{ $pendingPos->total() }} commande(s) à valider.</p>
    </div>

    @if($pendingPos->isEmpty())
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-6 text-center text-emerald-700 text-[13px]">
            ✓ Aucun PO en attente d'approbation.
        </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">PO</th>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-right">Montant TTC</th>
                    <th class="px-3 py-1.5 text-left">Soumis</th>
                    <th class="px-3 py-1.5 text-left">Niveau requis</th>
                    <th class="px-3 py-1.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($pendingPos as $po)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('achats.commandes.show', $po) }}" class="font-mono text-blue-700 font-semibold">{{ $po->number }}</a>
                        <p class="text-[12px] text-gray-500">par {{ $po->createdBy?->name ?? '—' }}</p>
                    </td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $po->supplier?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($po->total_ttc) }} FCFA</td>
                    <td class="px-3 py-1.5 text-[12px] text-gray-600">{{ $po->submitted_for_approval_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-[12px]">
                        @if($po->rule)
                            <span class="font-medium text-amber-700">{{ $po->rule->name }}</span>
                            <p class="text-gray-500">
                                @if($po->rule->required_role) Rôle : <code>{{ $po->rule->required_role }}</code> @endif
                                @if($po->rule->required_permission) Permission : <code>{{ $po->rule->required_permission }}</code> @endif
                            </p>
                        @else
                            <span class="text-gray-400">— aucun seuil défini —</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @if($po->can_approve)
                            <form action="{{ route('achats.approval.approve', $po) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Approuver le PO {{ $po->number }} ?')">
                                @csrf
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-medium px-3 py-1.5 rounded">✓ Approuver</button>
                            </form>
                            <form action="{{ route('achats.approval.reject', $po) }}" method="POST" class="inline"
                                  x-data="{ open: false, reason: '' }">
                                @csrf
                                <button type="button" @click="open = true" class="border border-red-300 text-red-700 hover:bg-red-50 text-[12px] font-medium px-3 py-1.5 rounded">✗ Rejeter</button>
                                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
                                    <div class="relative bg-white rounded-[4px] shadow-xl w-full max-w-md p-6 z-10">
                                        <h3 class="text-[13px] font-semibold">Rejeter PO {{ $po->number }}</h3>
                                        <textarea name="reason" x-model="reason" rows="3" required minlength="5" placeholder="Motif (≥ 5 caractères)..." class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] mt-3"></textarea>
                                        <div class="flex justify-end gap-2 mt-3">
                                            <button type="button" @click="open=false" class="border border-gray-300 text-gray-700 text-[13px] px-3 py-1.5 rounded-[4px]">Annuler</button>
                                            <button type="submit" :disabled="reason.length<5" :class="reason.length>=5?'bg-red-600 hover:bg-red-700 text-white':'bg-gray-200 text-gray-400 cursor-not-allowed'" class="text-[13px] font-medium px-3 py-1.5 rounded-[4px]">Confirmer le rejet</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @else
                            <span class="text-[12px] text-gray-400 italic">Pas votre niveau</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $pendingPos->links() }}</div>
    @endif
</div>
@endsection
