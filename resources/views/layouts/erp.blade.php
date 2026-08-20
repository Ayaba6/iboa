<!DOCTYPE html>
<html lang="fr" :class="{ 'overflow-hidden': $store.sidebar.open }">
<head>
    @include('partials.layout._head')
</head>
<body class="font-sans antialiased text-gray-900"
      style="min-height:100vh;">

@include('partials.layout._toast-notifications')

{{-- ── Mobile sidebar overlay ─────────────────────────────────────────────── --}}
<div x-show="$store.sidebar.open"
     @click="$store.sidebar.open = false"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-20 bg-gray-900/60 lg:hidden"
     x-cloak></div>

{{-- x-data="{}" requis pour qu'Alpine traite les :class de l'aside (Alpine v3
     n'évalue les directives que si un x-data ancestor existe). --}}
<div class="min-h-screen flex" x-data="{}">

    {{-- SIDEBAR --}}
    @include('partials.layout._sidebar')

    {{-- MAIN CONTENT (header + content + footer)
         Pas d'overflow ici : sinon ça casse le `sticky top-0` du <header>.
         Les tables larges sont déjà wrappées dans leur propre conteneur .tbl-rx (overflow-x auto). --}}
    <div class="flex-1 flex flex-col min-w-0">

        {{-- Top bar --}}
        @include('partials.layout._topbar')

        {{-- ── Bannière de validation — affichée si le formulaire est resoumis avec erreurs ── --}}
        @if($errors->any())
        <div id="erp-validation-errors"
             class="mx-4 lg:mx-6 mt-4 rounded-[4px] border border-red-200 bg-red-50 text-[13px] shadow-sm animate-fade-in-down"
             role="alert" aria-live="polite">
            <div class="flex items-start gap-3 p-4">
                {{-- Icône --}}
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                {{-- Contenu --}}
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-red-800 mb-1.5">
                        {{ $errors->count() === 1 ? '1 erreur à corriger' : $errors->count() . ' erreurs à corriger' }}
                    </p>
                    <ul class="space-y-1 text-red-700">
                        @foreach($errors->all() as $error)
                            <li class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            {{-- Barre de progression rouge en bas --}}
            <div class="h-1 rounded-b-xl bg-red-200"></div>
        </div>
        {{-- Auto-scroll vers la bannière d'erreurs --}}
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var banner = document.getElementById('erp-validation-errors');
            if (banner) {
                // Délai court pour laisser le layout se stabiliser avant le scroll
                setTimeout(function () {
                    banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    // Focus sur le premier champ en erreur pour l'accessibilité
                    var firstErr = document.querySelector('.border-red-400, .border-red-500, [aria-invalid="true"]');
                    if (firstErr) { setTimeout(function () { firstErr.focus({ preventScroll: true }); }, 400); }
                }, 80);
            }
        });
        </script>
        @endif

        {{-- Page Content (erp-content = scope du dark mode pour les utilitaires Tailwind) --}}
        <main class="erp-content flex-1 overflow-y-auto flex flex-col">
            <div class="flex-1 px-4 lg:px-6 py-6 animate-fade-in-up">
                @yield('content')
            </div>

            {{-- Pied de page --}}
            <footer class="flex-shrink-0 border-t border-gray-100 bg-white px-4 lg:px-6 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2 text-[12px] text-gray-400">
                    <span>© {{ date('Y') }} <strong class="text-gray-500">A3 ERP</strong> · Tous droits réservés</span>
                    <a href="https://wa.me/22670037622" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-green-600 hover:text-green-700 font-medium transition-colors">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        +226 70 03 76 22
                    </a>
                </div>
            </footer>
        </main>
    </div>
</div>

@stack('modals')

{{-- ── Confirmation modale globale + états de chargement PDF/export ────── --}}
<x-confirm-modal />

{{-- Command Palette (Ctrl+K / Cmd+K) --}}
{{-- [SAGE X3] palette remplacée par la recherche du header (Ctrl+K focus la barre) --}}

{{-- Keyboard Shortcuts Help (?) --}}
@include('partials.layout._keyboard-shortcuts')

{{-- DataTables init est dans app.js (enregistré une seule fois, avec lazy title) --}}

@include('partials.layout._layout-styles')

@stack('scripts')
</body>
</html>
