// ── Turbo Drive — doit être importé EN PREMIER ───────────────────────────────
import * as Turbo from '@hotwired/turbo';

// Désactiver le prefetch AVANT Turbo.start() pour bloquer l'observateur dès
// l'initialisation. La meta "turbo-prefetch" dans le layout couvre également
// chaque tentative individuelle (double protection).
Turbo.config.drive.prefetch = false;

Turbo.start();

// ── Turbo : gérer les erreurs réseau de navigation ───────────────────────────
// Filet de sécurité : si un fetch de navigation échoue malgré tout
// (réseau coupé, serveur arrêté), on absorbe l'erreur proprement.
document.addEventListener('turbo:fetch-request-error', (event) => {
    event.preventDefault();
    window.toast?.('Erreur réseau — veuillez réessayer.', 'error');
});

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
Alpine.plugin(collapse);
window.Alpine = Alpine;

// ── ApexCharts — bundlé par Vite, exposé globalement ─────────────────────────
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

// Helper pour les vues inline qui ont besoin d'ApexCharts.
// Problème : le bundle Vite charge en <script type="module"> (défer implicite).
// Les <script> inline classiques dans <body> s'exécutent AVANT le module
// → window.ApexCharts ET window.whenApexReady ne sont pas encore définis.
//
// Solution : queue d'attente sur window.__pendingApex.
// - Avant le chargement du bundle : les vues poussent leurs callbacks dans
//   un tableau (l'API push() de Array fait l'affaire).
// - Après chargement : on remplace l'array par un "dispatcher" qui exécute
//   immédiatement chaque push() (cas d'une navigation Turbo subséquente).
//
// Usage dans une vue Blade :
//   <script>
//     (window.__pendingApex = window.__pendingApex || []).push(function () {
//       new ApexCharts(document.querySelector('#chart'), {...}).render();
//     });
//   </script>
(function () {
    const pending = window.__pendingApex || [];
    // Remplace par un objet qui exécute les callbacks immédiatement
    window.__pendingApex = {
        push: function (cb) {
            try { cb(); } catch (e) { console.error('Apex callback error:', e); }
        }
    };
    // Exécute les callbacks accumulés avant le chargement du bundle
    pending.forEach(cb => { try { cb(); } catch (e) { console.error('Apex pending error:', e); } });
})();

// Alias pour compatibilité (whenApexReady était l'API précédente)
window.whenApexReady = (cb) => window.__pendingApex.push(cb);

// ═══════════════════════════════════════════════════════════════════════════════
// Registre de nettoyage Turbo
// Les scripts de pages enregistrent leurs cleanups ici.
// turbo:before-cache les exécute tous avant mise en cache, puis vide le tableau.
// ═══════════════════════════════════════════════════════════════════════════════
window.__turboCleanups = [];

// ── Turbo : nettoyage avant mise en cache ─────────────────────────────────────
// Enregistré UNE SEULE FOIS ici → pas de listener stacking
document.addEventListener('turbo:before-cache', function () {
    // Détruire les instances DataTables. jQuery n'est plus importé ici : il vient
    // de resources/js/datatables.js, chargé uniquement par les vues à tableau.
    // Sur toutes les autres pages, `window.jQuery` est absent et ce bloc est ignoré.
    const jq = window.jQuery;
    if (jq && jq.fn.DataTable) {
        document.querySelectorAll('table[data-dt]').forEach(function (el) {
            if (jq.fn.DataTable.isDataTable(el)) jq(el).DataTable().destroy();
        });
    }
    // Exécuter tous les cleanups de page (charts ApexCharts, etc.)
    window.__turboCleanups.forEach(fn => { try { fn(); } catch (e) {} });
    window.__turboCleanups = [];
});

// ── Turbo : init au chargement de chaque page ─────────────────────────────────
document.addEventListener('turbo:load', function () {
    // Défini par resources/js/datatables.js, absent sur les pages sans tableau.
    window.initDataTables?.();

    // ── Scroll horizontal auto pour les tables simples (non-DataTables) ──────
    document.querySelectorAll('table:not(.dataTable)').forEach(function (tbl) {
        if (tbl.closest('[data-dt]') || tbl.parentElement.classList.contains('tbl-rx')) return;
        var wrap = document.createElement('div');
        wrap.className = 'tbl-rx';
        tbl.parentNode.insertBefore(wrap, tbl);
        wrap.appendChild(tbl);
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// Alpine.js
// ═══════════════════════════════════════════════════════════════════════════════

// ── Toast notification manager ────────────────────────────────────────────────
Alpine.data('toastManager', () => ({
    toasts: [],
    _toastHandler: null,   // référence gardée pour le cleanup dans destroy()

    init() {
        // Flash server-side — lus depuis les <meta name="flash-*"> posées dans le body.
        // Cette approche évite les scripts inline réactivés par Turbo (source d'erreurs).
        ['success', 'error', 'warning', 'info'].forEach(type => {
            const meta = document.querySelector(`meta[name="flash-${type}"]`);
            if (meta?.content) {
                this.add(meta.content, type);
                meta.remove(); // Supprime après lecture pour ne pas re-afficher
            }
            // Compatibilité ascendante : vieille API window.__flash_* (si jamais présente)
            const key = `__flash_${type}`;
            if (window[key]) { this.add(window[key], type); window[key] = null; }
        });

        // Écoute les toasts déclenchés via window.toast()
        this._toastHandler = (e) => {
            this.add(e.detail.msg ?? e.detail.message ?? e.detail, e.detail.type ?? 'info');
        };
        window.addEventListener('toast', this._toastHandler);
    },

    // Lifecycle Alpine.data : appelé quand l'élément est retiré du DOM
    destroy() {
        if (this._toastHandler) {
            window.removeEventListener('toast', this._toastHandler);
            this._toastHandler = null;
        }
    },

    add(message, type = 'success', duration = 4500) {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, message, type, visible: true, duration });
        setTimeout(() => this.dismiss(id), duration);
    },

    dismiss(id) {
        const t = this.toasts.find(t => t.id === id);
        if (t) t.visible = false;
        setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 350);
    },

    icon(type) {
        return {
            success: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
            error:   `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
            warning: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>`,
            info:    `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
        }[type] ?? '';
    },

    colors(type) {
        return {
            success: { bg:'bg-white', border:'border-l-4 border-l-emerald-500', icon:'text-emerald-500', bar:'bg-emerald-400' },
            error:   { bg:'bg-white', border:'border-l-4 border-l-red-500',     icon:'text-red-500',     bar:'bg-red-400'     },
            warning: { bg:'bg-white', border:'border-l-4 border-l-amber-500',   icon:'text-amber-500',   bar:'bg-amber-400'   },
            info:    { bg:'bg-white', border:'border-l-4 border-l-blue-500',    icon:'text-blue-500',    bar:'bg-blue-400'    },
        }[type] ?? { bg:'bg-white', border:'border-l-4 border-l-gray-400', icon:'text-gray-500', bar:'bg-gray-400' };
    },
}));

// ── Number counter animation ──────────────────────────────────────────────────
Alpine.data('counter', (target, duration = 900) => ({
    displayed: 0,
    init() {
        if (!target) return;
        const start   = performance.now();
        const to      = parseFloat(target);
        const easeOut = t => 1 - Math.pow(1 - t, 3);
        const tick    = (now) => {
            const e = Math.min((now - start) / duration, 1);
            this.displayed = Math.round(to * easeOut(e));
            if (e < 1) requestAnimationFrame(tick); else this.displayed = to;
        };
        requestAnimationFrame(tick);
    },
    formatted() { return new Intl.NumberFormat('fr-FR').format(this.displayed); },
}));

// ── Global helper : toast depuis n'importe où ─────────────────────────────────
window.toast = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { msg: message, type } }));
};

// ── [PRODUCT-SEARCH] Helpers globaux du combobox produit ──────────────────────
// Utilisés par resources/views/ventes/partials/_product_combobox.blade.php
// Définis une seule fois ici (les <script> dans un <template x-for> ne s'exécutent pas).

// Échappe le HTML pour éviter toute injection dans x-html.
window.psEscape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

// Filtre les produits par désignation, référence, code-barres ou catégorie.
window.psFilter = (products, query) => {
    const q = (query || '').toLowerCase().trim();
    if (!q) return products;
    return products.filter((p) =>
        (p.name && p.name.toLowerCase().includes(q)) ||
        (p.reference && p.reference.toLowerCase().includes(q)) ||
        (p.barcode && String(p.barcode).toLowerCase().includes(q)) ||
        (p.family && p.family.name && p.family.name.toLowerCase().includes(q))
    );
};

// Met en surbrillance la portion recherchée (retourne du HTML échappé + <mark>).
window.psHighlight = (text, query) => {
    const safe = window.psEscape(text);
    const q = (query || '').trim();
    if (!q) return safe;
    const escQ = window.psEscape(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safe.replace(
        new RegExp('(' + escQ + ')', 'gi'),
        '<mark class="bg-amber-200/80 text-gray-900 rounded-[3px] px-0.5">$1</mark>'
    );
};

// ── Sidebar global state — store persistant entre les navigations Turbo ───────
Alpine.store('sidebar', {
    open:      false,
    collapsed: false,
});

// ── Dark mode — store Alpine global ──────────────────────────────────────────
// La classe 'dark' sur <html> est appliquée AVANT Alpine (inline script dans <head>)
// pour éviter le flash. Le store synchronise l'état réactif avec le DOM.
Alpine.store('darkMode', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('erp_dark', this.dark);
    },
});

// ── Recherche globale SAGE X3 (barre du header) ─────────────────────────────
// Saisie directe dans la barre, dropdown ancré dessous : fonctions (menus) +
// données (factures, clients…) groupées par catégorie. Ctrl+K focus la barre.
Alpine.data('sageSearch', function () {
    let functions = [];
    try {
        const el = document.getElementById('sage-search-functions');
        if (el) functions = JSON.parse(el.textContent || '[]');
    } catch (e) {}

    return {
        q: '',
        open: false,
        loading: false,
        results: [],
        functions,
        activeIndex: 0,

        // fonctions filtrées par la frappe (accents ignorés)
        get matchedFunctions() {
            const n = this.norm(this.q);
            if (n.length < 1) return this.functions.slice(0, 8);
            return this.functions.filter(f => this.norm(f.label).includes(n)).slice(0, 6);
        },
        // données groupées par type pour affichage façon X3
        get grouped() {
            const g = {};
            this.results.forEach(r => { (g[r.type] = g[r.type] || []).push(r); });
            return g;
        },
        // liste plate pour la navigation clavier : fonctions puis données
        get flat() {
            return [...this.matchedFunctions, ...this.results];
        },

        norm(v) {
            return String(v).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        },

        onFocus() { this.open = true; },
        close() { this.open = false; },
        focusBar() {
            this.$refs.sageInput?.focus();
            this.open = true;
        },

        async search() {
            this.activeIndex = 0;
            if (this.q.length < 2) { this.results = []; return; }
            this.loading = true;
            try {
                const url = this.$el.dataset.searchUrl || '/search';
                const r = await fetch(url + '?q=' + encodeURIComponent(this.q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) return;
                const d = await r.json();
                this.results = d.results ?? [];
                this.open = true;
            } catch (e) {
            } finally {
                this.loading = false;
            }
        },

        moveDown() { if (this.activeIndex < this.flat.length - 1) this.activeIndex++; },
        moveUp()   { if (this.activeIndex > 0) this.activeIndex--; },
        selectActive() {
            const item = this.flat[this.activeIndex];
            if (item?.url) { this.close(); window.location.href = item.url; }
        },
        isActive(item) { return this.flat[this.activeIndex] === item; },
        setActive(item) { const i = this.flat.indexOf(item); if (i >= 0) this.activeIndex = i; },

        colorStyle(color) {
            const styles = {
                indigo:  'background:#EEF2FF;color:#4338CA',
                violet:  'background:#F5F3FF;color:#7C3AED',
                blue:    'background:#EFF6FF;color:#1D4ED8',
                amber:   'background:#FFFBEB;color:#B45309',
                emerald: 'background:#ECFDF5;color:#047857',
                cyan:    'background:#ECFEFF;color:#0E7490',
                red:     'background:#FEF2F2;color:#DC2626',
                orange:  'background:#FFF7ED;color:#C2410C',
                gray:    'background:#F9FAFB;color:#374151',
            };
            return styles[color] || styles.gray;
        },
    };
});


Alpine.start();

// ═══════════════════════════════════════════════════════════════════════════════
// Auto-submit filter forms (data-autosubmit)
// Les formulaires de filtres marqués data-autosubmit se soumettent automatiquement
// quand un <select> ou <input type="date/month"> change, sans cliquer "Filtrer".
//
// Usage :
//   <form method="GET" data-autosubmit>
//     <select name="status">...</select>
//     <input type="date" name="date_from">
//     <input type="text" name="search" data-autosubmit-debounce="600">
//   </form>
//
// Comportement :
//   - select / date / month / checkbox → soumission immédiate
//   - input[text] avec data-autosubmit-debounce → soumission après N ms d'inactivité
//   - Affiche un spinner dans le bouton submit (si présent) pour feedback visuel
// ═══════════════════════════════════════════════════════════════════════════════
function initAutoSubmitForms() {
    document.querySelectorAll('form[data-autosubmit]').forEach(form => {
        if (form._autosubmitBound) return;  // éviter double binding (Turbo)
        form._autosubmitBound = true;

        const submitWithFeedback = () => {
            // Désactiver le bouton submit pendant la navigation
            const btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.dataset.origText = btn.textContent;
                btn.innerHTML = '<svg class="inline w-3.5 h-3.5 mr-1 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>Chargement…';
            }
            form.submit();
        };

        // Éléments à soumission immédiate
        form.querySelectorAll('select, input[type="date"], input[type="month"], input[type="checkbox"], input[type="radio"]').forEach(el => {
            el.addEventListener('change', submitWithFeedback);
        });

        // Éléments texte : soumission différée (debounce)
        form.querySelectorAll('input[type="text"], input[type="search"], input[type="number"]').forEach(el => {
            let timer;
            const delay = parseInt(el.dataset.autosubmitDebounce ?? '600', 10);
            el.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(submitWithFeedback, delay);
            });
        });
    });
}

// Init au premier chargement et à chaque navigation Turbo
document.addEventListener('turbo:load', initAutoSubmitForms);

// ── Garde global : avale les rejets fetch « NetworkError » bénins ────────────
// (requête annulée par navigation Turbo, source-map, extension navigateur).
// Les vraies erreurs applicatives restent visibles (on ne masque que les
// échecs réseau de type TypeError fetch).
window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;
    const msg = (reason && (reason.message || reason.name || String(reason))) || '';
    // Match par message (robuste cross-realm : worker, extension, source-map).
    const benign = /NetworkError|Failed to fetch|Load failed|aborted|AbortError|TypeError: NetworkError/i.test(msg);
    if (benign) {
        event.preventDefault(); // supprime le « Uncaught (in promise) » en console
        if (console && console.debug) console.debug('[fetch] requête réseau ignorée :', msg);
    }
});
