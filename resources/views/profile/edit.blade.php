@extends('layouts.erp')
@section('title', 'Mon profil')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium dark:text-gray-100">Mon profil</span>
@endsection

@section('content')
@php
    $card = 'bg-white border border-gray-300 rounded-[4px] overflow-hidden dark:bg-[#1A1D27] dark:border-white/10';
    $secH = 'px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0] dark:border-white/10 dark:bg-white/5';
    $lbl  = 'block text-[11px] font-semibold text-gray-600 mb-1 dark:text-gray-400';
    $inp  = 'w-full h-9 border border-gray-300 rounded-[4px] px-3 text-[13px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500 dark:bg-[#12141b] dark:border-white/10 dark:text-gray-100';
    $btn  = 'text-[12.5px] font-semibold px-4 py-1.5 rounded-[4px] transition-colors';
    $err  = 'mt-1 text-[11px] text-red-600 dark:text-red-400';
@endphp

<div class="space-y-3">

    {{-- Bandeau titre --}}
    <div>
        <h1 class="text-[16px] font-bold text-gray-900 dark:text-gray-100">Mon profil</h1>
        <p class="text-[11.5px] text-gray-400 dark:text-gray-500">Gérez vos informations personnelles et la sécurité de votre compte.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 items-start">

        {{-- Colonne gauche : profil + mot de passe --}}
        <div class="lg:col-span-2 space-y-3">

            {{-- ── Informations du profil ── --}}
            <div class="{{ $card }}">
                <div class="{{ $secH }}"><h2 class="text-[13px] font-bold text-emerald-900 dark:text-emerald-300">Informations du profil</h2></div>
                <form id="send-verification" method="POST" action="{{ route('verification.send') }}">@csrf</form>
                <form method="POST" action="{{ route('profile.update') }}" class="p-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="name" class="{{ $lbl }}">Nom complet</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" class="{{ $inp }}">
                            @error('name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="{{ $lbl }}">Adresse e-mail</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username" class="{{ $inp }}">
                            @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                        <div class="p-2.5 bg-amber-50 border border-amber-200 rounded-[4px] dark:bg-amber-400/10 dark:border-amber-400/30">
                            <p class="text-[11.5px] text-amber-700 dark:text-amber-200">Votre adresse e-mail n'est pas vérifiée.
                                <button form="send-verification" class="underline font-semibold hover:text-amber-900 ml-1 dark:hover:text-amber-100">Renvoyer l'e-mail</button>
                            </p>
                            @if (session('status') === 'verification-link-sent')
                                <p class="mt-1 text-[11.5px] font-semibold text-green-600 dark:text-green-400">Un nouveau lien de vérification a été envoyé.</p>
                            @endif
                        </div>
                    @endif

                    {{-- [CDC §Workflow] Canal e-mail pour les notifications de validation --}}
                    <label class="flex items-start gap-2.5 cursor-pointer rounded-[4px] border border-gray-200 p-2.5 dark:border-white/10">
                        <input type="hidden" name="notify_by_email" value="0">
                        <input type="checkbox" name="notify_by_email" value="1" {{ old('notify_by_email', $user->notify_by_email) ? 'checked' : '' }}
                               class="mt-0.5 w-4 h-4 rounded text-emerald-700 border-gray-300 focus:ring-emerald-500 dark:bg-[#12141b] dark:border-white/20">
                        <span>
                            <span class="block text-[12.5px] font-semibold text-gray-900 dark:text-gray-100">Notifications de validation par e-mail</span>
                            <span class="block text-[11px] text-gray-500 mt-0.5 dark:text-gray-400">Les notifications internes (cloche) restent toujours actives. Cette option envoie en plus un e-mail pour chaque document soumis à votre validation.</span>
                        </span>
                    </label>

                    <div class="flex items-center gap-3 pt-0.5">
                        <button type="submit" class="{{ $btn }} bg-emerald-700 hover:bg-emerald-800 text-white">Enregistrer</button>
                        @if (session('status') === 'profile-updated')
                            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-[12px] text-emerald-600 font-semibold">✓ Modifications enregistrées.</p>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── Modifier le mot de passe ── --}}
            <div id="password" class="{{ $card }} scroll-mt-20">
                <div class="{{ $secH }}"><h2 class="text-[13px] font-bold text-emerald-900 dark:text-emerald-300">Modifier le mot de passe</h2></div>
                <form method="POST" action="{{ route('password.update') }}" class="p-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="update_password_current_password" class="{{ $lbl }}">Mot de passe actuel</label>
                        <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" class="{{ $inp }} sm:max-w-sm">
                        @error('current_password', 'updatePassword')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="update_password_password" class="{{ $lbl }}">Nouveau mot de passe</label>
                            <input id="update_password_password" name="password" type="password" autocomplete="new-password" class="{{ $inp }}">
                            @error('password', 'updatePassword')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="update_password_password_confirmation" class="{{ $lbl }}">Confirmer le mot de passe</label>
                            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $inp }}">
                            @error('password_confirmation', 'updatePassword')<p class="{{ $err }}">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-0.5">
                        <button type="submit" class="{{ $btn }} bg-emerald-700 hover:bg-emerald-800 text-white">Mettre à jour</button>
                        @if (session('status') === 'password-updated')
                            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-[12px] text-emerald-600 font-semibold">✓ Mot de passe mis à jour.</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Colonne droite : compte + zone de danger --}}
        <div class="space-y-3">
            <div class="{{ $card }}">
                <div class="{{ $secH }}"><h2 class="text-[13px] font-bold text-emerald-900 dark:text-emerald-300">Compte</h2></div>
                <div class="p-4 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-[15px] font-bold text-white"
                         style="background:linear-gradient(135deg,#6366F1,#8B5CF6);">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[13px] font-bold text-gray-900 truncate dark:text-gray-100">{{ $user->name }}</p>
                        <p class="text-[11px] text-gray-400 truncate dark:text-gray-500">{{ $user->getRoleNames()->first() ?? 'Utilisateur' }}</p>
                    </div>
                </div>
            </div>

            <div class="{{ $card }} !border-red-200 dark:!border-red-500/30" x-data="{ open: false }">
                <div class="px-3 py-1.5 border-b border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10"><h2 class="text-[13px] font-bold text-red-700 dark:text-red-300">Zone de danger</h2></div>
                <div class="p-4">
                    <p class="text-[11.5px] text-gray-500 mb-3 dark:text-gray-400">Une fois votre compte supprimé, toutes ses données seront définitivement effacées.</p>
                    <button type="button" @click="open = true" class="{{ $btn }} bg-red-600 hover:bg-red-700 text-white">Supprimer le compte</button>

                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                        <div class="relative bg-white rounded-[4px] shadow-xl w-full max-w-md p-5 z-10 dark:bg-[#1A1D27] dark:border dark:border-white/10"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-red-100 flex items-center justify-center dark:bg-red-500/15">
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-[14px] font-bold text-gray-900 dark:text-gray-100">Confirmer la suppression</h3>
                                    <p class="text-[12px] text-gray-500 mt-0.5 dark:text-gray-400">Action irréversible. Entrez votre mot de passe pour confirmer.</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-3">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <label for="del_password" class="{{ $lbl }}">Mot de passe</label>
                                    <input id="del_password" name="password" type="password" placeholder="Votre mot de passe actuel"
                                           class="w-full h-9 border border-gray-300 rounded-[4px] px-3 text-[13px] focus:outline-none focus:ring-1 focus:ring-red-400 focus:border-red-500 dark:bg-[#12141b] dark:border-white/10 dark:text-gray-100">
                                    @error('password', 'userDeletion')<p class="{{ $err }}">{{ $message }}</p>@enderror
                                </div>
                                <div class="flex justify-end gap-2 pt-0.5">
                                    <button type="button" @click="open = false" class="{{ $btn }} border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-white/5">Annuler</button>
                                    <button type="submit" class="{{ $btn }} bg-red-600 hover:bg-red-700 text-white">Supprimer définitivement</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
