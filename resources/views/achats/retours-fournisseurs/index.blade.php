@extends('layouts.erp')
@section('title', 'Retours fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Retours fournisseurs</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Retours fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $returns->total() }} retour(s)</p>
        </div>
        @can('supplier_returns.create')
        <a href="{{ route('achats.retours-fournisseurs.create') }}"
           class="bg-amber-600 hover:bg-amber-700 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau retour
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, fournisseur..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">

            <select name="supplier_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les fournisseurs</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"        {{ ($filters['status'] ?? '') === 'brouillon'        ? 'selected' : '' }}>Brouillon</option>
                <option value="valide"           {{ ($filters['status'] ?? '') === 'valide'           ? 'selected' : '' }}>Validé</option>
                <option value="envoye"           {{ ($filters['status'] ?? '') === 'envoye'           ? 'selected' : '' }}>Envoyé</option>
                <option value="recu_fournisseur" {{ ($filters['status'] ?? '') === 'recu_fournisseur' ? 'selected' : '' }}>Reçu fournisseur</option>
                <option value="annule"           {{ ($filters['status'] ?? '') === 'annule'           ? 'selected' : '' }}>Annulé</option>
            </select>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'supplier_id', 'status']))
                <a href="{{ route('achats.retours-fournisseurs.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide w-32">N° retour</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide">Fournisseur</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden md:table-cell w-24">Date</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden lg:table-cell">Motif</th>
                        <th class="text-right font-bold px-3 py-2 uppercase tracking-wide w-32">Montant TTC</th>
                        <th class="text-center font-bold px-3 py-2 uppercase tracking-wide w-32">Statut</th>
                        <th class="px-3 py-2 w-28"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $return)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('achats.retours-fournisseurs.show', $return) }}" class="hover:underline font-semibold">{{ $return->number }}</a>
                        </td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $return->supplier?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $return->returned_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 hidden lg:table-cell">{{ $return->reason ? \Illuminate\Support\Str::limit($return->reason, 40) : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ number_format($return->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php $color = $return->statusColor(); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                {{ $return->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center justify-end gap-0.5">
                                {{-- Voir --}}
                                <a href="{{ route('achats.retours-fournisseurs.show', $return) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- PDF Avoir --}}
                                <a href="{{ route('achats.retours-fournisseurs.pdf', $return) }}"
                                   class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors" title="Télécharger l'avoir PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>

                                {{-- Modifier (brouillon seulement) --}}
                                @if($return->isEditable())
                                @can('supplier_returns.create')
                                <a href="{{ route('achats.retours-fournisseurs.edit', $return) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endcan
                                @endif

                                {{-- Valider (brouillon seulement) --}}
                                @if($return->status === 'brouillon')
                                @can('supplier_returns.validate')
                                <form action="{{ route('achats.retours-fournisseurs.validate', $return) }}" method="POST"
                                      onsubmit="return confirm('Valider le retour {{ addslashes($return->number) }} ? Le stock sera ajusté.')">
                                    @csrf
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Valider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                                {{-- Supprimer --}}
                                <form action="{{ route('achats.retours-fournisseurs.destroy', $return) }}" method="POST"
                                      onsubmit="return confirm('Supprimer le retour {{ addslashes($return->number) }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun retour trouvé.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $returns->total() }} retour(s) fournisseur</span>
            @if($returns->hasPages())<div>{{ $returns->appends($filters)->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
