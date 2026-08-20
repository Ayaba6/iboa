@extends('layouts.erp')
@section('title', 'Seuils d\'approbation PO')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Seuils d'approbation PO</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((float) $n, 0, ',', ' '); @endphp

<div class="max-w-4xl mx-auto space-y-3">
    <h1 class="text-[15px] font-bold text-gray-900">Seuils d'approbation des bons de commande</h1>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-[4px] px-3 py-1.5 text-[13px]">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-800 rounded-[4px] px-3 py-1.5 text-[13px]">{{ session('error') }}</div>@endif

    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-[13px] text-blue-800">
        <p class="font-medium mb-1">💡 Fonctionnement</p>
        <ul class="list-disc list-inside text-[12px] space-y-0.5 text-blue-700">
            <li>Quand un PO est soumis à approbation, le système trouve la <strong>règle</strong> dont la tranche <code>[min_amount, max_amount[</code> couvre le total TTC.</li>
            <li>Seul un utilisateur qui possède le <strong>rôle</strong> OU la <strong>permission</strong> définie peut approuver.</li>
            <li>Si <strong>aucune règle</strong> ne couvre le montant, le PO peut être confirmé sans approbation.</li>
            <li>Si une règle s'applique sans rôle ni permission définis, la permission par défaut est <code>purchase_orders.validate</code>.</li>
        </ul>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-[13px] font-semibold text-gray-700">Règles actives</h2></div>
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Nom</th>
                    <th class="px-3 py-1.5 text-right">Min</th>
                    <th class="px-3 py-1.5 text-right">Max</th>
                    <th class="px-3 py-1.5 text-left">Rôle requis</th>
                    <th class="px-3 py-1.5 text-left">Permission requise</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($thresholds as $t)
                <tr>
                    <td class="px-3 py-1.5">{{ $t->name }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($t->min_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $t->max_amount ? $fmt($t->max_amount) : '∞' }}</td>
                    <td class="px-3 py-1.5 text-[12px]"><code>{{ $t->required_role ?? '—' }}</code></td>
                    <td class="px-3 py-1.5 text-[12px]"><code>{{ $t->required_permission ?? '—' }}</code></td>
                    <td class="px-3 py-1.5 text-right">
                        <form action="{{ route('achats.approval.thresholds.destroy', $t) }}" method="POST" onsubmit="return confirm('Supprimer cette règle ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[12px] text-red-600 hover:underline">Supprimer</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucune règle définie — toute commande peut être confirmée sans approbation.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-[13px] font-semibold text-gray-800 mb-3">Ajouter une règle</h2>
        <form action="{{ route('achats.approval.thresholds.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            @csrf
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" required maxlength="100" placeholder="Ex. : Manager" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px]">
            </div>
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">Montant min <span class="text-red-500">*</span></label>
                <input type="number" name="min_amount" required min="0" step="0.01" value="0" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] text-right">
            </div>
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">Montant max</label>
                <input type="number" name="max_amount" min="0" step="0.01" placeholder="vide = ∞" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] text-right">
            </div>
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">Rôle requis</label>
                <input type="text" name="required_role" maxlength="100" placeholder="ex: manager" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px]">
            </div>
            <div>
                <label class="block text-[12px] font-medium text-gray-700 mb-1">OU permission</label>
                <input type="text" name="required_permission" maxlength="100" placeholder="ex: purchase_orders.validate" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px]">
            </div>
            <div class="md:col-span-5 flex justify-end">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-5 py-1.5 rounded-[4px]">Ajouter la règle</button>
            </div>
        </form>
    </div>
</div>
@endsection
