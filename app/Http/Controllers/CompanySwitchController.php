<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    /**
     * Change la société active pour la session courante.
     *
     * Seuls les super-admins peuvent basculer vers n'importe quelle société.
     * Les autres utilisateurs ne peuvent qu'accéder à leur propre société.
     */
    public function __invoke(Request $request, Company $company)
    {
        $user = auth()->user();

        // Vérification d'autorisation
        // [FIX] Le rôle s'appelle « super_admin » (underscore) — l'ancien test
        // « super-admin » ne matchait jamais et cassait le switch multi-société.
        $canSwitch = $user->hasRole('super_admin')
            || $user->company_id === $company->id;

        abort_unless($canSwitch, 403, 'Accès refusé à cette société.');

        session(['active_company_id' => $company->id]);

        return redirect()->back()
            ->with('success', "Société basculée vers « {$company->name} ».");
    }
}
