# DESIGN.md — A3 ERP (charte « Sage X3 » IBOA)

Système visuel de l'ERP. Toute page nouvelle ou refondue suit ce squelette ;
modèle de référence : `resources/views/comptabilite/balance.blade.php`.

## Theme

Poste de travail expert, clair, dense. Fond neutre `#F6F8FB`, cartes blanches
`rounded-[4px] border border-gray-300`. L'émeraude est la couleur de structure
(bandeaux, actions), le violet est proscrit.

## Colors

| Rôle | Valeur | Usage |
|---|---|---|
| Structure / action primaire | `emerald-700` (#047857), hover `emerald-800` | boutons primaires, liens de code |
| Bandeau de section | `#eef5f0` + texte `emerald-900` | en-têtes de cartes et de tables |
| Débit / valeurs positives | `blue-700` | soldes débiteurs, entrées |
| Crédit / alertes | `red-600` | soldes créditeurs, sorties, retards |
| Avertissement | `amber-50/500/800` | bandeaux d'information |
| Footer contexte | `gray-900` fond, `gray-200`/blanc texte | barre de contexte bas de page |
| Sidebar | vert sombre marque, actif `#22c55e` | navigation |

Badges statut : `bg-{couleur}-100 text-{couleur}-700 rounded-[3px] text-[10.5px]`
(emerald=actif/ok, gray=inactif/brouillon, amber=partiel/attente, red=rupture/retard,
blue=calculé/info).

## Typography

- UI : police système du layout (pas d'import de fonts décoratives).
- Montants, codes, références : `font-mono tabular-nums`, alignés à droite.
- Titres de page : `text-[17px] font-bold text-gray-900` + sous-titre
  `text-xs text-gray-400`.
- Bandeaux de section : `text-[11px] font-bold uppercase tracking-wide
  text-emerald-900`, numérotés (« 1. Critères de sélection », « 2. … »).
- Tables : corps `text-[12.5px]`, en-têtes `text-[10px]`-`text-[11px]` bold
  uppercase émeraude.

## Layout

Squelette de page (ordre fixe) :

1. **Barre titre** : titre + sous-titre à gauche ; à droite les actions —
   primaire verte (`bg-emerald-700`), secondaires blanches bordées
   (`border-gray-300`), « ✕ Fermer » en dernier.
2. **Sections numérotées** en cartes, bandeau `px-4 py-2 bg-[#eef5f0]
   border-b border-emerald-100`, compteur à droite en `text-emerald-600`.
3. **Critères** : labels `text-xs font-medium` AU-DESSUS des champs ;
   champ Société en lecture seule `bg-gray-50`.
4. **Tables** : thead `#eef5f0` (sous-en-têtes `#eef5f0/70`), zébrage
   `even:bg-gray-50/40`, hover `bg-[#eef5f0]/40`, `table-fixed` + `truncate`
   + `title` quand le contenu risque de déborder — jamais de scroll
   horizontal caché qui coupe la première colonne.
5. **Synthèse basse** : carte `grid divide-x`, cases `p-3 text-center`
   (label `text-[10px] uppercase`, valeur `text-[15px] font-bold`).
6. **Footer noir de contexte** : `bg-gray-900 text-xs` — Société · module ou
   période · filtre · Utilisateur · horodatage `d/m/Y H:i`.

## Components

- **Formulaires** : inputs `border-gray-300 rounded-[4px] px-3 py-2 text-sm
  focus:ring-1 focus:ring-emerald-500`. Astérisque rouge UNIQUEMENT si le
  backend valide `required`. Selects : jamais écraser le `padding-right`
  du chevron (règle globale `pr-2rem` dans app.css) ; champs `h-8` → `py-0`.
- **Fiches maquette X3** (immobilisations, OF, clients) : onglets horizontaux,
  panneau latéral d'actions, workflow en étapes numérotées avec coches.
- **Modales de confirmation** : « Confirmer l'action » + Annuler/Confirmer
  (orange) pour toute action irréversible.
- **Toasts** : coin haut droit, succès émeraude.

## Rules

- XOF entier : `number_format($n, 0, ',', ' ')` — jamais de décimales monétaires.
- Un solde de sens anormal s'affiche signé en rouge avec badge « solde
  anormal » — ne jamais écraser avec `max(0, …)`.
- Alignement en-tête/colonne : `th.text-right`/`th.text-center` (règles
  globales app.css) — un montant droit sous un en-tête gauche est un bug.
- Après édition de vue : `php artisan view:clear` + hard reload (Turbo sert
  des snapshots périmés) ; build CSS/JS : `npm run build` (Vite, pas de dev
  server).
- Vérification finale : `BladeViewsCompileTest` + screenshot navigateur.
