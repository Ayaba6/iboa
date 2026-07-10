@extends('layouts.erp')
@section('title', 'Rôle '.ucfirst(str_replace('_',' ',$role->name)))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('roles.index') }}" class="hover:text-gray-700">Rôles & Permissions</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ ucfirst(str_replace('_',' ',$role->name)) }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    @php
        $roleLabels = [
            'super_admin'=>'Super Administrateur','directeur'=>'Directeur Général (DG)',
            'daf'=>'DAF — Dir. Administratif & Financier',
            'commercial'=>'Commercial','responsable_commercial'=>'Resp. Commercial',
            'comptable'=>'Comptable','acheteur'=>'Acheteur / Approvisionneur',
            'magasinier'=>'Magasinier','responsable_stock'=>'Resp. Stock','caissier'=>'Caissier',
            'chef_production'=>'Chef de Production','directeur_usine'=>"Directeur d'Usine",
            'operateur_production'=>'Opérateur de Production',
            'responsable_qualite'=>'Responsable Qualité',
            'technicien_maintenance'=>'Technicien Maintenance',
            'lecture_seule'=>'Lecture seule',
            'drh'=>'Directeur RH','rh_manager'=>'Gestionnaire RH','rh_agent'=>'Agent RH','employe'=>'Employé',
        ];
        $isRhRole = in_array($role->name, ['drh','rh_manager','rh_agent','employe']);
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            @if($isRhRole)
            <p class="text-xs font-medium text-rose-500 mb-1 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Module Ressources Humaines
            </p>
            @endif
            <h1 class="text-[16px] font-bold text-gray-900">{{ $roleLabels[$role->name] ?? ucfirst(str_replace('_',' ',$role->name)) }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $role->permissions->count() }} permissions &bull; {{ $users->count() }} utilisateur(s)</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('roles.edit', $role) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Modifier les permissions
            </a>
            <a href="{{ route('roles.index') }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                Retour
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Permissions --}}
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Permissions accordées</h2>
            @php $rolePerms = $role->permissions->pluck('name')->toArray(); @endphp
            @foreach($grouped as $module => $permissions)
            @php $moduleGranted = $permissions->filter(fn($p) => in_array($p->name, $rolePerms)); @endphp
            @if($moduleGranted->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 p-4">
                <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-3">{{ $module }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($moduleGranted as $perm)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-[4px] text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $perm->name }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach

            {{-- Permissions refusées --}}
            @php
                $allPerms = collect($grouped)->flatten()->pluck('name')->toArray();
                $denied   = array_diff($allPerms, $rolePerms);
            @endphp
            @if(!empty($denied))
            <div class="bg-white rounded-[4px] border border-gray-300 p-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Non accordées</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($denied as $p)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-[4px] text-xs font-medium bg-gray-50 text-gray-400 border border-gray-200">
                        {{ $p }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Utilisateurs --}}
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Utilisateurs avec ce rôle</h2>
            <div class="bg-white rounded-[4px] border border-gray-300 divide-y divide-gray-100">
                @forelse($users as $user)
                <div class="flex items-center gap-3 px-3 py-1.5">
                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center
                        {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-400' }} font-bold text-xs">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <a href="{{ route('users.show', $user) }}" class="text-sm font-medium text-gray-900 hover:text-emerald-700 truncate block">
                            {{ $user->name }}
                        </a>
                        <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                    </div>
                    @if(!$user->is_active)
                    <span class="ml-auto text-xs text-gray-400 flex-shrink-0">Inactif</span>
                    @endif
                </div>
                @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400">
                    Aucun utilisateur avec ce rôle.
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
