# A3 ERP — IBOA / OA METAL INDUSTRIE

## Register

product

## Platform

web

## What this is

ERP métier complet pour OA METAL INDUSTRIE (Ouagadougou, Burkina Faso), fabricant
de tôles bac prélaquées et fers à béton. Modules : ventes, achats, stocks
(bobines/lots/entrepôts), production (ordres de fabrication, découpe, suivi),
comptabilité SYSCOHADA, trésorerie, RH/paie (CNSS + IUTS Burkina Faso),
qualité, analytique. Laravel 11 + Blade + Tailwind v3 + Alpine.js, MySQL.

## Target users

Employés internes de l'entreprise, à l'aise avec les ERP denses type Sage X3 :

- Administrateur / direction (tableaux de bord, validations)
- Comptable (écritures, balance, grand livre, déclarations)
- Gestionnaire RH/paie (bulletins, CNSS, IUTS)
- Responsable production et opérateurs atelier (OF, suivis, bobines)
- Commerciaux (devis, commandes, factures, clients)

Usage quotidien intensif au clavier, écrans 1366-1920 px, réseau parfois lent.
Langue : français uniquement. Devise : XOF (FCFA, aucun décimal).

## Brand personality

Sobre, dense, professionnel — « Sage X3 modernisé ». La densité d'information
est une qualité, pas un défaut : l'utilisateur expert veut tout voir d'un coup.
Aucune décoration gratuite. Chaque pixel sert la lecture des chiffres.

## Anti-references

- Dashboards SaaS aérés à grosses cartes et illustrations — trop de scroll,
  pas assez de données.
- Gradients violets/indigo, glassmorphism, ombres portées lourdes.
- Interfaces « conviviales » qui cachent les données derrière des clics.
- Tout composant qui casse la lecture tabulaire des montants.

## Strategic design principles

1. **La charte Sage X3 fait loi** — squelette documenté (barre titre + actions,
   sections numérotées à bandeau émeraude, tables denses, synthèse basse,
   footer noir de contexte). Toute nouvelle page l'adopte ; référence :
   `resources/views/comptabilite/balance.blade.php`.
2. **Chiffres d'abord** : montants en mono `tabular-nums` alignés à droite,
   en-têtes alignés sur leurs colonnes, débit bleu / crédit rouge,
   XOF sans décimales (`number_format($n, 0, ',', ' ')`).
3. **Cohérence inter-états** : un même chiffre (solde client, stock, IUTS)
   doit être identique sur toutes les pages qui l'affichent.
4. **Densité contrôlée** : text-[12.5px] dans les tables, py-1.5, zébrage
   léger — dense mais jamais tronqué (pas de débordement horizontal caché).
5. **Feedback métier explicite** : messages d'erreur actionnables qui nomment
   le composant, le dépôt, la règle violée — jamais de message générique.
