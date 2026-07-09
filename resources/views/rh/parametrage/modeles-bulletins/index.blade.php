@extends('layouts.erp')
@section('title', 'Modèles de bulletins')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Modèles de bulletins</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Modèles de bulletins de paie</h1>
        <p class="text-sm text-gray-500 mt-1">Configurez la mise en page et les sections affichées dans les bulletins PDF.</p>
    </div>
    <a href="{{ route('rh.modeles-bulletins.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouveau modèle
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

@php
$colorClasses = [
    'indigo' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-600'],
    'blue'   => ['bg' => 'bg-blue-100',   'text' => 'text-blue-600',   'dot' => 'bg-blue-500'],
    'green'  => ['bg' => 'bg-green-100',  'text' => 'text-green-600',  'dot' => 'bg-green-500'],
    'red'    => ['bg' => 'bg-red-100',    'text' => 'text-red-600',    'dot' => 'bg-red-500'],
    'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-600', 'dot' => 'bg-orange-500'],
    'teal'   => ['bg' => 'bg-emerald-100',   'text' => 'text-emerald-700',   'dot' => 'bg-emerald-600'],
    'gray'   => ['bg' => 'bg-gray-100',   'text' => 'text-gray-600',   'dot' => 'bg-gray-400'],
];
@endphp

@if($templates->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <div class="w-16 h-16 bg-[#eef5f0] rounded-[4px] flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucun modèle configuré</h3>
    <p class="text-gray-500 text-sm mb-6">Créez votre premier modèle de mise en page pour les bulletins PDF.</p>
    <a href="{{ route('rh.modeles-bulletins.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
        Créer un modèle
    </a>
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    @foreach($templates as $tmpl)
    @php $cc = $colorClasses[$tmpl->primary_color] ?? $colorClasses['gray']; @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden hover:shadow-md transition-shadow flex flex-col">
        {{-- Color band --}}
        <div class="h-1.5 {{ $cc['dot'] }}"></div>
        <div class="p-5 flex-1">
            {{-- Header --}}
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-[4px] {{ $cc['bg'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 {{ $cc['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900 flex items-center gap-2">
                            {{ $tmpl->libelle }}
                            @if($tmpl->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cc['bg'] }} {{ $cc['text'] }}">Par défaut</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 font-mono">{{ $tmpl->code }}</div>
                    </div>
                </div>
                @if(!$tmpl->is_active)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Inactif</span>
                @endif
            </div>

            {{-- Options activées --}}
            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-2">Sections affichées :</p>
                <div class="flex flex-wrap gap-1.5">
                    @if($tmpl->show_logo)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Logo</span>
                    @endif
                    @if($tmpl->show_company_address)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Adresse</span>
                    @endif
                    @if($tmpl->show_employee_photo)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Photo</span>
                    @endif
                    @if($tmpl->show_net_a_payer_box)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">Net à payer</span>
                    @endif
                    @if($tmpl->show_cumuls)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Cumuls</span>
                    @endif
                    @if($tmpl->show_conges_solde)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Congés</span>
                    @endif
                    @if($tmpl->show_cout_employeur)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">Coût employeur</span>
                    @endif
                </div>
            </div>

            {{-- Méta --}}
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span>{{ ucfirst($tmpl->paper_size) }} · {{ $tmpl->orientation === 'portrait' ? 'Portrait' : 'Paysage' }}</span>
                <span>·</span>
                <span>{{ $tmpl->items_count }} bulletin(s) associé(s)</span>
            </div>
        </div>

        {{-- Actions --}}
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-400">{{ $tmpl->active_options_count }}/7 options actives</span>
            <div class="flex items-center gap-2">
                <a href="{{ route('rh.modeles-bulletins.edit', $tmpl) }}"
                   class="p-1.5 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-[4px] transition-colors" title="Modifier">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
                @if(!$tmpl->is_default && $tmpl->items_count === 0)
                <form method="POST" action="{{ route('rh.modeles-bulletins.destroy', $tmpl) }}"
                      onsubmit="return confirm('Supprimer le modèle « {{ $tmpl->libelle }} » ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-[4px] transition-colors" title="Supprimer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
