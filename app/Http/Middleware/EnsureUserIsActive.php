<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * [SEC-PHASE2] La désactivation d'un compte n'était contrôlée qu'au LOGIN
 * (LoginRequest) : une session déjà ouverte survivait à la désactivation.
 * Ce middleware coupe la session au premier aller-retour suivant.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            // [SEC-PHASE2 §7] Canal API : même si la révocation des tokens a
            // échoué (update de masse, SQL brut), un token existant ne donne
            // JAMAIS accès à un compte désactivé.
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(403, 'Compte désactivé.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Votre compte a été désactivé. Contactez un administrateur.']);
        }

        return $next($request);
    }
}
