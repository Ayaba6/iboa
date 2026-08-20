// ═══════════════════════════════════════════════════════════════════════════
// DataTables — point d'entrée SÉPARÉ, chargé à la demande
// ═══════════════════════════════════════════════════════════════════════════
//
// [Perf] jQuery et DataTables vivaient dans app.js, donc sur CHAQUE page de
// l'ERP. Or `data-dt` n'apparaît que trois fois dans tout le projet
// (gestion/clients, brands, units). Tous les écrans de saisie — devis,
// commandes, factures — téléchargeaient et parsaient ces bibliothèques pour
// des tableaux qu'ils n'ont pas.
//
// Ce module n'est chargé que par les vues qui en ont besoin, via :
//     @push('head_scripts') @vite('resources/js/datatables.js') @endpush
//
// L'habillage (indicateurs de tri, filtres, pagination) est fourni par
// _layout-styles.blade.php, qui cible les classes natives de DataTables 2.
//
// Turbo : une fois le module évalué, il reste en mémoire pour les navigations
// suivantes. `window.initDataTables` est donc redéfinie une seule fois, et
// app.js l'appelle sur chaque `turbo:load` — s'il la trouve.

import $ from 'jquery';
import 'datatables.net';

// Exposé globalement : app.js détruit les instances sur `turbo:before-cache` et
// doit pouvoir constater l'absence de jQuery sur les pages qui ne chargent pas
// ce module.
window.$ = window.jQuery = $;

// ── DataTables : config française ────────────────────────────────────────────
const dtFr = {
    processing:     'Traitement en cours…',
    search:         'Rechercher :',
    lengthMenu:     'Afficher _MENU_ éléments',
    info:           'Affichage de _START_ à _END_ sur _TOTAL_ éléments',
    infoEmpty:      'Affichage de 0 à 0 sur 0 élément',
    infoFiltered:   '(filtré depuis _MAX_ éléments au total)',
    infoPostFix:    '',
    loadingRecords: 'Chargement en cours…',
    zeroRecords:    'Aucun élément à afficher',
    emptyTable:     'Aucune donnée disponible',
    paginate: { first:'Premier', previous:'Précédent', next:'Suivant', last:'Dernier' },
    aria: {
        sortAscending:  ' : activer pour trier la colonne par ordre croissant',
        sortDescending: ' : activer pour trier la colonne par ordre décroissant',
    },
    buttons: {
        copy:'Copier', csv:'CSV', excel:'Excel', pdf:'PDF', print:'Imprimer',
        colvis:'Colonnes', copyTitle:'Copié dans le presse-papier',
        copySuccess: { 1:'1 ligne copiée', _:'%d lignes copiées' },
    },
};

// ── DataTables : filtres dynamiques par colonne ──────────────────────────────
// [CDC Ventes] Ajoute une 2e ligne d'en-tête avec un champ de filtre par colonne
// (colonnes marquées data-sortable). Recherche client-side via l'API column().search().
// Ne pas activer sur les tables serveur-paginées (le filtre ne verrait que la page courante).
function dtAddColumnFilters(el, dt) {
    const thead = el.tHead;
    if (!thead || thead.querySelector('.dt-filter-row')) return;
    const headRow = thead.rows[0];
    const fr = document.createElement('tr');
    fr.className = 'dt-filter-row';
    dt.columns().every(function (i) {
        const col  = this;
        const th   = headRow.cells[i];
        const cell = document.createElement('th');
        cell.className = 'px-2 py-1 bg-white/80 align-top';
        if (th && th.hasAttribute('data-sortable')) {
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.placeholder = 'Filtrer…';
            inp.className = 'w-full min-w-[60px] text-[11px] font-normal normal-case px-1.5 py-0.5 border border-gray-300 rounded-[3px] focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-400';
            inp.addEventListener('input', function () {
                if (col.search() !== this.value) col.search(this.value).draw();
            });
            // Ne pas déclencher le tri de la colonne en cliquant dans le champ
            ['click', 'keydown', 'keyup', 'mousedown'].forEach(evt =>
                inp.addEventListener(evt, e => e.stopPropagation()));
            cell.appendChild(inp);
        }
        fr.appendChild(cell);
    });
    thead.appendChild(fr);
}

// ── DataTables : initialisation (appelée sur turbo:load) ─────────────────────
window.initDataTables = function () {
    if (typeof $ === 'undefined' || !$.fn.DataTable) return;

    document.querySelectorAll('table[data-dt="simple"]').forEach(function (el) {
        if ($.fn.DataTable.isDataTable(el)) return;
        $(el).DataTable({
            language:      dtFr,
            paging:        false,
            searching:     true,   // requis pour le filtre par colonne (aucune barre globale via dom)
            info:          false,
            ordering:      true,
            orderCellsTop: true,   // le tri porte sur la 1re ligne d'en-tête, les filtres sur la 2e
            dom:           'rt',
            columnDefs:    [{ orderable: false, targets: '_all' }],
        });
        const dt = $(el).DataTable();
        dt.columns().every(function (i) {
            const th = el.querySelectorAll('thead th')[i];
            if (!th || !th.hasAttribute('data-sortable')) this.orderable(false);
        });
        // Filtres par colonne : opt-in via data-col-filter (à réserver aux tables chargées
        // en entier — pas de pagination serveur, sinon le filtre ne verrait que la page courante).
        if (el.hasAttribute("data-col-filter")) dtAddColumnFilters(el, dt);
    });

    // [Retiré] La branche `data-dt="full"` (pagination + boutons d'export
    // Excel/PDF/Imprimer) n'était référencée par AUCUNE vue : `data-dt` n'apparaît
    // que trois fois dans le projet, toujours avec la valeur "simple". Ces boutons
    // n'ont donc jamais été affichés, alors que leur seule présence en code
    // imposait de charger dataTables.buttons, buttons.html5, buttons.print, jszip,
    // pdfmake et vfs_fonts sur CHAQUE page de l'ERP — dont les écrans de saisie
    // qui n'ont pas de tableau.
    //
    // L'export n'est pas perdu : il est déjà rendu côté serveur (65 routes
    // export-excel / export-pdf, classes App\Exports), avec la protection contre
    // l'injection de formules Excel que la génération client ne fournit pas.
    //
    // Pour rétablir un export client-side : réinstaller les extensions via npm
    // (`datatables.net-buttons`, `jszip`, `pdfmake`), les importer ici, et
    // restaurer cette branche. Les styles .dt-btn-* de _layout-styles.blade.php
    // ont été conservés à cette fin.
};
// Première initialisation : le module peut être évalué APRÈS le turbo:load de
// la page qui l'a demandé (script de type module = exécution différée).
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.initDataTables());
} else {
    window.initDataTables();
}
