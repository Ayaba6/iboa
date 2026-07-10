@extends('layouts.erp')
@section('title', 'Numérotation des bulletins')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Numérotation des bulletins</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Numérotation des bulletins</h1>
        <p class="text-sm text-gray-500 mt-1">Définissez le format et la séquence des numéros de bulletins de paie.</p>
    </div>
    <a href="{{ route('rh.numerotation.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouvelle règle
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

{{-- Info atomicité --}}
<div class="mb-6 bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-700 flex gap-3">
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
        <strong class="font-semibold">Numérotation atomique garantie.</strong>
        Même en cas de génération simultanée de plusieurs bulletins, chaque numéro est unique grâce au verrouillage transactionnel des compteurs de séquence.
    </div>
</div>

@if($numberings->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <div class="w-16 h-16 bg-[#eef5f0] rounded-[4px] flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
        </svg>
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucune règle configurée</h3>
    <p class="text-gray-500 text-sm mb-6">Créez votre première règle de numérotation pour les bulletins de paie.</p>
    <a href="{{ route('rh.numerotation.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
        Créer une règle
    </a>
</div>
@else
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="w-full divide-y divide-gray-100">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Règle</th>
                <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Format exemple</th>
                <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réinit.</th>
                <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Séquences</th>
                <th class="px-6 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($numberings as $num)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-3 py-1.5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-[4px] bg-[#eef5f0] flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 flex items-center gap-2">
                                {{ $num->libelle }}
                                @if($num->is_default)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">Par défaut</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 font-mono">{{ $num->code }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-3 py-1.5">
                    <span class="font-mono text-sm text-gray-700 bg-gray-100 px-2 py-1 rounded">
                        {{ $num->format_example }}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-600">
                    @php
                        $resetLabels = ['year' => 'Annuelle', 'month' => 'Mensuelle', 'never' => 'Jamais'];
                    @endphp
                    {{ $resetLabels[$num->reset_on] ?? $num->reset_on }}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-600">
                    {{ $num->sequences_count }} période(s)
                </td>
                <td class="px-3 py-1.5">
                    @if($num->is_active)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Actif
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactif
                    </span>
                    @endif
                </td>
                <td class="px-3 py-1.5">
                    <div class="flex items-center gap-2 justify-end">
                        <a href="{{ route('rh.numerotation.edit', $num) }}"
                           class="p-1.5 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-[4px] transition-colors" title="Modifier">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        @if(!$num->is_default)
                        <form method="POST" action="{{ route('rh.numerotation.destroy', $num) }}"
                              onsubmit="return confirm('Supprimer la règle « {{ $num->libelle }} » ? Toutes ses séquences seront supprimées.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-[4px] transition-colors" title="Supprimer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            {{-- Séquences détail (collapsible) --}}
            @if($num->sequences_count > 0)
            <tr x-data="{ open: false }">
                <td colspan="6" class="px-6 py-0">
                    <button @click="open = !open"
                            class="w-full text-left text-xs text-gray-400 hover:text-emerald-700 py-2 flex items-center gap-1">
                        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Voir les compteurs de séquence
                    </button>
                    <div x-show="open" x-cloak class="pb-3">
                        <table class="w-full text-xs text-gray-600 bg-gray-50 rounded-[4px] overflow-hidden">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-3 py-1.5 text-left font-semibold">Période</th>
                                    <th class="px-3 py-1.5 text-right font-semibold">Dernier n°</th>
                                    <th class="px-3 py-1.5 text-right font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($num->sequences as $seq)
                                <tr>
                                    <td class="px-3 py-1.5 font-mono">{{ $seq->period_key }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-semibold">{{ str_pad($seq->last_seq, $num->seq_length, '0', STR_PAD_LEFT) }}</td>
                                    <td class="px-3 py-1.5 text-right">
                                        <form method="POST" action="{{ route('rh.numerotation.reset-sequence', $num) }}"
                                              onsubmit="return confirm('Réinitialiser le compteur pour la période {{ $seq->period_key }} ? Les prochains bulletins reprendront à 0001.')">
                                            @csrf
                                            <input type="hidden" name="period_key" value="{{ $seq->period_key }}">
                                            <button type="submit" class="text-amber-600 hover:underline">Réinitialiser</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
