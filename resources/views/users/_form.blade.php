@php $user ??= null; @endphp

<div class="space-y-3">

    {{-- Informations personnelles --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3">
        <h2 class="text-base font-semibold text-gray-900">Informations personnelles</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Nom complet <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user?->name) }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Adresse e-mail <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user?->email) }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                <input type="text" name="phone" value="{{ old('phone', $user?->phone) }}"
                       placeholder="+226 XX XX XX XX"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Poste / Fonction</label>
                <input type="text" name="job_title" value="{{ old('job_title', $user?->job_title) }}"
                       placeholder="Ex: Directeur commercial"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                @error('job_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Rôle <span class="text-red-500">*</span></label>
                <select name="role" required
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="">Sélectionner un rôle...</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}"
                            {{ old('role', $user?->roles->first()?->name) === $role->name ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                        </option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-5">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                           {{ old('is_active', $user ? ($user->is_active ? '1' : '0') : '1') == '1' ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-1 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-700"></div>
                </label>
                <span class="text-sm text-gray-700">Compte actif</span>
            </div>

        </div>
    </div>

    {{-- Mot de passe --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
        <h2 class="text-base font-semibold text-gray-900">
            Mot de passe
            @if($user)
                <span class="text-xs font-normal text-gray-400 ml-2">Laisser vide pour ne pas modifier</span>
            @endif
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Mot de passe @if(!$user)<span class="text-red-500">*</span>@endif
                </label>
                <input type="password" name="password" autocomplete="new-password"
                       {{ !$user ? 'required' : '' }}
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Confirmation @if(!$user)<span class="text-red-500">*</span>@endif
                </label>
                <input type="password" name="password_confirmation" autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
        </div>
        @if(!$user)
        <p class="text-xs text-gray-400">Minimum 8 caractères.</p>
        @endif
    </div>

</div>
