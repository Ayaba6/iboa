{{--
    ┌─────────────────────────────────────────────────────────────────────┐
    │  COMPOSANT GLOBAL — Confirmation de suppression & états de chargement│
    │                                                                     │
    │  1. Intercepte TOUS les formulaires avec :                          │
    │     • onsubmit="return confirm(...)"  ← existants (sans modification)│
    │     • data-confirm="Message"          ← nouvelles vues              │
    │     Et remplace le confirm() natif par une modale Alpine.js belle.  │
    │                                                                     │
    │  2. Ajoute un état de chargement (spinner) sur :                    │
    │     • <a data-loading>  (lien PDF / export — même onglet)           │
    │     • <button data-loading> (ex : bouton Export)                    │
    │     Les liens target="_blank" sont ignorés (nouvel onglet = pas de  │
    │     gel de l'interface).                                             │
    │                                                                     │
    │  Inclus une seule fois dans layouts/erp.blade.php (avant </body>).  │
    └─────────────────────────────────────────────────────────────────────┘
--}}

{{-- ── Overlay global — modal de confirmation ─────────────────────────── --}}
{{--
    x-cloak : caché jusqu'à l'init Alpine (CSS dans app.css : [x-cloak]{display:none!important})
    x-show  : géré par Alpine après init
--}}
<div id="erp-confirm-modal"
     x-data="{
         open: false,
         title: 'Confirmer la suppression',
         message: 'Cette action est irréversible. Voulez-vous continuer ?',
         confirmLabel: 'Supprimer',
         isDanger: true,
         _resolve: null,

         show(opts) {
             this.title        = opts.title        ?? 'Confirmer la suppression';
             this.message      = opts.message      ?? 'Cette action est irréversible. Voulez-vous continuer ?';
             this.confirmLabel = opts.confirmLabel ?? 'Supprimer';
             this.isDanger     = opts.isDanger     ?? true;
             this.open = true;
             this.$nextTick(() => this.$refs.confirmBtn && this.$refs.confirmBtn.focus());
             return new Promise(resolve => { this._resolve = resolve; });
         },

         doConfirm() {
             this.open = false;
             if (this._resolve) { this._resolve(true); this._resolve = null; }
         },

         doCancel() {
             this.open = false;
             if (this._resolve) { this._resolve(false); this._resolve = null; }
         },
     }"
     x-cloak
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="doCancel()"
     class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
         x-show="open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="doCancel()"></div>

    {{-- Dialog --}}
    <div class="relative z-10 bg-white dark:bg-gray-800 rounded-[4px] shadow-2xl w-full max-w-md border border-gray-100 dark:border-gray-700"
         x-show="open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop>

        <div class="p-6">
            {{-- Icon --}}
            <div class="flex items-center justify-center w-12 h-12 rounded-full mx-auto mb-4"
                 :class="isDanger ? 'bg-red-50 dark:bg-red-900/30' : 'bg-amber-50 dark:bg-amber-900/30'">
                {{-- [BUG-FIX] x-if inside <svg> fails (SVG DOM doesn't support <template>).
                     Use x-show on <path> elements instead — works natively in SVG. --}}
                <svg class="w-6 h-6"
                     :class="isDanger ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="isDanger"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    <path x-show="!isDanger"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            {{-- Title --}}
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2"
                x-text="title"></h3>

            {{-- Message --}}
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center leading-relaxed"
               x-text="message"></p>
        </div>

        {{-- Actions --}}
        <div class="flex gap-3 px-6 pb-6">
            <button type="button"
                    @click="doCancel()"
                    class="flex-1 px-4 py-2.5 rounded-[4px] border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300">
                Annuler
            </button>
            <button type="button"
                    @click="doConfirm()"
                    x-ref="confirmBtn"
                    class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                    :class="isDanger
                        ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
                        : 'bg-amber-500 hover:bg-amber-600 focus:ring-amber-400'"
                    x-text="confirmLabel">
            </button>
        </div>
    </div>
</div>

{{-- ── Loading overlay (PDF / export — même onglet) ───────────────────── --}}
<div id="erp-loading-overlay"
     class="fixed inset-0 z-[9998] flex flex-col items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm"
     style="display:none">
    <div class="flex flex-col items-center gap-4 animate-pulse-slow">
        <div class="w-12 h-12 rounded-full border-4 border-emerald-200 border-t-indigo-600 animate-spin"></div>
        <p id="erp-loading-text"
           class="text-sm font-medium text-gray-700 dark:text-gray-300 tracking-wide">
            Génération en cours…
        </p>
    </div>
</div>

<script>
/* ============================================================
   ERP Confirm Modal — Interception globale des formulaires
   ============================================================
   Stratégie :
   • Écoute l'événement `submit` en phase de capture (avant les
     handlers inline onsubmit="return confirm(...)").
   • Détecte un message de confirmation dans :
       - l'attribut data-confirm="..."
       - le contenu de onsubmit="return confirm('...')"
   • Bloque le submit natif, ouvre la modale Alpine.js.
   • Après confirmation utilisateur → form.submit() natif.
   ============================================================ */

/* Extrait le texte du confirm() depuis un attribut onsubmit */
function _erpExtractConfirmMsg(attr) {
    if (!attr) return null;
    const m = attr.match(/confirm\s*\(\s*['"](.+?)['"]\s*\)/);
    return m ? m[1] : null;
}

document.addEventListener('DOMContentLoaded', function () {

    /* ── 1. Interception des formulaires ─────────────────────────────── */
    document.addEventListener('submit', async function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        /* Détecter le message */
        const dataConfirm  = form.dataset.confirm;
        const onsubmitAttr = form.getAttribute('onsubmit') || '';
        const inlineMsg    = _erpExtractConfirmMsg(onsubmitAttr);

        /* ── Auto-confirmation globale pour TOUS les formulaires DELETE ──
           Tout form avec _method=DELETE sans data-confirm explicite
           reçoit une confirmation par défaut (data-skip-confirm pour opt-out). */
        let confirmMsg = dataConfirm || inlineMsg;
        if (!confirmMsg && form.dataset.skipConfirm === undefined) {
            const methodInput = form.querySelector('input[name="_method"]');
            const hiddenMethod = methodInput ? methodInput.value.toUpperCase() : '';
            if (hiddenMethod === 'DELETE') {
                confirmMsg = form.dataset.confirmMsg || 'Supprimer cet élément ? Cette action est irréversible.';
            }
        }

        if (!confirmMsg) return; /* Pas de confirmation requise */

        /* Bloquer soumission native ET handler onsubmit inline */
        e.preventDefault();
        e.stopImmediatePropagation();

        /* Récupérer l'instance Alpine du modal */
        const modalEl = document.getElementById('erp-confirm-modal');
        const alpine  = modalEl && modalEl._x_dataStack && modalEl._x_dataStack[0];

        if (!alpine || typeof alpine.show !== 'function') {
            /* Fallback : confirm natif */
            if (window.confirm(confirmMsg)) {
                form.onsubmit = null;
                form.removeAttribute('data-confirm');
                form.submit();
            }
            return;
        }

        /* Construire les options */
        const methodInput = form.querySelector('input[name="_method"]');
        const method      = (methodInput ? methodInput.value : '').toUpperCase();
        const isDelete    = method === 'DELETE';

        const opts = {
            title:        form.dataset.confirmTitle  || (isDelete ? 'Confirmer la suppression' : 'Confirmer l\'action'),
            message:      confirmMsg,
            confirmLabel: form.dataset.confirmLabel  || (isDelete ? 'Supprimer' : 'Confirmer'),
            isDanger:     form.dataset.confirmDanger !== 'false' ? isDelete : false,
        };

        const confirmed = await alpine.show(opts);

        if (confirmed) {
            form.onsubmit = null;
            form.removeAttribute('data-confirm');
            form.removeAttribute('onsubmit');
            form.submit();
        }
    }, true /* capture phase */);

    /* ── 1b. Helper global async — utilisable depuis n'importe quel JS ── */
    window.erpConfirm = async function (optsOrMessage) {
        const modalEl = document.getElementById('erp-confirm-modal');
        const alpine  = modalEl?._x_dataStack?.[0];
        const opts    = typeof optsOrMessage === 'string'
            ? { message: optsOrMessage }
            : (optsOrMessage ?? {});
        if (!alpine || typeof alpine.show !== 'function') {
            return window.confirm(opts.message ?? 'Confirmer ?');
        }
        return alpine.show(opts);
    };

    /* ── 2. État de chargement pour liens PDF / export ───────────────── */
    const overlay  = document.getElementById('erp-loading-overlay');
    const loadText = document.getElementById('erp-loading-text');

    if (overlay) {
        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-loading]');
            if (!el) return;

            /* Ignorer les liens s'ouvrant dans un nouvel onglet */
            if (el.getAttribute('target') === '_blank') return;

            const text = el.dataset.loadingText || 'Génération en cours…';
            if (loadText) loadText.textContent = text;
            overlay.style.display = 'flex';

            /* Masquer après 30s (failsafe) */
            const t = setTimeout(() => { overlay.style.display = 'none'; }, 30000);
            overlay.dataset.timerId = t;
        });

        /* Masquer quand la page réapparaît (téléchargement terminé, retour arrière) */
        window.addEventListener('pageshow', () => { overlay.style.display = 'none'; });
        window.addEventListener('focus',    () => {
            /* Délai court pour laisser le navigateur décider s'il y a eu téléchargement */
            setTimeout(() => { overlay.style.display = 'none'; }, 500);
        });
    }
});
</script>
