@extends('layouts.erp')
@section('title', 'Remises commerciales')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('settings.sales.hub') }}" class="hover:text-gray-700">Paramétrage Vente</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Remises</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $typeLabels = ['client' => 'Client', 'groupe_client' => 'Groupe client', 'categorie_client' => 'Catégorie client', 'article' => 'Article', 'famille_article' => 'Famille article', 'volume' => 'Volume', 'promotionnelle' => 'Promotionnelle', 'exceptionnelle' => 'Exceptionnelle'];
@endphp
<div class="space-y-4"
     x-data="{
        modal: false, editId: null,
        form: { name: '', discount_type: 'client', client_id: '', client_group: '', client_category: '', product_id: '', product_family_id: '', rate_percent: '', min_quantity: '', cap_amount: '', starts_at: '', ends_at: '', requires_validation: false, is_active: true },
        openCreate() { this.form = { name: '', discount_type: 'client', client_id: '', client_group: '', client_category: '', product_id: '', product_family_id: '', rate_percent: '', min_quantity: '', cap_amount: '', starts_at: '', ends_at: '', requires_validation: false, is_active: true }; this.editId = null; this.modal = true; },
        openEdit(d) { this.form = { ...d, requires_validation: !!d.requires_validation, is_active: !!d.is_active }; this.editId = d.id; this.modal = true; },
     }">

    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Remises commerciales</h1>
            <p class="text-[11.5px] text-gray-500">Remises paramétrées appliquées automatiquement au calcul du prix (la plus favorable gagne)</p>
        </div>
        <button @click="openCreate()" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-full transition-colors">+ Nouvelle remise</button>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3 py-2.5 rounded-[4px] text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left">Nom</th>
                <th class="{{ $th }} text-left w-32">Type</th>
                <th class="{{ $th }} text-left">Cible</th>
                <th class="{{ $th }} text-right w-20">Taux</th>
                <th class="{{ $th }} text-right w-24">Qté min</th>
                <th class="{{ $th }} text-left w-44">Validité</th>
                <th class="{{ $th }} text-center w-24">Validation</th>
                <th class="{{ $th }} text-center w-20">Statut</th>
                <th class="{{ $th }} text-right w-32">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($discounts as $d)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1.5 font-semibold text-gray-800">{{ $d->name }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $typeLabels[$d->discount_type] ?? $d->discount_type }}</td>
                    <td class="px-3 py-1.5 text-gray-600 truncate max-w-[200px]">
                        {{ $d->client?->name ?? $d->client_group ?? $d->client_category ?? $d->product?->name ?? $d->productFamily?->name ?? 'Tous' }}
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono font-bold text-emerald-800">{{ number_format((float) $d->rate_percent, 2, ',', '') }} %</td>
                    <td class="px-3 py-1.5 text-right font-mono text-gray-500">{{ $d->min_quantity ? number_format((float) $d->min_quantity, 0, ',', ' ') : '—' }}</td>
                    <td class="px-3 py-1.5 font-mono text-[11px] text-gray-500">{{ $d->starts_at?->format('d/m/Y') ?? '∞' }} → {{ $d->ends_at?->format('d/m/Y') ?? '∞' }}</td>
                    <td class="px-3 py-1.5 text-center">{{ $d->requires_validation ? '🔒' : '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $d->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $d->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        <button @click="openEdit(@js($d->only(['id','name','discount_type','client_id','client_group','client_category','product_id','product_family_id','rate_percent','min_quantity','cap_amount','requires_validation','is_active']) + ['starts_at' => $d->starts_at?->toDateString(), 'ends_at' => $d->ends_at?->toDateString()]))"
                                class="text-[12px] font-semibold text-emerald-700 hover:underline">Modifier</button>
                        <span class="text-gray-300 mx-1">|</span>
                        <form method="POST" action="{{ route('settings.sales.discounts.destroy', $d) }}" class="inline"
                              onsubmit="return confirm('Supprimer la remise {{ addslashes($d->name) }} ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[12px] font-medium text-red-400 hover:text-red-600 hover:underline">Supprimer</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-5 py-16 text-center text-gray-400">Aucune remise paramétrée.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($discounts->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $discounts->links() }}</div>
        @endif
    </div>

    {{-- Modal --}}
    <div x-show="modal" x-cloak x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-xl w-full max-w-lg overflow-hidden" @click.outside="modal = false">
            <div class="px-3 py-2.5 border-b border-gray-200 bg-gradient-to-b from-[#eef5f0] to-white">
                <h3 class="text-[15px] font-bold text-emerald-900" x-text="editId ? 'Modifier la remise' : 'Nouvelle remise commerciale'"></h3>
            </div>
            <form :action="editId ? '{{ url('parametres/ventes/remises') }}/' + editId : '{{ route('settings.sales.discounts.store') }}'" method="POST" class="p-4 space-y-3">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Nom <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required maxlength="100" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Type <span class="text-red-500">*</span></label>
                        <select name="discount_type" x-model="form.discount_type" class="{{ $inp }}">
                            @foreach($typeLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Taux (%) <span class="text-red-500">*</span></label>
                        <input type="number" name="rate_percent" x-model="form.rate_percent" required step="0.01" min="0" max="100" class="{{ $inp }} text-right font-mono">
                    </div>
                    <div x-show="form.discount_type === 'client' || form.discount_type === 'promotionnelle' || form.discount_type === 'exceptionnelle'">
                        <label class="{{ $lbl }}">Client</label>
                        <select name="client_id" x-model="form.client_id" class="{{ $inp }}">
                            <option value="">— Tous —</option>
                            @foreach($clients as $cl)<option value="{{ $cl->id }}">{{ $cl->name }}</option>@endforeach
                        </select>
                    </div>
                    <div x-show="form.discount_type === 'groupe_client'">
                        <label class="{{ $lbl }}">Groupe client</label>
                        <input type="text" name="client_group" x-model="form.client_group" maxlength="60" class="{{ $inp }}">
                    </div>
                    <div x-show="form.discount_type === 'categorie_client'">
                        <label class="{{ $lbl }}">Catégorie client</label>
                        <input type="text" name="client_category" x-model="form.client_category" maxlength="60" class="{{ $inp }}">
                    </div>
                    <div x-show="['article', 'volume', 'promotionnelle'].includes(form.discount_type)">
                        <label class="{{ $lbl }}">Article</label>
                        <select name="product_id" x-model="form.product_id" class="{{ $inp }}">
                            <option value="">— Tous —</option>
                            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div x-show="form.discount_type === 'famille_article'">
                        <label class="{{ $lbl }}">Famille article</label>
                        <select name="product_family_id" x-model="form.product_family_id" class="{{ $inp }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($families as $fam)<option value="{{ $fam->id }}">{{ $fam->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Quantité minimale</label>
                        <input type="number" name="min_quantity" x-model="form.min_quantity" step="0.001" min="0" class="{{ $inp }} text-right font-mono">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Plafond (montant)</label>
                        <input type="number" name="cap_amount" x-model="form.cap_amount" step="0.01" min="0" class="{{ $inp }} text-right font-mono">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Début validité</label>
                        <input type="date" name="starts_at" x-model="form.starts_at" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Fin validité</label>
                        <input type="date" name="ends_at" x-model="form.ends_at" class="{{ $inp }}">
                    </div>
                </div>
                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="requires_validation" value="0">
                        <input type="checkbox" name="requires_validation" value="1" x-model="form.requires_validation" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Validation DAF/DG requise</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-[13px] font-medium text-gray-700">Active</span>
                    </label>
                </div>
                <div class="flex gap-2 justify-end pt-2 border-t border-gray-100">
                    <button type="button" @click="modal = false" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</button>
                    <button type="submit" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors" x-text="editId ? 'Enregistrer' : 'Créer'"></button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
