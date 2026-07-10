@extends('layouts.erp')
@section('title', 'Demandes d\'achat')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Demandes d'achat</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Total demandes</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Soumises</p>
            <p class="text-[16px] font-bold text-blue-600 tabular-nums">{{ $summary['pending'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Approuvées</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $summary['approved'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Brouillons</p>
            <p class="text-[16px] font-bold text-gray-500 tabular-nums">{{ $summary['draft'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Demandes d'achat</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $requests->total() }} demande(s)</p>
        </div>
        @can('purchase_requests.create')
        <a href="{{ route('achats.demandes-achat.create') }}"
           class="bg-amber-600 hover:bg-amber-700 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle demande
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, demandeur..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">

            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="soumis"    {{ ($filters['status'] ?? '') === 'soumis'    ? 'selected' : '' }}>Soumis</option>
                <option value="approuve"  {{ ($filters['status'] ?? '') === 'approuve'  ? 'selected' : '' }}>Approuvé</option>
                <option value="rejete"    {{ ($filters['status'] ?? '') === 'rejete'    ? 'selected' : '' }}>Rejeté</option>
                <option value="converti"  {{ ($filters['status'] ?? '') === 'converti'  ? 'selected' : '' }}>Converti</option>
            </select>

            <input type="text" name="department" value="{{ $filters['department'] ?? '' }}" placeholder="Département..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'department']))
                <a href="{{ route('achats.demandes-achat.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono, workflow soumission/approbation --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide w-36">N° demande</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden md:table-cell">Demandeur</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden lg:table-cell w-32">Département</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden lg:table-cell w-32">Date souhaitée</th>
                        <th class="text-right font-bold px-3 py-2 uppercase tracking-wide w-32">Montant estimé</th>
                        <th class="text-center font-bold px-3 py-2 uppercase tracking-wide w-28">Statut</th>
                        <th class="px-3 py-2 w-28"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('achats.demandes-achat.show', $req) }}" class="hover:underline font-semibold">{{ $req->number }}</a>
                            @if($req->justification)
                            <p class="text-[11px] text-gray-400 font-sans">{{ \Illuminate\Support\Str::limit($req->justification, 35) }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-700 hidden md:table-cell">{{ $req->requestedBy?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 hidden lg:table-cell">{{ $req->department ?: '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden lg:table-cell whitespace-nowrap">
                            @if($req->needed_at)
                                @php $urgent = $req->needed_at->isPast() && !in_array($req->status, ['approuve','converti','annule']); @endphp
                                <span class="{{ $urgent ? 'text-red-600 font-medium' : '' }}">{{ $req->needed_at->format('d/m/Y') }}</span>
                                @if($urgent)<span class="ml-1 inline-flex px-1.5 py-0.5 rounded-[3px] text-[10px] font-bold bg-red-100 text-red-700">URGENT</span>@endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">
                            {{ $req->total_estimated > 0 ? number_format($req->total_estimated, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @php $color = $req->statusColor(); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                {{ $req->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('achats.demandes-achat.show', $req) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @if($req->status === 'brouillon')
                                @can('purchase_requests.submit')
                                <form action="{{ route('achats.demandes-achat.submit', $req) }}" method="POST"
                                      onsubmit="return confirm('Soumettre la demande {{ addslashes($req->number) }} pour approbation ?')">
                                    @csrf
                                    <button type="submit" class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Soumettre">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                                @endif
                                @if($req->status === 'soumis')
                                @can('purchase_requests.approve')
                                <form action="{{ route('achats.demandes-achat.approve', $req) }}" method="POST"
                                      onsubmit="return confirm('Approuver la demande {{ addslashes($req->number) }} ?')">
                                    @csrf
                                    <button type="submit" class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Approuver">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                                @endif
                                @if($req->isEditable())
                                @can('purchase_requests.create')
                                <a href="{{ route('achats.demandes-achat.edit', $req) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endcan
                                <form action="{{ route('achats.demandes-achat.destroy', $req) }}" method="POST"
                                      onsubmit="return confirm('Supprimer cette demande ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
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
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune demande d'achat trouvée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $requests->total() }} demande(s) d'achat</span>
            @if($requests->hasPages())<div>{{ $requests->appends($filters)->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
