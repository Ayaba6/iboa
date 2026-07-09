@extends('layouts.erp')
@section('title', 'Machines de production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Machines</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Machines de production</h1>
            <p class="text-[12px] text-gray-500">Découpe, profilage — coût horaire &amp; disponibilité</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.machines.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle machine
        </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">Type machine</label>
                <div class="relative"><select name="type" class="{{ $lk }}">
                    <option value="">Tous</option>
                    <option value="decoupe"   @selected(request('type')==='decoupe')>Découpe</option>
                    <option value="profilage" @selected(request('type')==='profilage')>Profilage</option>
                    <option value="mixte"     @selected(request('type')==='mixte')>Mixte</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 sm:col-span-3 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request('type'))
                <a href="{{ route('production.machines.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Code</th>
                        <th class="{{ $th }} text-left">Nom</th>
                        <th class="{{ $th }} text-left">Type</th>
                        <th class="{{ $th }} text-right">Coût horaire</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($machines as $m)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $m->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $m->code }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $m->name }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ ucfirst($m->type) }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-900">{{ number_format($m->hourly_cost, 0, ',', ' ') }} F</td>
                        <td class="px-3 py-1.5 text-center">
                            @php [$sl, $sc] = match($m->status){
                                'active'      => ['Active',      'bg-emerald-100 text-emerald-700'],
                                'maintenance' => ['Maintenance', 'bg-amber-100 text-amber-700'],
                                'en_panne'    => ['En panne',    'bg-red-100 text-red-700'],
                                default       => [ucfirst(str_replace('_', ' ', (string) $m->status)), 'bg-gray-100 text-gray-500'],
                            }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $sl }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.machines.edit', $m) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.machines.destroy', $m) }}" class="inline ml-2" data-confirm="Supprimer cette machine ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune machine. Créez-en une.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $machines->total() }} machine(s)</span>
            @if($machines->hasPages())<div>{{ $machines->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
