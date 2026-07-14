# GUIDE D'UTILISATION DU CYCLE DE VENTE ET DE FABRICATION
## OA METAL INDUSTRIE — ERP A3/IBOA

| | |
|---|---|
| **Logo** | [Capture 00 — logo OA METAL INDUSTRIE en tête de l'ERP] |
| **ERP** | A3/IBOA |
| **Titre** | Guide d'utilisation du cycle de vente et de fabrication |
| **Version** | 1.0 |
| **Date** | 14/07/2026 |
| **Auteur** | Cellule Test ERP |
| **Diffusion** | Document interne |

### Historique des versions

| Version | Date | Auteur | Modification | Validation |
|---|---|---|---|---|
| 1.0 | 14/07/2026 | Cellule Test ERP | Création — basé sur le test réel DEV-2026-00036 → ENC-2026-012 | À valider par la Direction |

---

## Sommaire

1. [Introduction](#1-introduction)
2. [Prérequis](#2-prérequis)
3. [Guide pas à pas](#3-guide-pas-à-pas) (34 opérations)
4. [Gestion des cas particuliers](#4-gestion-des-cas-particuliers)
5. [Foire aux questions](#5-foire-aux-questions)
6. [Messages d'erreur](#6-messages-derreur)
7. [Glossaire](#7-glossaire)

---

## 1. Introduction

Le module **Ventes** pilote la relation commerciale : devis, commandes, bons de préparation, bons de livraison, factures et avoirs. Pour un produit **fabriqué à la commande** (tôle bac), il est relié automatiquement aux autres modules :

- **Production** : la validation d'une commande d'un article fabriqué sans stock crée automatiquement un **ordre de fabrication** (OF).
- **Stock** : la production entre le produit fini en stock ; la validation du BL le sort ; la matière première (bobine) est décomptée à la déclaration.
- **Qualité** : chaque OF porte un contrôle qualité ; une non-conformité **bloque la livraison**.
- **Livraison** : le bon de préparation doit être **chargé** avant de créer le BL (§13.7).
- **Facturation** : la facture se génère depuis le BL validé ; sa validation crée l'écriture de vente.
- **Trésorerie** : l'encaissement s'impute sur la facture et met à jour la banque et l'encours client.
- **Comptabilité** : écritures SYSCOHADA automatiques (411/7011/4431 puis 521/411) et **lettrage** du compte client.

## 2. Prérequis

**Droits utilisateurs** (rôle → actions principales) :

| Rôle | Actions |
|---|---|
| commercial | Devis (créer, soumettre), conversion en commande, soumission de commande |
| directeur / responsable commercial | Validation devis et commandes, création BL, génération facture depuis BL |
| chef_production | Allocation matière, soumission OF, validation Responsable Production, lancement, consommation, déclaration, chutes, contrôle qualité OF, clôture |
| directeur_usine / chef_atelier | Validation Chef Atelier de l'OF |
| daf | Autorisation financière de l'OF (client à crédit) |
| magasinier | Chargement du bon de préparation, validation du BL |
| comptable | Validation de facture, encaissement, imputation |

**Données nécessaires avant de commencer** : client actif avec plafond de crédit ; article fabriqué en mode « Fabrication à la commande » avec prix de vente et unité ml ; nomenclature active (bobine, kg/m, taux de perte) ; ligne/machine de profilage active ; dépôts MP et PF ; bobine compatible disponible (couleur, épaisseur, largeur, poids) ; taux de TVA 18 % ; journaux Ventes et Banque ; comptes 411/70x/4431/521.

**Conventions de saisie** : montants en FCFA entiers ; quantités de tôle bac en **mètres linéaires** ; le détail « nombre de feuilles × longueur » se saisit dans les lignes de coupe de l'OF et en note de déclaration ; ne jamais utiliser le compte administrateur pour les opérations courantes.

## 3. Guide pas à pas

> Chaque opération indique : profil, menu, actions, contrôles automatiques, statut obtenu et l'emplacement de capture correspondant.

### 3.1 Se connecter à l'ERP
Profil : tous. Ouvrir l'URL de l'ERP, saisir e-mail + mot de passe, **Se connecter**. `[Capture 01 — écran de connexion A3 ERP]`

### 3.2 Accéder au module Ventes
Menu latéral **Ventes** → tableau de bord ventes (KPIs, échéances, pipeline). `[Capture 02 — tableau de bord Ventes]`

### 3.3 Rechercher ou créer un client
**Gestion → Clients**. Vérifier sur la fiche : statut actif, **plafond de crédit**, encours, mode de règlement, conditions de paiement. Exemple du test : CLIENT TEST GUIDE SARL, plafond 10 000 000 F, encours 0. `[Capture 03 — fiche client, encadré crédit]`

### 3.4 Créer un devis
Profil : commercial. **Ventes → Devis → Nouveau devis.**
Champs : Client\* (liste), Adresse de livraison, Devise (XOF), Date, **Durée de validité 30 jours** (date calculée automatiquement), TVA par défaut 18 %, **Entrepôt/Dépôt = DEP-PF**, Conditions de paiement **30 jours**, Mode de paiement **Virement bancaire**. `[Capture 04 — entête du devis rempli]`

### 3.5 Ajouter une ligne de tôle bac
Dans **Détail des articles**, cliquer le sélecteur **— Produit —**, rechercher « TOLE BAC PRELAQUEE BEIGE 27/100 4 ONDES ». Le PU HT (4 000) et la TVA se remplissent depuis la fiche article. `[Capture 05 — sélecteur produit ouvert]`

### 3.6–3.8 Feuilles, longueur, mètres linéaires
La quantité du devis s'exprime en **mètres linéaires** (unité de vente). Pour 20 feuilles de 5 m : saisir **Qté = 100**. Indiquer « 20 feuilles × 5 m » dans la désignation ou les observations ; le plan de coupe détaillé (20 × 5 m) sera visible sur l'OF (lignes de coupe). Contrôle : 20 × 5 = 100 ml.

### 3.9 Vérifier prix et taxes
Bas de page : **Total HT 400 000 · Total TVA 72 000 · Total TTC 472 000**. Tout écart = erreur de saisie (PU ou quantité). `[Capture 06 — bloc totaux 400 000 / 72 000 / 472 000]`

### 3.10 Enregistrer le devis
Bouton **Enregistrer**. L'ERP attribue le numéro automatiquement (ex. **DEV-2026-00036**) ; statut **Brouillon** ; créateur et date tracés dans « Suivi ». `[Capture 07 — devis créé, numéro + statut Brouillon]`

### 3.11 Imprimer le devis
Fiche devis → **PDF** (téléchargement ou aperçu). Vérifier logo, mentions légales et totaux. `[Capture 08 — PDF du devis]`

### 3.12 Soumettre à validation
Fiche devis → **Soumettre à validation** → confirmation. Statut : **En attente de validation** ; une notification part au valideur (cloche + « Mes validations »). `[Capture 09 — statut En attente + notification]`

### 3.13 Valider ou rejeter le devis
Profil : directeur. Ouvrir le devis (via **Mes validations**) → **Valider** (ou **Refuser** avec motif obligatoire). Statut : **Validé**, nom du valideur et horodatage tracés. Les champs sensibles sont verrouillés. `[Capture 10 — devis Validé, encart validation]`

### 3.14 Transformer le devis en commande
Profil : commercial. Fiche du devis validé → **Transformer en commande**. Une commande (ex. **CMD-2026-046**) reprend à l'identique client, lignes, prix, TVA, conditions ; le devis passe **Converti** et ne peut plus être reconverti. `[Capture 11 — commande créée liée au devis]`

### 3.15 Vérifier le crédit client
Le contrôle est **automatique à la soumission** : si l'encours dépasse le plafond, la commande est **bloquée** et le responsable notifié. Récapitulatif du test : plafond 10 000 000 · encours avant 0 · commande TTC 472 000 · encours prévisionnel 472 000 · crédit disponible 9 528 000 · **décision : autorisée automatiquement**. Consultez **Gestion → Clients → Crédit** pour le détail et l'historique des décisions. `[Capture 12 — écran Crédit client]`

### 3.16 Valider la commande
Commercial : **Soumettre**. Directeur : **Valider**. Statut **Confirmée**. Pour un article fabriqué sans stock, l'ERP crée **automatiquement l'OF** (ex. **OF-2026-0151**) lié à la commande, avec nomenclature et ligne de production pré-affectées. `[Capture 13 — commande Confirmée + OF auto en documents liés]`

### 3.17 Créer l'OF
Cas MTO : **automatique** (voir 3.16). Cas manuel : **Production → Ordres de fabrication → Nouveau** en renseignant commande, produit, quantité (ml), lignes de coupe (20 × 5 m), dates, priorité, ligne/machine, nomenclature, dépôts. `[Capture 14 — fiche OF, onglet Gestion]`

### 3.18 Réserver une bobine
Profil : chef de production. Fiche OF → **Allouer la matière** : l'ERP vérifie la disponibilité du composant (bobine beige 27/100 — poids requis ≈ qté × 1,7513 kg/m × 1,035). Statut OF : **Matière allouée**. En cas de manque : blocage avec liste des manquants et substituts proposés. ⚠ L'allocation est un engagement logique ; le poids précis de la bobine est décompté à la consommation (voir réserve A4 du rapport). `[Capture 15 — OF Matière allouée]`

### 3.19 Lancer la fabrication
Circuit de validation : **Soumettre validation** (chef prod.) → **Validation Chef Atelier** (directeur d'usine) → **Validation Responsable Production** (chef prod.) → **Autorisation financière** (DAF — obligatoire pour une commande à crédit ; décision motivée tracée) → **Lancer l'OF** puis **Démarrer**. Statut : **En cours**, date/heure de démarrage tracées. `[Capture 16 — chaîne de validation OF + statut En cours]`

### 3.20 Déclarer la consommation
Fiche OF → onglet **Suivi** → bloc consommation : choisir la **bobine** (BOB-TEST-GUIDE-001), saisir **poids consommé** (182 kg) et longueur déroulée (101 m). Effets : poids restant bobine 500 → **318 kg**, statut bobine « en production », coût matière tracé (182 × 850 = 154 700 F). `[Capture 17 — consommation bobine enregistrée]`

### 3.21 Déclarer la production
Onglet **Suivi** → **Déclaration de production** : produit, **dépôt PF**, **quantité en ml (100)**, coût unitaire, **n° de lot** (LOT-PF-TEST-GUIDE-001), note « 20 feuilles × 5 m ». Effets : **entrée stock PF +100 ml**, sortie automatique du composant BOM (175,13 kg), lot tracé. `[Capture 18 — déclaration 100 ml + lot]`

### 3.22 Enregistrer chutes et rebuts
Onglet **Clôture** → **Chutes & pertes** : type (**Réutilisable** / **Rebut**), poids, valeur, motif. Test : chute réutilisable 0,9 kg (765 F) « fin de bobine » + rebut 1,8 kg (1 530 F) « amorce de réglage ». `[Capture 19 — tableau chutes]`

### 3.23 Effectuer le contrôle qualité
Fiche OF → bloc **Contrôle qualité** : cocher **Épaisseur / Longueur / Couleur / Visuel**, verdict **Conforme** (ou Non conforme avec quantité rejetée et motif), contrôleur, date. Une **non-conformité bloque la livraison**. `[Capture 20 — QC conforme 4 critères]`

### 3.24 Entrée du produit fini en stock
Réalisée **automatiquement** par la déclaration (3.21). Vérification : **Stocks → Mouvements** — mouvement « Production OF-2026-0151 » +100 au dépôt PF. `[Capture 21 — mouvement d'entrée production]`

### 3.25 Clôturer l'OF
Après visa du chef sur la déclaration (**Valider** sur la ligne de déclaration) : bouton **Terminer l'OF**. Contrôles bloquants : déclarations visées, quantité produite ≥ demandée (sinon « Terminer partiellement »), matière consommée, contrôle qualité présent. Effets : statut **Terminé**, **lot de fabrication auto**, **réservation du produit fini pour le client (100 ml)**, calcul du **coût réel**, écritures de production. Un OF terminé n'est plus modifiable librement. `[Capture 22 — OF Terminé + panneau Traçabilité & Rendement]`

### 3.26 Créer le bon de préparation
Le BP est **généré automatiquement** à la confirmation de la commande (ex. **BP-2026-0028**, statut « En attente »). **Ventes → Bons de préparation**. `[Capture 23 — liste BP]`

### 3.27 Contrôler le chargement
Profil : magasinier. Fiche BP → **Démarrer le chargement** puis **Terminer le chargement**. Statut : **Chargé**. Sans cela, la création du BL est **refusée** (§13.7). `[Capture 24 — BP Chargé]`

### 3.28 Créer et valider le BL
Création (profil direction/responsable commercial) : fiche commande → **Générer le bon de livraison** (ex. **BL-2026-025**, brouillon, dépôt PF, 100 ml). Validation (profil magasinier) : fiche BL → **Valider**. Effets : **sortie stock −100**, réservation client soldée, quantité livrée mise à jour, commande **Livrée**. `[Capture 25 — BL validé + stock PF à 0]`

### 3.29 Créer la facture
Fiche BL validé → **Générer la facture** (ex. **FA-2026-018**, brouillon) : reprise du BL (client, 100 ml × 4 000), **HT 400 000 / TVA 72 000 / TTC 472 000**, échéance +30 j. Toute seconde génération pour le même BL est **refusée**. `[Capture 26 — facture générée]`

### 3.30 Valider et envoyer la facture
Profil : comptable. Fiche facture → **Valider**. Effets : statut **Envoyée**, montants verrouillés, **écriture de vente** générée (D 411 = 472 000 ; C 7011 = 400 000 ; C 4431 = 72 000). PDF : bouton **PDF** (contrôler logo, IFU, coordonnées bancaires). L'envoi e-mail nécessite un serveur SMTP configuré. `[Capture 27 — facture validée + écriture]`

### 3.31 Enregistrer le paiement
Profil : comptable. **Trésorerie → Encaissements → Nouveau** : client, **compte bancaire** (Compte Coris Bank), **montant 472 000**, date, **référence VIR-TEST-GUIDE-001**, imputation sur FA-2026-018 = 472 000. `[Capture 28 — formulaire encaissement]`

### 3.32 Imputer le paiement
L'imputation saisie en 3.31 est appliquée à l'enregistrement (imputation a posteriori possible via **Imputer** sur l'encaissement). Effets : facture **Payée**, écriture **D 521 / C 411 = 472 000**, **lettrage automatique** du compte client (lettre commune facture/règlement), solde banque mis à jour (+472 000). `[Capture 29 — encaissement imputé + lettrage]`

### 3.33 Vérifier le solde
Fiche facture : reste à payer **0** ; fiche client : encours **0** ; toute tentative de re-validation de la facture réglée est **refusée (verrouillée)**. `[Capture 30 — facture Payée, reste 0]`

### 3.34 Consulter l'historique complet
**Gestion → Clients → fiche → Dossier** : chaîne documentaire avec le **statut propre de chaque document** — Devis *Converti* → Commande *Facturée* → OF *Terminé* → BL *Livré* → Facture *Payée* → Reste dû 0. Historique d'audit détaillé (créé/soumis/validé par, horodaté) au bas de chaque document. `[Capture 31 — Dossier client, chaîne complète]`

## 4. Gestion des cas particuliers

| Cas | Conduite à tenir (comportement vérifié) |
|---|---|
| **Client dépassant son plafond** | La soumission de commande est bloquée avec message explicite ; le responsable commercial/directeur est notifié ; passer par **Clients → Crédit** (relèvement de plafond ou dérogation tracée) avant de re-soumettre. |
| **Produit fini déjà disponible** | Aucun OF n'est créé à la validation : la commande passe directement au circuit préparation/livraison. |
| **Bobine insuffisante** | La consommation supérieure au restant est **refusée** (« Poids demandé supérieur au restant ») ; au lancement, les manquants bloquent avec proposition de **substituts** de nomenclature ; sinon approvisionner (module Achats). |
| **Bobine non conforme** | Ne pas consommer ; déclarer la non-conformité (module Qualité), statut bobine « bloquée », retour fournisseur le cas échéant. |
| **Production partielle** | Bouton **Terminer partiellement** : l'OF garde le reliquat à produire ; la clôture définitive avec écart exige une dérogation explicite. |
| **Production supérieure à la commande** | Déclarer le réel ; l'excédent reste en stock PF disponible (non réservé). |
| **Livraison partielle / reliquat** | Modifier la quantité du BL ; la commande passe **Partiellement livrée** ; un second BL solde le reliquat (comportement vérifié par test automatisé). |
| **Produit non conforme** | QC **Non conforme** → livraison bloquée ; déclarer le **rebut** (validation chef + qualité) ; reprise de fabrication via nouvel OF ou reliquat. |
| **Reprise de fabrication** | Créer un OF de reprise lié à la commande ; consommer la matière additionnelle ; re-contrôler. |
| **Annulation d'un OF** | Bouton **Annuler** (motif obligatoire) tant que non terminé ; les consommations saisies peuvent être annulées ligne à ligne (restitution du poids bobine). |
| **Modification d'une commande validée** | Circuit de **demande de modification** à 4 avis (chef production → commercial → finance → DG) sur l'OF lié ; la commande confirmée n'est pas modifiable librement. |
| **Retour client / avoir** | **Ventes → Avoirs → Nouveau** depuis la facture : par ligne, choisir le **sort du retour** (« Remis en stock » ré-entre le stock ; « Rebut » ne ré-entre rien) ; possibilité de générer un **BL de remplacement** depuis l'avoir validé. |
| **Paiement partiel** | Imputer le montant reçu : facture **Partiellement payée**, solde suivi en échéancier/relances. |
| **Paiement supérieur** | L'excédent reste **non imputé** sur l'encaissement (imputable plus tard ou en acompte). |
| **Facture impayée** | Suivi via échéancier, relances et balance âgée (module Relances/Comptabilité). |
| **Annulation / extourne d'un paiement** | Annuler l'encaissement : contre-passation de l'écriture et dé-lettrage ; la facture redevient due. |

## 5. Foire aux questions

1. **Le devis ne calcule pas 472 000 TTC ?** Vérifiez PU = 4 000, Qté = 100, TVA ligne = 18 %.
2. **Le bouton Valider n'apparaît pas ?** Votre profil n'a pas le droit de validation — le document doit être traité par le valideur notifié.
3. **Pourquoi la commande est bloquée à la soumission ?** Plafond de crédit dépassé ou client bloqué : voir écran Crédit client.
4. **L'OF ne s'est pas créé ?** L'article n'est pas en mode « Fabrication à la commande » ou du stock PF disponible couvrait la commande.
5. **« Lancer l'OF » est grisé ?** Circuit de validation incomplet (chef atelier, responsable production) ou autorisation financière absente, ou matière manquante.
6. **Pourquoi une autorisation DAF ?** Toute commande à crédit exige un visa financier avant lancement de la fabrication — décision motivée et tracée.
7. **La consommation bobine est refusée ?** Poids demandé > restant, ou l'OF n'est pas « En cours ».
8. **Dans quelle unité déclarer la production ?** En **mètres linéaires** (unité de vente) ; notez « X feuilles × Y m » en commentaire.
9. **Où voir le reste de la bobine ?** Fiche OF onglet **Traçabilité** (poids consommé/restant, lot, fournisseur) ou fiche bobine.
10. **Peut-on livrer sans contrôle qualité ?** Non si le contrôle est exigé ; une non-conformité bloque la livraison.
11. **Pourquoi la clôture de l'OF est refusée ?** Déclaration non visée par le chef, quantité produite < demandée, aucune matière consommée, ou QC obligatoire absent.
12. **À quoi sert le bon de préparation ?** Il matérialise préparation + contrôle du chargement ; le BL est refusé tant qu'il n'est pas « Chargé ».
13. **Qui valide le BL ?** Le magasinier (droit deliveries.validate) ; la sortie de stock se fait à cette validation.
14. **Peut-on facturer deux fois un BL ?** Non — la seconde génération est refusée.
15. **La facture affiche « Envoyée » sans e-mail ?** Le statut est posé à la validation ; l'envoi réel dépend du serveur SMTP configuré.
16. **Comment imputer un règlement sur plusieurs factures ?** Dans l'encaissement, ajoutez plusieurs lignes d'imputation (une par facture).
17. **Qu'est-ce que le lettrage ?** Le rapprochement facture/règlement sur le compte 411 — automatique ici (lettre commune).
18. **Peut-on modifier une facture payée ?** Non — verrouillée ; passer par un avoir.
19. **Où suivre tout le dossier d'un client ?** Fiche client → bouton **Dossier** (chaîne documentaire, statuts par document, reste dû).
20. **Que faire si le stock PF affiche 0 avant livraison ?** Vérifier que la déclaration de production a été faite **en ml** au bon dépôt et visée.
21. **Comment traiter un retour partiel ?** Avoir sur la facture avec disposition par ligne (remis en stock / rebut), puis remplacement éventuel.

## 6. Messages d'erreur

| Message affiché | Cause probable | Vérification | Solution | Contact |
|---|---|---|---|---|
| « Commande bloquée : la limite de crédit du client … est dépassée » | Encours + commande > plafond | Écran Crédit client | Relèvement/dérogation tracée puis re-soumettre | Responsable commercial / DAF |
| « Poids demandé (…) supérieur au restant de la bobine » | Bobine insuffisante | Fiche bobine (poids restant) | Choisir une autre bobine ou approvisionner | Magasinier / Achats |
| « La consommation n'est possible que sur un OF “en cours” » | OF non démarré ou terminé | Statut de l'OF | Lancer/démarrer l'OF | Chef de production |
| « … déclaration(s) de production en attente du visa chef d'équipe — clôture impossible » | Déclaration non visée | Onglet Suivi | Faire viser la déclaration | Chef d'atelier |
| « Quantité produite (…) inférieure à la quantité demandée — clôture définitive bloquée » | Production incomplète | Quantités OF | « Terminer partiellement » ou dérogation | Chef de production |
| « Bon de préparation … : le chargement doit être terminé avant de créer le bon de livraison » | BP non chargé | Statut du BP | Démarrer puis terminer le chargement | Magasinier |
| « Livraison bloquée : contrôle qualité non conforme sur l'OF … » | QC non conforme | Bloc Qualité de l'OF | Reprise/tri puis nouveau contrôle conforme | Qualité |
| « Stock insuffisant : X disponible(s), Y demandée(s) » | Sortie > disponible | Niveaux de stock | Corriger la quantité ou produire/réapprovisionner | Magasinier |
| « Une facture a déjà été générée pour ce bon de livraison » | Double facturation | Documents liés du BL | Utiliser la facture existante | Comptable |
| « Seules les factures … peuvent être validées » / 403 sur facture payée | Facture verrouillée (réglée) | Statut facture | Passer par un avoir | Comptable |
| « Cette action n'est pas autorisée » (403) | Droit manquant pour votre profil | Matrice des rôles (§2) | Faire réaliser l'action par le bon profil | Administrateur |

## 7. Glossaire

| Terme | Définition |
|---|---|
| **Devis** | Proposition commerciale chiffrée, à valider puis convertir en commande. |
| **Commande** | Engagement client ; sa validation déclenche préparation et, en MTO, la fabrication. |
| **OF (Ordre de fabrication)** | Document pilotant la production : quantités, plan de coupe, matière, contrôles, coûts. |
| **Make-to-Order (MTO)** | Fabrication déclenchée par la commande client, sans stock préalable. |
| **Bobine** | Matière première en rouleau (couleur, épaisseur, largeur, poids), tracée par lot. |
| **Nomenclature (BOM)** | Recette de l'article : composants, kg par mètre, taux de perte, substituts. |
| **Gamme** | Suite d'opérations de fabrication avec postes de charge et temps. |
| **Mètre linéaire (ml)** | Unité de vente de la tôle bac (longueur produite/vendue). |
| **Produit fini (PF)** | Article fabriqué prêt à livrer, stocké au dépôt PF. |
| **Chute** | Reste de matière ; « réutilisable » (revalorisable) ou non. |
| **Rebut** | Production non conforme éliminée, valorisée en perte. |
| **Contrôle qualité** | Vérification (épaisseur, longueur, couleur, visuel…) conditionnant la libération. |
| **BP / BL** | Bon de préparation (préparation + chargement) / Bon de livraison (sortie de stock, remise client). |
| **Facture** | Pièce de vente comptabilisée (411/70x/4431), porteuse de l'échéance. |
| **Encaissement** | Règlement client (banque/caisse/mobile money) enregistré en trésorerie. |
| **Imputation** | Affectation d'un encaissement à une ou plusieurs factures. |
| **Lettrage** | Rapprochement des lignes 411 facture/règlement par une lettre commune. |
| **Encours** | Total dû par le client (factures non soldées). |
| **Plafond de crédit** | Encours maximal autorisé ; au-delà, commande bloquée. |

---

*Pied de page : ERP A3/IBOA — Guide cycle Vente & Fabrication v1.0 — OA METAL INDUSTRIE — Document interne.*
