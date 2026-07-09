{{--
    Styles inline du layout ERP.
    - Animations progressBar / fade-up / pulse-dot
    - Custom scrollbar de la sidebar
    - DataTables custom (boutons html5 + .buttons-html5)
    - .tbl-rx (responsive table wrapper)
    - Classes utilitaires print
--}}
<style>
/* ── Sidebar nav : scrollbar fine et stylée ──────────────────────── */
.sidebar-nav { scrollbar-width: thin; scrollbar-color: rgba(5,150,105,.35) transparent; }
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(99,102,241,.45);
    border-radius: 99px;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(129,140,248,.7);
}

/* ── DataTables — style Tailwind cohérent ──────────────────────────────────── */
/* NOTE: ce bloc utilise du CSS natif — pas de @apply (non traité dans les templates Blade). */

table.dataTable thead th { cursor: pointer; user-select: none; }
table.dataTable thead th.dt-ordering-asc::after  { content: " ↑"; opacity: 0.5; font-size: 0.75em; }
table.dataTable thead th.dt-ordering-desc::after { content: " ↓"; opacity: 0.5; font-size: 0.75em; }

/* Barre de recherche DataTables */
div.dt-search input {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    background-color: #ffffff;
}
div.dt-search input:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.4);
    border-color: #6366f1;
}
div.dt-search label { font-size: 0.875rem; color: #4b5563; margin-right: 0.5rem; }

/* Sélecteur de longueur */
div.dt-length select {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.375rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    background-color: #ffffff;
    margin-right: 0.25rem;
}
div.dt-length label { font-size: 0.875rem; color: #4b5563; }

/* Info et pagination */
div.dt-info   { font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem; }
div.dt-paging { margin-top: 0.5rem; }
div.dt-paging .dt-paging-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    margin: 0 0.125rem;
    transition: color 150ms, background-color 150ms, border-color 150ms;
    cursor: pointer;
}
div.dt-paging .dt-paging-button.current,
div.dt-paging .dt-paging-button:hover { background-color: #4f46e5; color: #ffffff; border-color: #4f46e5; }
div.dt-paging .dt-paging-button.disabled { opacity: 0.4; cursor: not-allowed; }

/* Boutons d'export */
.dt-buttons { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }

.dt-btn-excel,
.dt-btn-pdf,
.dt-btn-print {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.5rem;
    background: none !important;
    transition: background-color 150ms, color 150ms;
}
.dt-btn-excel { border: 1px solid #059669; color: #047857; }
.dt-btn-excel:hover { background-color: #ecfdf5 !important; }
.dt-btn-pdf   { border: 1px solid #dc2626; color: #b91c1c; }
.dt-btn-pdf:hover   { background-color: #fef2f2 !important; }
.dt-btn-print { border: 1px solid #9ca3af; color: #374151; }
.dt-btn-print:hover { background-color: #f9fafb !important; }

/* ════════════════════════════════════════════════════════════════════
   RESPONSIVE — adaptations mobiles / tablettes
════════════════════════════════════════════════════════════════════ */

/* ── iOS : empêcher le zoom auto sur les inputs (font-size < 16px) ── */
@supports (-webkit-touch-callout: none) {
    input[type="text"], input[type="email"], input[type="number"],
    input[type="search"], input[type="tel"], input[type="date"],
    input[type="datetime-local"], input[type="password"],
    input[type="url"], select, textarea { font-size: max(16px, 1em) !important; }
}

/* ── Tables : scroll horizontal automatique ─────────────────────── */
.tbl-rx { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.tbl-rx table { min-width: 520px; }

/* ── Notifications dropdown : ne pas dépasser l'écran ───────────── */
@media (max-width: 479px) {
    .notif-dropdown { width: calc(100vw - 2rem) !important; right: -4rem !important; }
}

/* ── Cards : padding compact sur mobile ─────────────────────────── */
@media (max-width: 639px) {
    .kpi-card .p-5   { padding: 0.875rem !important; }
    .space-y-5 > * + * { margin-top: 0.75rem !important; }
    main .gap-4 { gap: 0.625rem !important; }
    main .gap-5 { gap: 0.75rem !important; }
    main .py-6  { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
    main .px-4  { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
}

/* ── Grilles : forcer 1 colonne sur très petits écrans ──────────── */
@media (max-width: 479px) {
    main .grid-cols-2:not(.force-2) { grid-template-columns: 1fr !important; }
    .lg\:grid-cols-2, .lg\:grid-cols-3 { grid-template-columns: 1fr; }
}

/* ── Sidebar : bloquer le scroll du body quand ouvert ───────────── */
body.sidebar-open { overflow: hidden; }

/* ── Formulaires : inputs pleine largeur sur mobile ─────────────── */
@media (max-width: 639px) {
    form .grid.grid-cols-2 { grid-template-columns: 1fr !important; }
    form .grid.grid-cols-3 { grid-template-columns: 1fr !important; }
    form .flex.gap-3       { flex-direction: column; }
    form .flex.gap-3 > *   { width: 100%; }
}

/* ── Modaux : plein écran sur mobile ────────────────────────────── */
@media (max-width: 639px) {
    [role="dialog"] .sm\:max-w-lg,
    [role="dialog"] .max-w-lg,
    [role="dialog"] .max-w-2xl { max-width: calc(100vw - 1rem) !important; margin: 0.5rem !important; }
}
</style>

