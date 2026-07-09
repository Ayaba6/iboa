@extends('layouts.erp')
@section('title', 'Journal d\'activité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Journal d'activité</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Journal d'activité</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ number_format($logs->total()) }} entrée(s) enregistrée(s)</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Utilisateur, modèle, URL..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

            <select name="action" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Toutes les actions</option>
                @foreach($actions as $action)
                    @php
                        $labels = [
                            'created'   => 'Créé',
                            'updated'   => 'Modifié',
                            'deleted'   => 'Supprimé',
                            'login'     => 'Connexion',
                            'logout'    => 'Déconnexion',
                            'validated' => 'Validé',
                            'sent'      => 'Envoyé',
                            'exported'  => 'Exporté',
                        ];
                    @endphp
                    <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                        {{ $labels[$action] ?? ucfirst($action) }}
                    </option>
                @endforeach
            </select>

            <select name="user_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les utilisateurs</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                   placeholder="Du">

            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                   placeholder="Au">
        </div>
        <div class="flex gap-2 mt-3">
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['search','action','user_id','date_from','date_to']))
            <a href="{{ route('audit.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">
                Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date & heure</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Utilisateur</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Action</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Objet</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Modifications</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                    @php
                        $colorMap = [
                            'created'   => 'bg-green-100 text-green-700',
                            'updated'   => 'bg-blue-100 text-blue-700',
                            'deleted'   => 'bg-red-100 text-red-700',
                            'login'     => 'bg-emerald-100 text-emerald-800',
                            'logout'    => 'bg-gray-100 text-gray-600',
                            'validated' => 'bg-purple-100 text-purple-700',
                            'sent'      => 'bg-sky-100 text-sky-700',
                            'exported'  => 'bg-amber-100 text-amber-700',
                        ];
                        $badgeClass = $colorMap[$log->action] ?? 'bg-gray-100 text-gray-600';
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        {{-- Date --}}
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <span class="text-gray-900 font-medium">{{ $log->created_at->format('d/m/Y') }}</span>
                            <span class="text-gray-400 ml-1">{{ $log->created_at->format('H:i:s') }}</span>
                        </td>

                        {{-- Utilisateur --}}
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                    {{ strtoupper(substr($log->user_name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-gray-700">{{ $log->user_name ?? '—' }}</span>
                            </div>
                        </td>

                        {{-- Action --}}
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $badgeClass }}">
                                {{ $log->actionLabel() }}
                            </span>
                        </td>

                        {{-- Objet --}}
                        <td class="px-3 py-1.5">
                            @if($log->model_type)
                            <span class="text-gray-700 font-medium">{{ $log->modelLabel() }}</span>
                            @if($log->model_id)
                                <span class="text-gray-400 ml-1">#{{ $log->model_id }}</span>
                            @endif
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Modifications --}}
                        <td class="px-3 py-1.5 hidden lg:table-cell max-w-xs">
                            @if($log->old_values || $log->new_values)
                            <div x-data="{ open: false }">
                                <button @click="open = !open"
                                        class="text-xs text-emerald-700 hover:text-emerald-900 underline underline-offset-2">
                                    Voir les détails
                                </button>
                                <div x-show="open" x-cloak class="mt-2 space-y-1 text-xs">
                                    @if($log->old_values)
                                    <div class="bg-red-50 border border-red-100 rounded p-2">
                                        <p class="font-semibold text-red-600 mb-1">Avant :</p>
                                        @foreach($log->old_values as $key => $val)
                                            <p class="text-red-700">
                                                <span class="font-medium">{{ $key }}</span> :
                                                {{ is_array($val) ? implode(', ', $val) : $val }}
                                            </p>
                                        @endforeach
                                    </div>
                                    @endif
                                    @if($log->new_values)
                                    <div class="bg-green-50 border border-green-100 rounded p-2">
                                        <p class="font-semibold text-green-600 mb-1">Après :</p>
                                        @foreach($log->new_values as $key => $val)
                                            <p class="text-green-700">
                                                <span class="font-medium">{{ $key }}</span> :
                                                {{ is_array($val) ? implode(', ', $val) : $val }}
                                            </p>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @else
                            <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>

                        {{-- IP --}}
                        <td class="px-3 py-1.5 text-gray-400 text-xs hidden xl:table-cell whitespace-nowrap">
                            {{ $log->ip_address ?? '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucune entrée dans le journal.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
