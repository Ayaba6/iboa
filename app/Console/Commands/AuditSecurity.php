<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * [SEC-PHASE2 §2] a3:audit-security — lecture seule, exit 1 si anomalie.
 * Contrôles : maker-checker inactif en production, conflits de séparation des
 * tâches dans les rôles, permissions critiques sans détenteur ou trop larges,
 * super-admins multiples, utilisateurs actifs sans rôle, permissions directes
 * hors rôle, utilisateurs désactivés porteurs de tokens API.
 */
class AuditSecurity extends Command
{
    protected $signature = 'a3:audit-security';
    protected $description = 'Audit sécurité : séparation des tâches, permissions, comptes (lecture seule)';

    private int $anomalies = 0;

    /** Paires incompatibles au sein d'un même rôle opérationnel. */
    private const CONFLICTS = [
        ['payments.create', 'treasury.cancel', 'encaisse ET annule les encaissements'],
        ['payments.create', 'cash_closures.validate', 'encaisse ET valide la clôture de caisse'],
        ['credit_notes.create', 'credit_notes.validate', 'crée ET valide les avoirs'],
        ['purchase_orders.create', 'purchase_orders.confirm', 'prépare ET confirme les commandes fournisseurs'],
        ['receptions.create', 'receptions.cancel', 'saisit ET annule les réceptions'],
        ['accounting.create', 'accounting.validate', 'saisit ET valide les écritures'],
    ];

    /** Rôles de contrôle : le cumul y est assumé (direction/supervision). */
    private const CONTROL_ROLES = ['super_admin', 'directeur', 'daf', 'directeur_usine', 'comptable', 'responsable_commercial', 'responsable_stock'];

    private const CRITICAL_PERMS = [
        'treasury.cancel', 'treasury.validate', 'credit_notes.validate',
        'accounting.validate', 'rh.payroll.validate', 'cash_closures.validate',
        'purchase_orders.confirm', 'receptions.cancel',
    ];

    public function handle(): int
    {
        // 1. Maker-checker en production
        if (app()->environment('production') && ! config('security.maker_checker.enabled')) {
            $this->fail_('CRITIQUE : maker-checker DÉSACTIVÉ en production (SECURITY_MAKER_CHECKER=false).');
        } elseif (! config('security.maker_checker.enabled')) {
            $this->line('  maker-checker inactif (environnement ' . app()->environment() . ' — toléré hors production)');
        }

        // 2. Conflits de séparation des tâches par rôle
        foreach (Role::with('permissions')->get() as $role) {
            if (in_array($role->name, self::CONTROL_ROLES, true)) {
                continue;
            }
            $names = $role->permissions->pluck('name')->all();
            foreach (self::CONFLICTS as [$a, $b, $label]) {
                if (in_array($a, $names, true) && in_array($b, $names, true)) {
                    $this->fail_("Conflit de séparation : rôle « {$role->name} » $label ($a + $b).");
                }
            }
        }

        // 3. Permissions critiques sans détenteur actif
        foreach (self::CRITICAL_PERMS as $p) {
            if (! Permission::where('name', $p)->exists()) {
                continue;
            }
            $holders = User::where('is_active', true)->permission($p)->count();
            if ($holders === 0) {
                $this->fail_("Permission critique « $p » : AUCUN utilisateur actif ne la détient (blocage opérationnel).");
            } elseif ($holders > 10) {
                $this->fail_("Permission critique « $p » : $holders détenteurs — attribution trop large.");
            }
        }

        // 4. Super-admins
        $sa = User::where('is_active', true)->role('super_admin')->count();
        if ($sa > 2) {
            $this->fail_("$sa super-admins actifs — maximum recommandé : 2.");
        }

        // 5. Utilisateurs actifs sans rôle
        $noRole = User::where('is_active', true)->doesntHave('roles')->count();
        if ($noRole > 0) {
            $this->fail_("$noRole utilisateur(s) actif(s) SANS rôle.");
        }

        // 6. Permissions directes hors rôle
        $direct = DB::table('model_has_permissions')->count();
        if ($direct > 0) {
            $this->fail_("$direct permission(s) directe(s) hors rôle — tout doit passer par les rôles.");
        }

        // 7. Utilisateurs désactivés avec tokens API encore présents
        if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
            $ghost = DB::table('personal_access_tokens')
                ->join('users', fn ($j) => $j->on('users.id', '=', 'personal_access_tokens.tokenable_id')
                    ->where('personal_access_tokens.tokenable_type', User::class))
                ->where('users.is_active', false)->count();
            if ($ghost > 0) {
                $this->fail_("$ghost token(s) API appartenant à des comptes désactivés — révocation à rejouer.");
            }
        }

        $this->newLine();
        if ($this->anomalies === 0) {
            $this->info('AUDIT SÉCURITÉ PROPRE — aucune anomalie détectée.');

            return self::SUCCESS;
        }
        $this->error("{$this->anomalies} anomalie(s) de sécurité — voir ci-dessus. Aucune modification effectuée.");

        return self::FAILURE;
    }

    private function fail_(string $msg): void
    {
        $this->anomalies++;
        $this->warn('  ✗ ' . $msg);
    }
}
