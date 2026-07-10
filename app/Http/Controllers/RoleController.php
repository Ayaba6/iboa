<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // Permissions groupées par module pour l'affichage
    private function groupedPermissions(): array
    {
        $groups = [
            // Référentiels
            'Produits'              => 'products',
            'Clients'               => 'clients',
            'Fournisseurs'          => 'suppliers',
            // Ventes
            'CRM'                   => 'crm',
            'Devis'                 => 'quotes',
            'Commandes ventes'      => 'orders',
            'Factures ventes'       => 'invoices',
            'Avoirs'                => 'credit_notes',
            'Bons de livraison'     => 'deliveries',
            'Workflow ventes'       => 'sales',
            // Achats
            'Demandes d\'achat'     => 'purchase_requests',
            'Commandes achats'      => 'purchase_orders',
            'Réceptions'            => 'receptions',
            'Factures fournisseurs' => 'supplier_invoices',
            'Retours fournisseurs'  => 'supplier_returns',
            // Stocks
            'Stocks'                => 'stocks',
            'Inventaires'           => 'inventory',
            // Production (§9 CDC)
            'Production'            => 'production',
            // Qualité (§10 CDC)
            'Qualité'               => 'quality',
            // Maintenance (§13.8 CDC)
            'Maintenance'           => 'maintenance',
            // Finance
            'Paiements'             => 'payments',
            'Trésorerie'            => 'cash_accounts',
            'Trésorerie (écriture)' => 'treasury',
            'Comptabilité SYSCOHADA'=> 'accounting',
            // Analytique (§12 CDC)
            'Comptabilité analytique' => 'analytic',
            // Rapports
            'Rapports'              => 'reports',
            // RH / Paie
            'Ressources Humaines'   => 'rh',
            // Intégrations & Admin
            'Intégrations'          => 'integrations',
            'Administration'        => null, // catch-all : users, roles, settings, company, audit
        ];

        $allPermissions = Permission::orderBy('name')->get();
        $grouped = [];

        // Prefixes connus
        $knownPrefixes = array_filter(array_values($groups));

        foreach ($groups as $label => $prefix) {
            if ($prefix) {
                $perms = $allPermissions->filter(fn($p) => str_starts_with($p->name, $prefix.'.'));
            } else {
                // Administration : tout ce qui ne correspond à aucun préfixe connu
                $perms = $allPermissions->filter(function ($p) use ($knownPrefixes) {
                    foreach ($knownPrefixes as $kp) {
                        if (str_starts_with($p->name, $kp.'.')) return false;
                    }
                    return true;
                });
            }
            if ($perms->isNotEmpty()) {
                $grouped[$label] = $perms;
            }
        }

        return $grouped;
    }

    public function index()
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::withCount(['permissions', 'users'])->orderBy('id')->get();
        return view('roles.index', compact('roles'));
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);

        $role->load('permissions');
        $users = User::role($role->name)->orderBy('name')->get();
        $grouped = $this->groupedPermissions();

        return view('roles.show', compact('role', 'users', 'grouped'));
    }

    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        $role->load('permissions');
        $grouped = $this->groupedPermissions();

        return view('roles.edit', compact('role', 'grouped'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->toArray();

        $permissionIds = collect($request->input('permissions', []))
            ->map(fn($id) => (int) $id)
            ->filter()
            ->toArray();

        $role->syncPermissions($permissionIds);

        // Vider le cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = $role->fresh('permissions')->permissions->pluck('name')->sort()->values()->toArray();

        $this->audit->log('updated', $role,
            ['permissions' => $oldPermissions],
            ['permissions' => $newPermissions]
        );

        return redirect()
            ->route('roles.show', $role)
            ->with('success', 'Permissions du rôle « '.ucfirst(str_replace('_', ' ', $role->name)).' » mises à jour.');
    }
}
