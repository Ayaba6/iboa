<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Crée (ou met à jour) un utilisateur de démonstration par rôle.
 * Idempotent — utilise firstOrCreate. Sûr en local.
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrFail();

        $users = [
            // Direction
            ['email' => 'admin@iboa.bf',             'name' => 'Administrateur IBOA',     'role' => 'super_admin',           'job' => 'Super Administrateur'],
            ['email' => 'directeur@iboa.bf',         'name' => 'Moussa Kaboré',            'role' => 'directeur',             'job' => 'Directeur Général'],
            ['email' => 'daf@iboa.bf',               'name' => 'Fatoumata Compaoré',      'role' => 'daf',                   'job' => 'DAF — Directrice Administrative et Financière'],
            ['email' => 'directeur.usine@iboa.bf',   'name' => 'Ibrahim Ouédraogo',        'role' => 'directeur_usine',       'job' => "Directeur d'Usine"],
            // Commercial
            ['email' => 'commercial@iboa.bf',        'name' => 'Aminata Sawadogo',         'role' => 'commercial',            'job' => 'Commerciale'],
            ['email' => 'resp.commercial@iboa.bf',   'name' => 'Sylvie Ilboudo',           'role' => 'responsable_commercial','job' => 'Responsable Commercial'],
            // Finance / Compta
            ['email' => 'comptable@iboa.bf',         'name' => 'Patricia Konaté',          'role' => 'comptable',             'job' => 'Comptable'],
            // Achats
            ['email' => 'acheteur@iboa.bf',          'name' => 'Rasmané Traoré',           'role' => 'acheteur',              'job' => 'Acheteur / Approvisionneur'],
            // Stock / Logistique
            ['email' => 'magasinier@iboa.bf',        'name' => 'Salif Diallo',             'role' => 'magasinier',            'job' => 'Magasinier'],
            ['email' => 'resp.stock@iboa.bf',        'name' => 'Wendyam Bassole',          'role' => 'responsable_stock',     'job' => 'Responsable Stock'],
            ['email' => 'caissier@iboa.bf',          'name' => 'Inès Tapsoba',             'role' => 'caissier',              'job' => 'Caissière'],
            // Production (§15 CDC)
            ['email' => 'chef.production@iboa.bf',   'name' => 'Adama Zongo',              'role' => 'chef_production',       'job' => 'Chef de Production'],
            ['email' => 'directeur.usine@iboa.bf',   'name' => 'Ibrahim Ouédraogo',        'role' => 'directeur_usine',       'job' => "Directeur d'Usine"],
            ['email' => 'operateur@iboa.bf',         'name' => 'Kofi Sawadogo',            'role' => 'operateur_production',  'job' => 'Opérateur de Production'],
            // Qualité (§15 CDC)
            ['email' => 'qualite@iboa.bf',           'name' => 'Mariam Kaboré',            'role' => 'responsable_qualite',   'job' => 'Responsable Qualité'],
            // Maintenance (§15 CDC)
            ['email' => 'maintenance@iboa.bf',       'name' => 'Noël Ouattara',            'role' => 'technicien_maintenance','job' => 'Technicien Maintenance'],
            // RH
            ['email' => 'drh@iboa.bf',               'name' => 'Clarisse Bonkoungou',      'role' => 'drh',                   'job' => 'Directrice RH'],
            ['email' => 'rh.manager@iboa.bf',        'name' => 'Boukary Nana',             'role' => 'rh_manager',            'job' => 'Gestionnaire RH'],
            ['email' => 'rh.agent@iboa.bf',          'name' => 'Aïssata Ouédraogo',        'role' => 'rh_agent',              'job' => 'Agent RH'],
            ['email' => 'employe@iboa.bf',           'name' => 'Jean-Pierre Kinda',        'role' => 'employe',               'job' => 'Employé'],
            // Audit
            ['email' => 'lecture@iboa.bf',           'name' => 'Auditeur Externe',         'role' => 'lecture_seule',         'job' => 'Lecture seule (audit)'],
        ];

        foreach ($users as $u) {
            $role = Role::where('name', $u['role'])->first();
            if (! $role) {
                $this->command->warn("Rôle introuvable : {$u['role']} — ignoré.");
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name'       => $u['name'],
                    'password'   => Hash::make('password'),
                    'company_id' => $company->id,
                    'job_title'  => $u['job'],
                    'is_active'  => true,
                ]
            );

            if (! $user->hasRole($u['role'])) {
                $user->assignRole($role);
            }

            $this->command->line("  ✓ {$user->email} → {$u['role']}");
        }

        $this->command->info('UsersSeeder terminé — ' . count($users) . ' utilisateurs traités.');
    }
}
