@extends('layouts.erp')
@section('title', $integration->exists ? 'Modifier — ' . $integration->name : 'Nouvelle intégration')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $integration->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-3"
     x-data="{
         showAdvanced: {{ $integration->extra_config ? 'true' : 'false' }},
         provider: '{{ old('provider', $integration->provider ?? '') }}',
         mode: '{{ old('mode', $integration->mode ?? 'sandbox') }}',
         hints: {{ json_encode($providerHints) }},
         get providerHint() { return this.hints[this.provider] ?? null; },
         get sandboxUrl() { return this.providerHint?.sandbox_url ?? ''; },
         get prodUrl() { return this.providerHint?.prod_url ?? ''; },
         validateJson(el) {
             let valid = true;
             if (el.value.trim()) {
                 try { JSON.parse(el.value); } catch(e) { valid = false; }
             }
             el.classList.toggle('border-red-400', !valid);
             el.classList.toggle('border-gray-300', valid);
         },
     }">

    <form method="POST"
        action="{{ $integration->exists ? route('integrations.update', $integration) : route('integrations.store') }}"
        class="space-y-3">
        @csrf
        @if($integration->exists) @method('PUT') @endif

        {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════ --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h1 class="text-[17px] font-bold text-gray-900">
                    {{ $integration->exists ? 'Modifier l\'intégration' : 'Nouvelle intégration externe' }}
                </h1>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $integration->exists ? $integration->name . ' · ' . $integration->provider : 'Connecteur API : paiement mobile, SMS, fiscalité…' }}
                </p>
            </div>
            <div class="flex items-center gap-2 self-start">
                <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                    {{ $integration->exists ? 'Enregistrer' : 'Créer l\'intégration' }}
                </button>
                <a href="{{ $integration->exists ? route('integrations.show', $integration) : route('integrations.index') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                    ✕ Annuler
                </a>
            </div>
        </div>

        {{-- Production warning --}}
        <div x-show="mode === 'production'" x-transition x-cloak
             class="bg-amber-50 border border-amber-300 rounded-[4px] p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-amber-800">Mode Production activé</p>
                <p class="text-xs text-amber-700 mt-0.5">Les transactions seront réelles. Assurez-vous d'utiliser les bons identifiants production avant d'activer.</p>
            </div>
        </div>

        {{-- ══ 1. Informations générales ═════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">1. Informations générales</p>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label for="i-name" class="block text-xs font-medium text-gray-600 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input id="i-name" type="text" name="name" value="{{ old('name', $integration->name) }}"
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="Ex: Orange Money Burkina Faso" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="i-type" class="block text-xs font-medium text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                        <select id="i-type" name="type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 bg-white" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" {{ old('type', $integration->type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="i-provider" class="block text-xs font-medium text-gray-600 mb-1">Fournisseur <span class="text-red-500">*</span></label>
                        <select id="i-provider" name="provider" x-model="provider" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 bg-white" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($providers as $value => $label)
                                <option value="{{ $value }}" {{ old('provider', $integration->provider) == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Provider hint --}}
                <div x-show="providerHint" x-transition x-cloak class="bg-blue-50 border border-blue-200 rounded-[4px] p-3">
                    <p class="text-xs font-semibold text-blue-800 mb-1">Aide pour <span x-text="provider"></span></p>
                    <p x-show="providerHint?.webhook_note" class="text-xs text-blue-700" x-text="providerHint?.webhook_note"></p>
                    <a x-show="providerHint?.doc_url" :href="providerHint?.doc_url" target="_blank" rel="noopener"
                       class="text-xs text-blue-700 hover:underline inline-flex items-center gap-1 mt-1">
                        Documentation officielle ↗
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="i-base-url" class="block text-xs font-medium text-gray-600 mb-1">URL de base (production)</label>
                        <input id="i-base-url" type="url" name="base_url" value="{{ old('base_url', $integration->base_url) }}"
                            :placeholder="prodUrl || 'https://api.provider.com/v1'"
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label for="i-sandbox-url" class="block text-xs font-medium text-gray-600 mb-1">URL de base (sandbox)</label>
                        <input id="i-sandbox-url" type="url" name="sandbox_base_url" value="{{ old('sandbox_base_url', $integration->sandbox_base_url) }}"
                            :placeholder="sandboxUrl || 'https://sandbox.provider.com/v1'"
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="i-mode" class="block text-xs font-medium text-gray-600 mb-1">Mode <span class="text-red-500">*</span></label>
                        <select id="i-mode" name="mode" x-model="mode" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="sandbox">Sandbox (test)</option>
                            <option value="production">Production</option>
                        </select>
                    </div>
                    <div>
                        <label for="i-timeout" class="block text-xs font-medium text-gray-600 mb-1">Timeout (secondes)</label>
                        <input id="i-timeout" type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $integration->timeout_seconds ?? 30) }}"
                            min="5" max="120"
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            {{ old('is_active', $integration->is_active ?? false) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">Activer cette intégration</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="notify_on_error" value="0">
                        <input type="checkbox" name="notify_on_error" value="1"
                            class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            {{ old('notify_on_error', $integration->notify_on_error ?? true) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">Alertes admin en cas d'erreur</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ══ 2. Identifiants API (chiffrés) ════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">2. Identifiants API</p>
                <span class="inline-flex items-center gap-1 text-[10.5px] text-emerald-700 font-medium">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    Chiffrement AES-256 — jamais exposés en clair
                </span>
            </div>
            <div class="p-4 space-y-4">
                @if($integration->exists)
                    <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-2 text-xs text-amber-700">
                        Laissez un champ vide pour conserver la valeur existante. Les champs sont masqués par sécurité.
                    </div>
                @endif

                @php
                    $fieldLabels = [
                        'api_key'        => 'API Key / Merchant Key',
                        'secret_key'     => 'Secret Key',
                        'client_id'      => 'Client ID',
                        'client_secret'  => 'Client Secret',
                        'token'          => 'Token / Bearer',
                        'webhook_secret' => 'Webhook Secret (HMAC)',
                    ];
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($fieldLabels as $field => $label)
                    <div>
                        <label for="i-{{ $field }}" class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" id="i-{{ $field }}" name="{{ $field }}" autocomplete="off"
                                class="w-full border border-gray-300 rounded-[4px] pl-3 pr-9 py-2 text-sm focus:ring-1 focus:ring-emerald-500 font-mono"
                                placeholder="{{ $integration->exists ? '(conservé si vide)' : '' }}">
                            <button type="button" @click="show = !show" :aria-label="show ? 'Masquer' : 'Afficher'"
                                class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══ 3. Configuration avancée (JSON) ═══════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <button type="button" @click="showAdvanced = !showAdvanced"
                class="w-full flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100 hover:bg-[#e3efe7] transition-colors">
                <span class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">3. Configuration avancée (JSON libre)</span>
                <svg :class="showAdvanced ? 'rotate-180' : ''" class="w-4 h-4 text-emerald-700 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="showAdvanced" x-transition x-cloak class="p-4">
                <p class="text-xs text-gray-400 mb-2">
                    JSON libre : <code>sender_id</code>, <code>merchant_id</code>, <code>from_number</code>, <code>token_url</code>, etc.
                    <span x-show="providerHint?.fields?.extra_config" class="ml-1 text-blue-700">
                        Exemple pour <span x-text="provider"></span> : <span x-text="providerHint?.fields?.extra_config ?? '{}'"></span>
                    </span>
                </p>
                <textarea name="extra_config_raw" rows="5" x-ref="jsonField"
                    @blur="validateJson($el)"
                    class="w-full border {{ $errors->has('extra_config_raw') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 transition-colors"
                    placeholder='{"sender_id": "IBOA", "merchant_id": "12345"}'>{{ old('extra_config_raw', $integration->extra_config ? json_encode($integration->extra_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                @error('extra_config_raw')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </form>

    {{-- Suppression — HORS du formulaire principal : un <form> imbriqué est du
         HTML invalide et le bouton « Supprimer » soumettait l'update parent. --}}
    @if($integration->exists)
    <div class="flex justify-end">
        <form method="POST" action="{{ route('integrations.destroy', $integration) }}"
            data-confirm="Supprimer définitivement cette intégration ? Les clés API et l'historique seront perdus."
            data-confirm-title="Supprimer l'intégration">
            @csrf @method('DELETE')
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50 transition-colors">
                Supprimer l'intégration
            </button>
        </form>
    </div>
    @endif

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">Intégrations — {{ $integration->exists ? 'Modification' : 'Création' }}</strong></span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
