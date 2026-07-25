# Matrice comptable Production

| Événement | Débit | Crédit | Source/garantie |
|---|---|---|---|
| Consommation matière | 603x variation/consommation MP | 31x stock MP | ProductionAccountingService, clé idempotente |
| Entrée produit fini | 35x stock PF | 72x production stockée | sortie validée, pas avant qualité si contrôle requis |
| Rebut valorisé | compte perte/rebut | 31x/35x stock concerné | validation et trace du rebut |
| Écart de consommation | compte écart industriel | stock/variation selon signe | coût théorique vs réel |
| Sous-traitance | coût de production/sous-traitance | fournisseur/charge à payer | composante coût dédiée |
| Extourne | inverse exact de l’écriture originale | inverse exact | jamais de suppression historique |

Règles : écriture équilibrée, idempotence par événement, aucune écriture pour une simulation MRP/trésorerie, aucune double comptabilisation commande–OF–stock, comptes issus du paramétrage SYSCOHADA. Après correction de l’auditeur, les écritures brouillon ne participent plus au rapprochement des soldes.
