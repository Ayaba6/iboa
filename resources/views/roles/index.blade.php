@extends('layouts.erp')
@section('title', 'Rôles & Permissions')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rôles & Permissions</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';

    $roleLabels = [
        'super_admin'            => 'Super Administrateur',
        'directeur'              => 'Directeur Général (DG)',
        'daf'                    => 'DAF — Dir. Administratif & Financier',
        'commercial'             => 'Commercial',
        'responsable_commercial' => 'Responsable Commercial',
        'comptable'              => 'Comptable',
        'acheteur'               => 'Acheteur / Approvisionneur',
        'magasinier'             => 'Magasinier',
        'responsable_stock'      => 'Responsable Stock',
        'caissier'               => 'Caissier',
        'chef_production'        => 'Chef de Production',
        'directeur_usine'        => 'Directeur d\'Usine',
        'operateur_production'   => 'Opérateur de Production',
        'responsable_qualite'    => 'Responsable Qualité',
        'technicien_maintenance' => 'Technicien Maintenance',
        'lecture_seule'          => 'Lecture seule',
        'drh'                    => 'Directeur RH',
        'rh_manager'             => 'Gestionnaire RH',
        'rh_agent'               => 'Agent RH',
        'employe'                => 'Employé',
    ];
    $roleCategories = [
        'super_admin' => 'Direction', 'directeur' => 'Direction', 'daf' => 'Direction',
        'commercial' => 'Ventes', 'responsable_commercial' => 'Ventes',
        'comptable' => 'Finance & Trésorerie', 'caissier' => 'Finance & Trésorerie',
        'acheteur' => 'Achats',
        'magasinier' => 'Stock & Logistique', 'responsable_stock' => 'Stock & Logistique',
        'chef_production' => 'Production', 'directeur_usine' => 'Production', 'operateur_production' => 'Production',
        'responsable_qualite' => 'Qualité', 'technicien_maintenance' => 'Maintenance',
        'drh' => 'Ressources humaines', 'rh_manager' => 'Ressources humaines',
        'rh_agent' => 'Ressources humaines', 'employe' => 'Ressources humaines',
        'lecture_seule' => 'Divers',
    ];
    $categoryOrder = ['Direction', 'Ventes', 'Achats', 'Stock & Logistique', 'Production', 'Qualité', 'Maintenance', 'Finance & Trésorerie', 'Ressources humaines', 'Divers'];
    $grouped = $roles->groupBy(fn ($r) => $roleCategories[$r->name] ?? 'Divers')
                     ->sortBy(fn ($g, $cat) => array_search($cat, $categoryOrder) === false ? 99 : array_search($cat, $categoryOrder));
    $totalUsers = $roles->sum('users_count');
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Rôles & Permissions</h1>
            <p class="text-[11.5px] text-gray-500">{{ $roles->count() }} rôles — {{ $totalUsers }} affectation(s) utilisateur</p>
        </div>
        <a href="{{ route('users.index') }}" class="text-[13px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-full transition-colors">Gérer les utilisateurs</a>
    </div>

    {{-- Table SAGE groupée par catégorie --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left w-[34%]">Rôle</th>
                <th class="{{ $th }} text-left">Code technique</th>
                <th class="{{ $th }} text-right w-28">Permissions</th>
                <th class="{{ $th }} text-right w-28">Utilisateurs</th>
                <th class="{{ $th }} text-right w-44">Actions</th>
            </tr></thead>
            <tbody>
                @foreach($grouped as $category => $categoryRoles)
                <tr class="bg-gray-50 border-b border-gray-200">
                    <td colspan="5" class="px-3 py-1 text-[11px] font-bold text-gray-600 uppercase tracking-wider">{{ $category }}</td>
                </tr>
                @foreach($categoryRoles as $role)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('roles.show', $role) }}" class="font-semibold text-emerald-800 hover:underline">{{ $roleLabels[$role->name] ?? ucfirst(str_replace('_', ' ', $role->name)) }}</a>
                        @if($role->name === 'super_admin')
                        <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded-full text-[10.5px] font-semibold bg-purple-100 text-purple-700 align-middle">accès total</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[12px] text-gray-500">{{ $role->name }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $role->permissions_count ? 'text-gray-800' : 'text-gray-400' }}">{{ $role->name === 'super_admin' ? 'toutes' : $role->permissions_count }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">
                        <span class="{{ $role->users_count ? 'font-semibold text-gray-800' : 'text-gray-400' }}">{{ $role->users_count }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        <a href="{{ route('roles.show', $role) }}" class="text-[12px] font-medium text-gray-500 hover:text-emerald-700 hover:underline">Détail</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('roles.edit', $role) }}" class="text-[12px] font-semibold text-emerald-700 hover:underline">Gérer les permissions</a>
                    </td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-[11.5px] text-gray-400 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Les rôles définissent les permissions d'accès aux modules. L'affectation d'un rôle à un utilisateur se fait depuis la fiche utilisateur.
    </p>

</div>
@endsection
