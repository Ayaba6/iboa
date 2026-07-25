# Matrice fonctionnelle Production

| Domaine | Routes | Contrôleur/service | Modèles/migrations | UI | Permissions | Tests | Niveau |
|---|---|---|---|---|---|---|---|
| Dashboard | Oui | Oui | Oui | Oui | `production.view` | Dashboard/MES | Partiellement prouvé |
| Éligibles MTO | Oui | SalesProductionService | Commande/OF | Oui | view/create | Eligibility/MTO/E2E | Prouvé |
| MTS | Oui | PlanningService | Produit/OF | Oui | view/create | MtsPlanning/E2E | Prouvé |
| OF | Oui | Workflow/ProductionService | OF, lignes, opérations | Oui | permissions granulaires | nombreux tests | Prouvé |
| Exécution | Oui | Execution/StockService | consommations/sorties/rebuts | Oui | declare/validate | execution/backflush | Prouvé |
| BOM | Oui | BomExplosionService | BOM/lignes | Oui | CRUD production | BOM/substitute | Partiel |
| Gammes | Oui | RoutingService | gamme/opérations | Oui | CRUD production | Routing | Partiel |
| Ressources | Oui | PlanningService | machines/lignes/centres | Oui | CRUD production | référentiel/capacité | Partiel |
| Maintenance | Oui | MaintenanceService | OT/plans/pièces | Oui | maintenance.* | préventif/machine | Prouvé |
| Charge/arrêts | Oui | Planning/Downtime | planning/arrêts | Oui | update/declare | workload/downtime | Prouvé |
| Découpe | Oui | CuttingOptimizer | plans/lignes | Oui | production.* | 5 suites dédiées | Prouvé |
| Qualité | Oui | Quality services | inspections/NC/CAPA/releases | Oui | quality.* | qualité/SoD | Prouvé |
| Certificats | Oui | CertificateController | certificats | Oui | quality.* | couverture partielle | Partiel |
| MRP | Oui | MrpService | propositions calculées | Oui | production.view | ProductionMrp | Prouvé |
| Trésorerie | Oui | TreasuryService | agrégats OF | Oui | production.view | Treasury | Partiel |
| Rapports | Oui | ReportController | requêtes réelles | Oui | report.view | reports/MES | Partiel |

L’intégration ventes → production → stock → qualité → comptabilité est couverte par des parcours E2E et des services idempotents. Aucun écran audité ne contient de lien `#`, `TODO` ou action vide détectable dans le périmètre source.
