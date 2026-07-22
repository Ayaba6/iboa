# A3 ERP — Dictionnaire de données (généré le 22/07/2026)

Tables métier principales. Volume = lignes au moment de la génération (base de recette nettoyée).

## Référentiel

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `companies` | id | 61 | 2 | oui | 1 |
| `users` | id | 16 | 1 | — | 21 |
| `products` | id | 97 | 30 | oui | 28 |
| `item_categories` | id | 64 | 17 | oui | 10 |
| `item_category_sites` | id | 14 | 6 | — | 0 |
| `product_families` | id | 52 | 15 | oui | 41 |
| `product_sites` | id | 13 | 6 | — | 0 |
| `units` | id | 19 | 1 | — | 13 |
| `tax_rates` | id | 11 | 2 | — | 4 |
| `warehouses` | id | 44 | 4 | oui | 6 |
| `accounts` | id | 13 | 3 | — | 154 |
| `clients` | id | 90 | 9 | oui | 1 |
| `suppliers` | id | 60 | 3 | oui | 1 |
| `employees` | id | 41 | 4 | oui | 1 |

## Ventes

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `quotes` | id | 52 | 7 | oui | 0 |
| `quote_items` | id | 18 | 4 | — | 0 |
| `orders` | id | 60 | 7 | oui | 0 |
| `order_items` | id | 21 | 5 | — | 0 |
| `delivery_notes` | id | 31 | 4 | oui | 0 |
| `delivery_note_items` | id | 16 | 4 | — | 0 |
| `invoices` | id | 66 | 12 | oui | 0 |
| `invoice_items` | id | 19 | 4 | — | 0 |
| `credit_notes` | id | 28 | 4 | oui | 0 |

## Trésorerie

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `client_payments` | id | 35 | 5 | oui | 0 |
| `client_payment_allocations` | id | 9 | 3 | — | 0 |
| `cash_accounts` | id | 45 | 2 | oui | 3 |
| `bon_preparations` | id | 19 | 3 | — | 10 |
| `supplier_payments` | id | 44 | 5 | oui | 0 |
| `supplier_payment_allocations` | id | 8 | 2 | — | 0 |

## Achats

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `purchase_requests` | id | 22 | 3 | oui | 0 |
| `purchase_request_items` | id | 12 | 3 | — | 0 |
| `purchase_orders` | id | 44 | 6 | oui | 0 |
| `purchase_order_items` | id | 18 | 4 | — | 0 |
| `receptions` | id | 18 | 4 | oui | 0 |
| `reception_items` | id | 15 | 4 | — | 0 |
| `supplier_invoices` | id | 39 | 8 | oui | 0 |
| `supplier_invoice_items` | id | 16 | 4 | — | 0 |
| `supplier_returns` | id | 26 | 6 | oui | 0 |

## Stocks

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `product_stocks` | id | 10 | 3 | — | 0 |
| `stock_movements` | id | 33 | 12 | — | 0 |
| `stock_lots` | id | 20 | 3 | — | 0 |
| `stock_reservations` | id | 13 | 5 | — | 0 |
| `stock_transfers` | id | 41 | 4 | oui | 0 |
| `coils` | id | 44 | 6 | oui | 0 |
| `inventory_sessions` | id | 28 | 2 | oui | 0 |
| `inventory_items` | id | 15 | 3 | — | 0 |

## Production

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `production_orders` | id | 94 | 14 | oui | 0 |
| `production_outputs` | id | 21 | 6 | — | 0 |
| `production_consumptions` | id | 15 | 4 | — | 0 |
| `production_quality_controls` | id | 15 | 3 | — | 0 |
| `bom_lines` | id | 19 | 5 | — | 9 |
| `routings` | id | 35 | 5 | oui | 8 |
| `production_lines` | id | 53 | 5 | oui | 6 |

## Comptabilité

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `journal_entries` | id | 22 | 5 | oui | 0 |
| `journal_entry_lines` | id | 16 | 2 | — | 0 |
| `fiscal_years` | id | 27 | 1 | oui | 2 |
| `analytic_lines` | id | 14 | 4 | — | 0 |
| `accounting_budgets` | id | 13 | 2 | — | 1 |
| `accounting_budget_lines` | id | 10 | 2 | — | 0 |
| `fixed_assets` | id | 41 | 1 | oui | 1 |

## RH / Paie

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `payroll_runs` | id | 21 | 3 | — | 0 |
| `attendances` | id | 13 | 2 | — | 0 |
| `leave_requests` | id | 14 | 2 | — | 1 |
| `leave_types` | id | 11 | 1 | — | 8 |
| `leave_balances` | id | 9 | 2 | — | 0 |
| `employee_contracts` | id | 11 | 2 | — | 1 |

## Système

| Table | PK | Colonnes | FK sortantes | Soft delete | Volume |
|---|---|---:|---:|---|---:|
| `roles` | id | 5 | 0 | — | 21 |
| `permissions` | id | 5 | 0 | — | 151 |
| `model_has_roles` | id | 3 | 2 | — | 20 |
| `notifications` | id | 8 | 1 | — | 0 |
| `audit_logs` | id | 12 | 2 | — | 0 |
| `document_sequences` | id | 18 | 2 | — | 24 |
| `api_integrations` | id | 26 | 1 | — | 1 |
| `external_transactions` | id | 23 | 4 | — | 0 |

> Régénérer : `php artisan tinker <script>` ou voir docs/DEPLOIEMENT.md. Audit d'intégrité : `php artisan a3:audit-database`.
