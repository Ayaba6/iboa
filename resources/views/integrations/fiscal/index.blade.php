@extends('layouts.erp')

@section('title', 'Export Fiscal — DGI Burkina Faso')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.show', $integration) }}" class="hover:text-gray-700">{{ $integration->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Export Fiscal DGI</span>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- En-tête -------------------------------------------------------------}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Export Fiscal — DGI Burkina Faso</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $integration->name }}
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $integration->mode === 'sandbox' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                        {{ $integration->mode === 'sandbox' ? '🧪 Sandbox' : '🚀 Production' }}
                    </span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Exports conformes DGI BF — formats CSV, XML, JSON acceptés par e-SINTAX</span>
        </div>
    </div>

    {{-- Alertes -------------------------------------------------------------}}
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 text-sm text-red-700 dark:text-red-300">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4 text-sm text-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- Info DGI BF ---------------------------------------------------------}}
    <div class="rounded-[4px] border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 p-4">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                <p class="font-semibold">Plateformes fiscales DGI Burkina Faso</p>
                <p>• <strong>e-SINTAX</strong> (Système Informatisé National de Taxation) — télédéclaration TVA, IS, patente</p>
                <p>• <strong>Portail DGI BF</strong> : <a href="https://impots.gov.bf" target="_blank" class="underline hover:text-blue-900 dark:hover:text-blue-100">impots.gov.bf</a></p>
                <p>• TVA standard BF : <strong>18 %</strong> — délai de déclaration : le 20 du mois suivant</p>
                <p>• Format FEC (Fichier des Écritures Comptables) supporté pour les journaux SYSCOHADA</p>
            </div>
        </div>
    </div>

    {{-- Grille 3 panneaux d'export -----------------------------------------}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- 1. Déclaration TVA ---------------------------------------------}}
        <div class="rounded-[4px] border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-700 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h2 class="font-semibold text-gray-900 dark:text-white text-sm">Déclaration TVA</h2>
            </div>
            <div class="p-5">
                {{-- Dernières déclarations ---------------------------------}}
                @if ($lastDeclarations->isNotEmpty())
                    <div class="mb-4 space-y-1.5">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Dernières périodes</p>
                        @foreach ($lastDeclarations as $decl)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-700 dark:text-gray-300">{{ $decl->period_label ?? $decl->period_start?->format('M Y') }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">{{ number_format(($decl->tva_due ?? 0)/100, 0, ',', ' ') }} XOF</span>
                                    <span class="px-1.5 py-0.5 rounded text-xs font-medium
                                        {{ $decl->status === 'valide' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                           ($decl->status === 'soumis' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                                           'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400') }}">
                                        {{ $decl->status }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-700 my-4"></div>
                @endif

                <form method="POST" action="{{ route('integrations.fiscal.tva', $integration) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Début période</label>
                            <input type="date" name="period_start" required
                                   value="{{ old('period_start', now()->startOfMonth()->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fin période</label>
                            <input type="date" name="period_end" required
                                   value="{{ old('period_end', now()->endOfMonth()->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Format d'export</label>
                        <select name="format" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="csv">📄 CSV — e-SINTAX (recommandé)</option>
                            <option value="xml">🗂 XML — SINTAX v2</option>
                            <option value="json">{ } JSON — API DGI BF</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 py-2.5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Télécharger déclaration TVA
                    </button>
                </form>
            </div>
        </div>

        {{-- 2. Export Factures ---------------------------------------------}}
        <div class="rounded-[4px] border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-700 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h2 class="font-semibold text-gray-900 dark:text-white text-sm">Export Factures</h2>
            </div>
            <div class="p-5">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Exporte les factures de vente et/ou d'achat avec NIF/IFU, montants HT/TVA/TTC —
                    conforme aux exigences DGI BF pour le contrôle fiscal.
                </p>
                <form method="POST" action="{{ route('integrations.fiscal.factures', $integration) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date début</label>
                            <input type="date" name="date_from" required
                                   value="{{ old('date_from', now()->startOfMonth()->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date fin</label>
                            <input type="date" name="date_to" required
                                   value="{{ old('date_to', now()->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Type de factures</label>
                        <select name="type" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="vente">🧾 Factures de vente (clients)</option>
                            <option value="achat">📦 Factures d'achat (fournisseurs)</option>
                            <option value="all">📋 Toutes (ventes + achats)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Format d'export</label>
                        <select name="format" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="csv">📄 CSV — e-SINTAX (recommandé)</option>
                            <option value="xml">🗂 XML — DGI BF</option>
                            <option value="json">{ } JSON — API</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 py-2.5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Télécharger export factures
                    </button>
                </form>
            </div>
        </div>

        {{-- 3. Export Journal / FEC ----------------------------------------}}
        <div class="rounded-[4px] border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h2 class="font-semibold text-gray-900 dark:text-white text-sm">Journal SYSCOHADA / FEC</h2>
            </div>
            <div class="p-5">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Export FEC (Fichier des Écritures Comptables) au format SYSCOHADA —
                    standard accepté par les commissaires aux comptes et la DGI pour le contrôle fiscal.
                </p>
                <form method="POST" action="{{ route('integrations.fiscal.journal', $integration) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date début</label>
                            <input type="date" name="date_from" required
                                   value="{{ old('date_from', now()->startOfYear()->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date fin</label>
                            <input type="date" name="date_to" required
                                   value="{{ old('date_to', now()->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Journal (optionnel)</label>
                        <select name="journal_type_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="">— Tous les journaux</option>
                            @foreach ($journalTypes as $jt)
                                <option value="{{ $jt->id }}">{{ $jt->code }} — {{ $jt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Format d'export</label>
                        <select name="format" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="csv">📄 FEC pipe-délimité (norme DGI)</option>
                            <option value="xml">🗂 XML SYSCOHADA</option>
                            <option value="json">{ } JSON</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2.5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Télécharger FEC / Journal
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Déclaration push DGI (mode API) --------------------------------------}}
    @php
        $apiPushEnabled = (bool) ($integration->extra_config['api_push_enabled'] ?? false);
    @endphp
    <div class="rounded-[4px] border {{ $apiPushEnabled ? 'border-orange-200 dark:border-orange-800' : 'border-gray-200 dark:border-gray-700 opacity-70' }} bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
                    <svg class="w-4 h-4 text-orange-700 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white text-sm">Télédéclaration TVA — Push API DGI</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Envoi direct vers le portail DGI BF via API REST</p>
                </div>
            </div>
            @if (! $apiPushEnabled)
                <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
                    Non activé — mode export uniquement
                </span>
            @endif
        </div>
        <div class="p-5">
            @if (! $apiPushEnabled)
                <div class="flex items-start gap-3 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <div>
                        <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">API DGI BF non encore activée</p>
                        <p>La DGI Burkina Faso déploie progressivement son API REST. Pour activer le push direct,
                           ajoutez <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">"api_push_enabled": true</code>
                           dans la configuration extra de cette intégration et renseignez vos credentials DGI
                           (<code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">client_id</code> /
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">client_secret</code>).</p>
                        <p class="mt-2">En attendant, utilisez les exports CSV/XML ci-dessus pour le dépôt manuel sur
                           <a href="https://etax.impots.gov.bf" target="_blank" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">etax.impots.gov.bf</a>.</p>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('integrations.fiscal.declarer', $integration) }}"
                      class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Début période</label>
                        <input type="date" name="period_start" required
                               value="{{ old('period_start', now()->startOfMonth()->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fin période</label>
                        <input type="date" name="period_end" required
                               value="{{ old('period_end', now()->endOfMonth()->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">TVA collectée (F CFA)</label>
                        <input type="number" name="tva_collectee" min="0" step="1"
                               placeholder="0" value="{{ old('tva_collectee') }}"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2">
                    </div>
                    <div>
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 py-2.5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            Envoyer à la DGI
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Logs récents ---------------------------------------------------------}}
    @if ($recentLogs->isNotEmpty())
    <div class="rounded-[4px] border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
            <h2 class="font-semibold text-gray-900 dark:text-white text-sm">Historique des exports</h2>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($recentLogs as $log)
                <div class="px-5 py-3 flex items-center justify-between text-sm">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $log->success ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                            {{ $log->success ? '✓' : '✗' }}
                        </span>
                        <span class="text-gray-700 dark:text-gray-300 font-mono text-xs">{{ $log->endpoint }}</span>
                        <span class="text-gray-500 dark:text-gray-400 text-xs">{{ $log->method }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                        @if ($log->duration_ms)
                            <span>{{ $log->duration_ms }} ms</span>
                        @endif
                        <span>{{ $log->created_at?->diffForHumans() }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-700">
            <a href="{{ route('integrations.logs', ['integration_id' => $integration->id]) }}"
               class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                Voir tous les logs →
            </a>
        </div>
    </div>
    @endif

    {{-- Actions --------------------------------------------------------------}}
    <div class="flex items-center justify-between pt-2">
        <a href="{{ route('integrations.show', $integration) }}"
           class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
            ← Retour à l'intégration
        </a>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('integrations.ping', $integration) }}" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.143 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                    </svg>
                    Tester la connexion
                </button>
            </form>
            <a href="{{ route('integrations.edit', $integration) }}"
               class="inline-flex items-center gap-1.5 text-sm text-white bg-gray-700 hover:bg-gray-800 dark:bg-gray-600 dark:hover:bg-gray-500 rounded-lg px-3 py-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Configurer
            </a>
        </div>
    </div>

</div>
@endsection
