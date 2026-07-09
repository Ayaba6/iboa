<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Informations du profil') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Mettez à jour les informations de votre compte et votre adresse e-mail.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-3">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Nom complet')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Adresse e-mail')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Votre adresse e-mail n\'est pas vérifiée.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-1 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-gray-800">
                            {{ __('Cliquez ici pour renvoyer l\'e-mail de vérification.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('Un nouveau lien de vérification a été envoyé à votre adresse e-mail.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- [CDC §Workflow] Canal email optionnel pour les notifications de validation --}}
        <div class="rounded-[4px] border border-gray-200 dark:border-gray-700 p-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="notify_by_email" value="0">
                <input type="checkbox" name="notify_by_email" value="1"
                       {{ old('notify_by_email', $user->notify_by_email) ? 'checked' : '' }}
                       class="mt-0.5 w-4 h-4 rounded text-emerald-700 border-gray-300 focus:ring-emerald-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Recevoir les notifications de validation par email</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Les notifications internes ERP (cloche) restent toujours actives. Cette option envoie en plus un email pour chaque document soumis à votre validation, relance ou retour de validation.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Enregistré.') }}</p>
            @endif
        </div>
    </form>
</section>
