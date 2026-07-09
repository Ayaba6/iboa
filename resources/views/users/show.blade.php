@extends('layouts.erp')
@section('title', $user->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('users.index') }}" class="hover:text-gray-700">Utilisateurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $user->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full flex items-center justify-center flex-shrink-0
                    {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-400' }} font-bold text-xl">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div>
                    <h1 class="text-[16px] font-bold text-gray-900">{{ $user->name }}</h1>
                    @if($user->job_title)
                    <p class="text-sm text-gray-500">{{ $user->job_title }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        @foreach($user->roles as $role)
                            @php
                                $roleColors = [
                                    'super_admin' => 'bg-purple-100 text-purple-700',
                                    'directeur'   => 'bg-blue-100 text-blue-700',
                                    'commercial'  => 'bg-green-100 text-green-700',
                                    'comptable'   => 'bg-amber-100 text-amber-700',
                                    'magasinier'  => 'bg-emerald-100 text-emerald-800',
                                ];
                                $color = $roleColors[$role->name] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $color }}">
                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                            </span>
                        @endforeach
                        @if($user->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('users.edit', $user) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>

                @if($user->id !== auth()->id())
                <form action="{{ route('users.toggle', $user) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-[4px] text-sm font-medium transition-colors
                                {{ $user->is_active
                                    ? 'border border-red-300 text-red-600 hover:bg-red-50'
                                    : 'border border-green-300 text-green-600 hover:bg-green-50' }}">
                        @if($user->is_active)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Désactiver
                        @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Activer
                        @endif
                    </button>
                </form>
                @endif

                <a href="{{ route('users.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Coordonnées</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">E-mail</dt>
                    <dd class="font-medium text-gray-900">
                        <a href="mailto:{{ $user->email }}" class="hover:text-emerald-700">{{ $user->email }}</a>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Téléphone</dt>
                    <dd class="font-medium text-gray-900">{{ $user->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Poste</dt>
                    <dd class="font-medium text-gray-900">{{ $user->job_title ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Activité</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Compte créé le</dt>
                    <dd class="font-medium text-gray-900">{{ $user->created_at->format('d/m/Y à H:i') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Dernière connexion</dt>
                    <dd class="font-medium text-gray-900">
                        @if($user->last_login_at)
                            {{ $user->last_login_at->format('d/m/Y à H:i') }}
                            <span class="text-xs text-gray-400 ml-1">({{ $user->last_login_at->diffForHumans() }})</span>
                        @else
                            <span class="text-gray-400">Jamais connecté</span>
                        @endif
                    </dd>
                </div>
                @if($user->last_login_ip)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Dernière IP</dt>
                    <dd class="font-mono text-xs text-gray-700">{{ $user->last_login_ip }}</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-gray-500">Statut</dt>
                    <dd>
                        @if($user->is_active)
                            <span class="text-green-600 font-medium">Actif</span>
                        @else
                            <span class="text-gray-400 font-medium">Inactif</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

    </div>

    {{-- Permissions --}}
    @if($user->getAllPermissions()->count())
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Permissions</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($user->getAllPermissions()->sortBy('name') as $permission)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 font-mono">
                {{ $permission->name }}
            </span>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
