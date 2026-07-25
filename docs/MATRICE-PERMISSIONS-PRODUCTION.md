# Matrice des permissions Production

## Permissions existantes

`production.view`, `production.create`, `production.update`, `production.delete`, `production.launch`, `production.validate`, `production.cancel`, `production.declare`, `production.validate_declaration`, `production.approve_financial`, `production.modify_launched`, `production.submit_validation`, `production.validate_chef`, `production.validate_responsable`, `production.cost.view`, `production.report.view`, permissions d’avis de modification, `quality.view`, `quality.manage`, `quality.release`, `maintenance.view`, `maintenance.manage`.

| Rôle | Consulter | Planifier/créer | Lancer | Déclarer | Contrôler/libérer | Clôturer/annuler | Exporter |
|---|---:|---:|---:|---:|---:|---:|---:|
| DG / Directeur | Oui | Supervision | Selon délégation | Non | Dérogations | Actions sensibles | Oui |
| DAF | Oui | Non | Autorisation financière | Non | Non | Avis financier | Oui |
| Directeur usine | Oui | Oui | Oui | Oui | Supervision | Oui | Oui |
| Responsable production | Oui | Oui | Oui | Oui | Visa production | Oui | Oui |
| Planificateur | Oui | Oui | Non | Non | Non | Replanifier | Oui |
| Chef atelier/équipe | Oui | Limité | Validation chef | Oui | Visa déclaration | Suspendre | Oui |
| Opérateur | OF assignés | Non | Non | Oui | Non | Non | Non |
| Magasinier | Oui | Allocation matière | Non | Consommations | Quarantaine stock | Libération réservation | Oui |
| Responsable qualité | Oui | Plans contrôle | Non | Contrôles | Oui | Décision qualité | Oui |
| Contrôleur qualité | Oui | Non | Non | Mesures | Selon délégation | Non | Oui |
| Responsable maintenance | Oui | OT/plans | Non | Intervention | Remise service | Clôture OT | Oui |
| Technicien maintenance | Oui | Demande | Non | Intervention | Contrôle technique | Fin intervention | Non |
| Contrôleur gestion | Oui | Non | Non | Non | Coûts | Non | Oui |

## Séparation des tâches

Le créateur d’une dérogation, d’un rebut sensible ou d’une libération exceptionnelle ne doit pas être son validateur. Les tests `QualitySeparationOfDutiesTest`, `ProductionAuditGuardsTest` et `SecurityActionGranularityTest` apportent la preuve actuelle. Les permissions génériques restantes doivent progressivement être séparées en actions `submit`, `plan`, `suspend`, `close`, `reverse` et `export` dédiées.
