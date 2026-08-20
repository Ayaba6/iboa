{{--
    Système de notifications toast (haut-droite).

    - Les flash messages session (success/error/warning/info) sont injectés via des
      balises <meta name="flash-*"> (data-turbo-temporary) — aucun <script> inline.
      Cette approche évite les erreurs Turbo Drive lors du body-swap (replaceWith SyntaxError).
    - Le composant Alpine `toastManager` (défini dans app.js) lit les <meta> dans init(),
      les affiche, et les supprime. Voir Alpine.data('toastManager', ...) dans app.js.
--}}

{{--
    Flash messages — passés via <meta> dans le <body> plutôt qu'un <script> inline.
    Avantage Turbo : aucun script activé lors du body-swap (pas de SyntaxError potentiel).
    data-turbo-temporary → Turbo supprime ces éléments avant la mise en cache ; ils ne
    réapparaissent donc pas lors d'une restauration depuis le cache Turbo.
    Le toastManager Alpine les lit dans init() via querySelector, puis les supprime.
--}}
@if(session('success'))
<meta name="flash-success" content="{{ session('success') }}" data-turbo-temporary>
@endif
@if(session('error'))
<meta name="flash-error" content="{{ session('error') }}" data-turbo-temporary>
@endif
@if(session('warning'))
<meta name="flash-warning" content="{{ session('warning') }}" data-turbo-temporary>
@endif
@if(session('info'))
<meta name="flash-info" content="{{ session('info') }}" data-turbo-temporary>
@endif

{{-- ── Toast Notifications (fixed top-right) ──────────────────────────────── --}}
<div x-data="toastManager"
     class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 pointer-events-none">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8 scale-95"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-250"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             :class="[colors(toast.type).bg, colors(toast.type).border,
                      'relative overflow-hidden rounded-xl shadow-lg border border-gray-100 pointer-events-auto']">

            <div class="flex items-start gap-3 p-4">
                {{-- Icon --}}
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0"
                     :class="colors(toast.type).icon"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <g x-html="icon(toast.type)"></g>
                </svg>
                {{-- Message --}}
                <p class="flex-1 text-[13px] text-gray-700 leading-snug" x-text="toast.message"></p>
                {{-- Close --}}
                <button @click="dismiss(toast.id)"
                        class="flex-shrink-0 text-gray-300 hover:text-gray-500 transition-colors -mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            {{-- Progress bar --}}
            <div class="absolute bottom-0 left-0 h-0.5 rounded-full"
                 :class="colors(toast.type).bar"
                 :style="`animation: progressBar ${toast.duration}ms linear forwards`"
                 x-cloak></div>
        </div>
    </template>
</div>
