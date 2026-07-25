<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Société
            'company.view', 'company.edit',
            // Direction — tableau de bord exécutif + chaîne de valeur (§3/§15 CDC)
            'direction.view',
            // Articles
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // [X3] Catégories & familles d'articles
            'categories.view', 'categories.create', 'categories.update', 'categories.disable', 'categories.propagate',
            'families.view', 'families.create', 'families.update', 'families.disable',
            'articles.change_category', 'articles.override_category_defaults',
            // Clients
            'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
            // CRM (prospection : contacts/leads, opportunités, activités)
            'crm.view', 'crm.manage',
            // Fournisseurs
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            // Ventes
            'quotes.view', 'quotes.create', 'quotes.edit', 'quotes.delete', 'quotes.validate',
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.validate', 'orders.reopen',
            'orders.edit_validated', // [CDC §13.1] modifier une commande déjà validée (prix verrouillés pour les autres)
            'bon_preparations.view', 'bon_preparations.update',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.validate', 'invoices.send',
            'deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.validate',
            'credit_notes.view', 'credit_notes.create', 'credit_notes.edit',
            // Workflow de validation interne Ventes (transversal à tous les documents)
            'sales.create',       // créer un document en brouillon
            'sales.submit',       // soumettre à validation interne
            'sales.validate',     // valider (responsable)
            'sales.reject',       // refuser avec motif obligatoire
            'sales.cancel',       // annuler avec motif obligatoire
            'sales.transform',              // transformer (Devis→Commande→BL→Facture→Écriture)
            'sales.view_all',               // voir tous les documents (pas seulement les siens)
            'sales.bypass_self_validation', // valider son propre document (bypass double validation)
            'sales_below_floor.request', 'sales_below_floor.approve',
            'sales_below_floor.reject', 'sales_below_floor.cancel',
            // Achats
            'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.submit', 'purchase_requests.approve',
            'purchase_requests.validate_l1', // validation chef service (<500k FCFA)
            'purchase_requests.validate_l2', // validation direction (<5M FCFA)
            'purchase_requests.validate_l3', // validation DG (≥5M FCFA) — §13.4
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.edit',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            // Stocks
            'stocks.view', 'stocks.adjust', 'stocks.transfer',
            'stocks.lot.trace',  // traçabilité lot → clients (§8 CDC)
            'inventory.view', 'inventory.create', 'inventory.validate',
            // Production / Fabrication tôles bac + métaux (§9 CDC)
            'production.view', 'production.create', 'production.update', 'production.delete',
            'production.launch', 'production.validate', 'production.cancel',
            'production.declare',           // déclaration production/rebuts (opérateurs §15 CDC)
            'production.validate_declaration', // visa chef d'équipe sur les déclarations de production (§13.3 CDC)
            'production.approve_financial',    // validation financière avant lancement OF (§13.2 CDC)
            'production.modify_launched',      // demande modification OF lancé (§13.10 CDC)
            // Modification OF exceptionnelle — workflow 4 étapes (§13.10 CDC)
            'production.modification.avis_chef',       // 1. avis Chef Production
            'production.modification.avis_commercial', // 2. avis Commercial
            'production.modification.avis_finance',    // 3. avis Finance (DAF)
            'production.modification.avis_dg',          // 4. validation finale DG
            'production.submit_validation',    // soumission OF pour validation 2-niveaux (§13.3 CDC)
            'production.validate_chef',        // validation Chef Atelier (§13.3 CDC)
            'production.validate_responsable', // validation Responsable Production (§13.3 CDC)
            'production.cost.view', 'production.report.view',
            // Qualité — module autonome (§10 CDC)
            'quality.view',   // consulter inspections et non-conformités
            'quality.manage', // créer/valider/clôturer inspections et NC
            'quality.nc.manage', // gestion spécifique non-conformités et actions correctives
            // Maintenance (§13.8 CDC)
            'maintenance.view',   // consulter les ordres de travail et interventions
            'maintenance.manage', // créer/valider/clôturer ordres de travail
            // Comptabilité analytique (§12 CDC)
            'analytic.view',   // consulter centres de coûts et lignes analytiques
            'analytic.manage', // créer/modifier centres de coûts et ventilations
            // Trésorerie
            'payments.view', 'payments.create', 'payments.edit',
            'cash_accounts.view', 'cash_accounts.manage',
            'treasury.write', 'treasury.validate', 'treasury.view',
            // Rapports
            'reports.view', 'reports.export',
            // Comptabilité SYSCOHADA
            'accounting.view', 'accounting.write', 'accounting.validate', 'accounting.manage',
            // Intégrations API & déclarations fiscales DGI
            'integrations.view', 'integrations.manage', 'integrations.declare',
            // Admin
            'users.manage', 'roles.manage', 'settings.manage', 'audit.view',
            // RH — module Ressources Humaines (paie, congés, prêts, employés)
            'rh.view',              // accès module RH
            'rh.employees.view',    // consulter fiches employés
            'rh.employees.manage',  // créer/modifier/archiver employés
            'rh.leaves.view',       // consulter congés
            'rh.leaves.manage',     // valider/refuser congés
            'rh.loans.view',        // consulter prêts salarié
            'rh.loans.manage',      // valider/gérer prêts
            'rh.payroll.view',      // consulter bulletins et runs de paie
            'rh.payroll.manage',    // créer et calculer runs de paie
            'rh.payroll.validate',  // valider (marquer payé) un run de paie
            'rh.portail',           // accès portail employé self-service
            'rh.settings',          // paramétrage RH (barèmes, cotisations, profils)
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Super Admin — tous les droits
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Directeur — tout sauf users.manage, roles.manage
        $directeur = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $directeur->syncPermissions(Permission::whereNotIn('name', ['users.manage', 'roles.manage'])->get());

        // Commercial — ventes + clients + workflow (create + submit + transform)
        // [CDC §13.1] Le commercial crée/modifie devis et crée commandes ; il ne
        // valide PAS financièrement (rôle Finance) et ne génère pas d'OF (Chef Production).
        $commercial = Role::firstOrCreate(['name' => 'commercial', 'guard_name' => 'web']);
        $commercial->syncPermissions([
            'products.view', 'clients.view', 'clients.create', 'clients.edit',
            'crm.view', 'crm.manage',   // prospection CRM
            'quotes.view', 'quotes.create', 'quotes.edit',
            'orders.view', 'orders.create', 'orders.edit',
            'bon_preparations.view', // [CDC §BP] suivi pour créer BL après chargement
            'invoices.view', 'invoices.create', 'invoices.send',
            'deliveries.view', 'deliveries.create',
            'credit_notes.view', 'credit_notes.create',
            'payments.view', 'reports.view',
            'stocks.view',    // lecture stock pour info dispo sur devis/commandes
            // [CDC §13.1] Workflow : le commercial crée, soumet et transforme —
            // la validation financière des commandes appartient à Finance (comptable/daf).
            'sales.create', 'sales.submit', 'sales.transform', 'sales_below_floor.request',
            // [FIX-BUG] production.view manquait — sans elle le groupe de routes
            // production/* (gate de groupe) était inaccessible, rendant
            // production.modify_launched et avis_commercial inopérants.
            'production.view',
            'production.modify_launched', // demander modif OF lié à sa commande (§13.10 CDC)
            'production.modification.avis_commercial', // §13.10 — étape 2/4
        ]);

        // Comptable — factures + trésorerie + rapports + workflow validation factures/avoirs
        $comptable = Role::firstOrCreate(['name' => 'comptable', 'guard_name' => 'web']);
        $comptable->syncPermissions([
            'products.view', 'clients.view', 'suppliers.view',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.validate',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.edit',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            'credit_notes.view', 'credit_notes.create',
            'payments.view', 'payments.create', 'payments.edit',
            'cash_accounts.view', 'cash_accounts.manage',
            'treasury.write', 'treasury.validate', 'treasury.view',
            'reports.view', 'reports.export',
            'accounting.view', 'accounting.write', 'accounting.validate', 'accounting.manage',
            // Intégrations : consulter + déclarer la TVA à la DGI (gestion des clés réservée au directeur)
            'integrations.view', 'integrations.declare',
            // Workflow : le comptable valide les factures et avoirs, peut annuler
            'sales.validate', 'sales.reject', 'sales.cancel', 'sales.view_all',
            'sales_below_floor.approve', 'sales_below_floor.reject', 'sales_below_floor.cancel',
            'production.modification.avis_finance', // §13.10 — étape 3/4
        ]);

        // Magasinier — stocks + réceptions + préparation + validation livraisons physiques
        $magasinier = Role::firstOrCreate(['name' => 'magasinier', 'guard_name' => 'web']);
        $magasinier->syncPermissions([
            'products.view', 'stocks.view', 'stocks.adjust', 'stocks.transfer',
            'stocks.lot.trace',  // traçabilité lots bobines (réception : largeur, épaisseur, couleur, lot, fournisseur)
            'inventory.view', 'inventory.create', 'inventory.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            'purchase_orders.view',
            'deliveries.view', 'deliveries.create', 'deliveries.validate', // préparation + contrôle chargement
            'orders.view',    // voir les commandes à préparer
            'bon_preparations.view', 'bon_preparations.update', // [CDC §BP] procéder au chargement
            'invoices.view',  // vérifier si facturé avant expédition
            'production.view', // suivi production / stock produits finis
        ]);

        // Chef de production — fabrication tôles bac (OF, bobines, production, coûts)
        $chefProduction = Role::firstOrCreate(['name' => 'chef_production', 'guard_name' => 'web']);
        $chefProduction->syncPermissions([
            'products.view', 'clients.view', 'suppliers.view',
            'stocks.view', 'stocks.adjust',
            'production.view', 'production.create', 'production.update', 'production.delete',
            'production.launch', 'production.validate', 'production.cancel',
            'production.validate_declaration', // §13.3 — visa déclarations (proxy chef d'équipe)
            'production.modify_launched',
            'production.submit_validation',    // §13.3 — soumettre OF pour validation
            'production.validate_responsable', // §13.3 — valider en tant que Responsable Production
            'production.modification.avis_chef', // §13.10 — étape 1/4
            'production.cost.view', 'production.report.view',
            'quality.view',        // suivi qualité production
            'maintenance.view',    // suivi maintenance équipements
            'analytic.view',       // coûts de revient
            'orders.view',
        ]);

        // ── Nouveaux rôles conformes §15 CDC ────────────────────────────────────

        // DAF — Directeur Administratif et Financier
        // Valide financièrement les OF, supervise trésorerie + comptabilité, analyse crédit clients
        $daf = Role::firstOrCreate(['name' => 'daf', 'guard_name' => 'web']);
        $daf->syncPermissions([
            // Comptabilité complète
            'accounting.view', 'accounting.write', 'accounting.validate', 'accounting.manage',
            // Intégrations fiscales
            'integrations.view', 'integrations.declare',
            // Trésorerie
            'payments.view', 'payments.create', 'payments.edit',
            'cash_accounts.view', 'cash_accounts.manage',
            'treasury.write', 'treasury.validate', 'treasury.view',
            // Factures/avoirs
            'invoices.view', 'invoices.validate', 'invoices.create', 'invoices.edit',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.edit',
            'credit_notes.view', 'credit_notes.create',
            // Achats — validation direction (<5M)
            'purchase_requests.view', 'purchase_requests.validate_l1', 'purchase_requests.validate_l2',
            'purchase_orders.view', 'purchase_orders.validate',
            'receptions.view', 'supplier_returns.view',
            // Workflow ventes
            'sales.validate', 'sales.reject', 'sales.cancel', 'sales.view_all',
            'sales_below_floor.approve', 'sales_below_floor.reject', 'sales_below_floor.cancel',
            // Validation financière OF (§13.2 CDC) — DAF débloque fabrication
            'production.view', 'production.approve_financial',
            'production.modification.avis_finance', // §13.10 — étape 3/4
            // Direction — synthèse exécutive + chaîne de valeur
            'direction.view',
            // Analytique
            'analytic.view', 'analytic.manage',
            // Référentiels lecture
            'products.view', 'clients.view', 'suppliers.view',
            'stocks.view', 'reports.view', 'reports.export',
        ]);

        // Directeur Usine — pilotage opérationnel usine (entre DG et chef_production)
        $directeurUsine = Role::firstOrCreate(['name' => 'directeur_usine', 'guard_name' => 'web']);
        $directeurUsine->syncPermissions([
            // Production complète
            'production.view', 'production.create', 'production.update', 'production.delete',
            'production.launch', 'production.validate', 'production.cancel',
            'production.validate_declaration', // §13.3 — visa déclarations
            'production.modify_launched',
            'production.submit_validation',    // §13.3
            'production.validate_chef',        // §13.3
            'production.validate_responsable', // §13.3
            'production.modification.avis_chef', // §13.10 — étape 1/4 (pilotage usine, proxy chef si absent)
            'production.cost.view', 'production.report.view',
            // Direction — synthèse exécutive + chaîne de valeur
            'direction.view',
            // Qualité + maintenance
            'quality.view', 'quality.manage', 'quality.nc.manage',
            'maintenance.view', 'maintenance.manage',
            // Stocks
            'stocks.view', 'stocks.adjust', 'stocks.transfer', 'stocks.lot.trace',
            'inventory.view', 'inventory.create', 'inventory.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            // Achats — validation chef service (<500k)
            'purchase_requests.view', 'purchase_requests.validate_l1',
            'purchase_orders.view',
            // Analytique
            'analytic.view', 'analytic.manage',
            // Référentiels
            'products.view', 'suppliers.view', 'clients.view',
            'orders.view', 'deliveries.view',
            'reports.view', 'reports.export',
        ]);

        // Acheteur — approvisionnements complets (§15 CDC)
        $acheteur = Role::firstOrCreate(['name' => 'acheteur', 'guard_name' => 'web']);
        $acheteur->syncPermissions([
            'products.view', 'suppliers.view', 'suppliers.create', 'suppliers.edit',
            // Achats complets
            'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.submit',
            'purchase_requests.validate_l1',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.edit',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            // Stocks lecture
            'stocks.view', 'inventory.view',
            // Qualité réception
            'quality.view',
            // RFQ (consultations fournisseurs)
            'reports.view',
        ]);

        // Responsable Qualité — contrôles, non-conformités, certifications (§10 CDC)
        $responsableQualite = Role::firstOrCreate(['name' => 'responsable_qualite', 'guard_name' => 'web']);
        $responsableQualite->syncPermissions([
            // Qualité complète
            'quality.view', 'quality.manage', 'quality.nc.manage',
            // Production lecture (contrôles en cours de production)
            'production.view', 'production.report.view',
            // Stocks lecture (contrôle réception matière + PF)
            'stocks.view', 'stocks.lot.trace',
            'inventory.view',
            'receptions.view',
            // Rebuts — validation qualité (§13.9 CDC)
            'production.declare',  // déclarer rebut + valider NC
            // Référentiels
            'products.view', 'suppliers.view',
            'reports.view', 'reports.export',
        ]);

        // Technicien Maintenance — ordres de travail, interventions (§13.8 CDC)
        $technicienMaintenance = Role::firstOrCreate(['name' => 'technicien_maintenance', 'guard_name' => 'web']);
        $technicienMaintenance->syncPermissions([
            'maintenance.view', 'maintenance.manage',
            'production.view',  // voir machines et OF pour planifier interventions
            'stocks.view',      // pièces de rechange
            'products.view',
            'reports.view',
        ]);

        // Chef Atelier — supervision opérateurs, validation déclarations, validation OF §13.3
        $chefAtelier = Role::firstOrCreate(['name' => 'chef_atelier', 'guard_name' => 'web']);
        $chefAtelier->syncPermissions([
            'production.view',
            'production.validate_chef',  // §13.3 — 1ère étape validation OF avant lancement
            'production.declare',        // valider les déclarations opérateurs (rebuts, temps)
            'production.validate_declaration', // §13.3 — visa chef d'équipe sur déclarations de production
            'quality.view',
            'maintenance.view',
            'stocks.view',
            'reports.view',
        ]);

        // Opérateur de production — déclaration production, temps, rebuts (§15 CDC)
        $operateurProduction = Role::firstOrCreate(['name' => 'operateur_production', 'guard_name' => 'web']);
        $operateurProduction->syncPermissions([
            'production.view',    // consulter les OF assignés
            'production.declare', // déclarer production, temps, consommation, rebuts
            'stocks.view',        // consulter niveaux stock pour approvisionnement poste
            'maintenance.view',   // consulter OT maintenance (arrêts planifiés machines)
        ]);

        // ── Rôles complémentaires ─────────────────────────────────────────────

        // Responsable commercial — périmètre commercial élargi + validation
        $responsableCommercial = Role::firstOrCreate(['name' => 'responsable_commercial', 'guard_name' => 'web']);
        $responsableCommercial->syncPermissions([
            'products.view', 'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
            'crm.view', 'crm.manage',
            'quotes.view', 'quotes.create', 'quotes.edit', 'quotes.delete',
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.validate', 'orders.reopen',
            'orders.edit_validated', // [CDC §13.1] seul un responsable peut retoucher une commande validée
            'bon_preparations.view', // [CDC §BP] suivi des bons de préparation
            'invoices.view', 'invoices.create', 'invoices.send',
            'deliveries.view', 'deliveries.create',
            'credit_notes.view', 'credit_notes.create',
            'payments.view', 'reports.view',
            'stocks.view',
            'sales.create', 'sales.submit', 'sales.transform', 'sales_below_floor.request', 'sales.validate', 'sales.view_all',
        ]);

        // Responsable stock — gestion stock + inventaire + réceptions
        $responsableStock = Role::firstOrCreate(['name' => 'responsable_stock', 'guard_name' => 'web']);
        $responsableStock->syncPermissions([
            'products.view', 'products.create', 'products.edit',
            'stocks.view', 'stocks.adjust', 'stocks.transfer', 'stocks.lot.trace',
            'inventory.view', 'inventory.create', 'inventory.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            'purchase_orders.view', 'deliveries.view', 'orders.view', 'invoices.view',
            'production.view', 'reports.view',
        ]);

        // Caissier — paiements clients + trésorerie courante
        $caissier = Role::firstOrCreate(['name' => 'caissier', 'guard_name' => 'web']);
        $caissier->syncPermissions([
            'clients.view',
            'orders.view',     // [CDC §cash] voir les commandes au comptant
            'invoices.view',
            'payments.view', 'payments.create', 'payments.edit',
            'bon_preparations.view', // [CDC §cash] consulter les BP créés après paiement
            'cash_accounts.view', 'cash_accounts.manage',
            'treasury.write', 'treasury.view',
            'reports.view',
        ]);

        // Lecture seule — auditeurs, consultants, direction en lecture
        $lectureSeule = Role::firstOrCreate(['name' => 'lecture_seule', 'guard_name' => 'web']);
        $lectureSeule->syncPermissions([
            'products.view', 'clients.view', 'suppliers.view',
            'quotes.view', 'orders.view', 'invoices.view',
            'deliveries.view', 'credit_notes.view',
            'purchase_orders.view', 'receptions.view', 'supplier_invoices.view',
            'stocks.view', 'inventory.view',
            'payments.view', 'cash_accounts.view', 'treasury.view',
            'accounting.view',
            'reports.view',
            'production.view', 'quality.view', 'maintenance.view',
            'analytic.view',
        ]);

        // ── Rôles RH ─────────────────────────────────────────────────────────────

        // DRH — Directeur des Ressources Humaines (accès RH complet + settings)
        $drh = Role::firstOrCreate(['name' => 'drh', 'guard_name' => 'web']);
        $drh->syncPermissions([
            'rh.view', 'rh.employees.view', 'rh.employees.manage',
            'rh.leaves.view', 'rh.leaves.manage',
            'rh.loans.view', 'rh.loans.manage',
            'rh.payroll.view', 'rh.payroll.manage', 'rh.payroll.validate',
            'rh.portail', 'rh.settings',
            'reports.view', 'reports.export',
            'analytic.view',
        ]);

        // Responsable RH / RH Manager — paie + congés + prêts, sans paramétrage
        $rhManager = Role::firstOrCreate(['name' => 'rh_manager', 'guard_name' => 'web']);
        $rhManager->syncPermissions([
            'rh.view', 'rh.employees.view', 'rh.employees.manage',
            'rh.leaves.view', 'rh.leaves.manage',
            'rh.loans.view', 'rh.loans.manage',
            'rh.payroll.view', 'rh.payroll.manage', 'rh.payroll.validate',
            'rh.portail',
            'reports.view',
        ]);

        // Agent RH — saisie et consultation, sans validation paie ni paramétrage
        $rhAgent = Role::firstOrCreate(['name' => 'rh_agent', 'guard_name' => 'web']);
        $rhAgent->syncPermissions([
            'rh.view', 'rh.employees.view', 'rh.employees.manage',
            'rh.leaves.view',
            'rh.loans.view',
            'rh.payroll.view', 'rh.payroll.manage',
            'rh.portail',
            'reports.view',
        ]);

        // Employé — portail self-service uniquement
        $employe = Role::firstOrCreate(['name' => 'employe', 'guard_name' => 'web']);
        $employe->syncPermissions([
            'rh.portail',
        ]);

        $this->command->info('Roles & Permissions créés avec succès (21 rôles complets).');
    }
}
