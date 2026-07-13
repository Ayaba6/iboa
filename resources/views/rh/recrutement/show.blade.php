@extends('layouts.erp')
@section('title', 'Besoin — '.$recruitment->title)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.recrutement.index') }}" class="hover:text-gray-700">Recrutement</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $recruitment->title }}</span>
@endsection

@section('content')
@php
    $badge = ['recu'=>'text-gray-500','preselectionne'=>'text-blue-700','entretien'=>'text-amber-700','retenu'=>'text-indigo-700','rejete'=>'text-red-600','embauche'=>'text-emerald-700'];
    $hired = $recruitment->candidates->where('status','embauche')->count();
@endphp

<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $recruitment->title }}</h1>
            <p class="text-sm text-gray-500">
                {{ \App\Models\Recruitment::CONTRACT_TYPES[$recruitment->contract_type] ?? $recruitment->contract_type }}
                · {{ $recruitment->department?->name ?? 'Sans département' }}
                · {{ $recruitment->positions_count }} poste(s) · {{ $hired }} embauché(s)
                · <span class="font-semibold">{{ $recruitment->statusLabel() }}</span>
            </p>
        </div>
        @can('rh.employees.manage')
        <a href="{{ route('rh.recrutement.edit', $recruitment) }}" class="border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-[4px] hover:bg-gray-50">Modifier</a>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2">{{ session('error') }}</div>@endif

    @if($recruitment->description || $recruitment->requirements)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @if($recruitment->description)<div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-[11px] font-semibold text-gray-400 uppercase mb-1">Description</p><p class="text-sm text-gray-700 whitespace-pre-line">{{ $recruitment->description }}</p></div>@endif
        @if($recruitment->requirements)<div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-[11px] font-semibold text-gray-400 uppercase mb-1">Profil recherché</p><p class="text-sm text-gray-700 whitespace-pre-line">{{ $recruitment->requirements }}</p></div>@endif
    </div>
    @endif

    {{-- Ajout candidat --}}
    @can('rh.employees.manage')
    <div class="bg-[#eef5f0] text-emerald-900 rounded-[4px] px-4 py-2 text-sm font-semibold">Ajouter un candidat</div>
    <form method="POST" action="{{ route('rh.recrutement.candidates.store', $recruitment) }}" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
        @csrf
        <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Prénom *</label><input name="first_name" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]" required></div>
        <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Nom *</label><input name="last_name" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]" required></div>
        <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Email</label><input name="email" type="email" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]"></div>
        <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Téléphone</label><input name="phone" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]"></div>
        <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Source</label><input name="source" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]" placeholder="Annonce, cooptation…"></div>
        <div><button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold h-8 rounded-[3px]">+ Ajouter</button></div>
    </form>
    @endcan

    {{-- Pipeline candidats --}}
    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Candidat</th>
                    <th class="px-3 py-2 text-left">Contact</th>
                    <th class="px-3 py-2 text-left">Source</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                    <th class="px-3 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recruitment->candidates as $c)
                <tr class="{{ $c->status === 'embauche' ? 'bg-emerald-50/40' : '' }}">
                    <td class="px-3 py-1.5 font-medium">{{ $c->full_name }}</td>
                    <td class="px-3 py-1.5 text-gray-600 text-xs">{{ $c->email ?? '—' }}{{ $c->phone ? ' · '.$c->phone : '' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $c->source ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="{{ $badge[$c->status] ?? 'text-gray-500' }} text-xs font-semibold">{{ $c->statusLabel() }}</span></td>
                    <td class="px-3 py-1.5 text-right">
                        @can('rh.employees.manage')
                        @if($c->status !== 'embauche')
                        <form method="POST" action="{{ route('rh.recrutement.candidates.advance', [$recruitment, $c]) }}" class="inline-flex items-center gap-1 justify-end">@csrf
                            <select name="status" class="h-7 py-0 px-1 border border-gray-300 rounded-[3px] text-xs">
                                @foreach(\App\Models\JobCandidate::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected($c->status===$k)>{{ $lbl }}</option>@endforeach
                            </select>
                            <button class="text-emerald-700 hover:underline text-xs font-semibold"
                                    onclick="return this.form.status.value!=='embauche' || confirm('Embaucher {{ $c->full_name }} ? Une fiche salarié sera créée.');">OK</button>
                        </form>
                        @else
                        <span class="text-emerald-700 text-xs">✓ Fiche #{{ $c->hired_employee_id }}</span>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun candidat pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Candidats : <span class="text-white font-semibold">{{ $recruitment->candidates->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
