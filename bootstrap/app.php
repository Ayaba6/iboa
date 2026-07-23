<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // [FIX-CRITIQUE] Laravel découvre automatiquement app/Listeners par défaut et
    // enregistre CHAQUE listener une seconde fois en plus des Event::listen()
    // explicites dans AppServiceProvider::boot() — doublant réservations stock,
    // soldes client/fournisseur et emails envoyés. Tous les listeners de l'app
    // sont déjà câblés explicitement : la découverte automatique est désactivée.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // [SEC-PHASE2] Coupe la session d'un compte désactivé (le login seul ne suffit pas)
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\SetCurrentCompany::class,
            \App\Http\Middleware\TrackLastLogin::class,
            \App\Http\Middleware\SecurityHeaders::class,
            // [CONCURRENCE-MULTI-USER] Anti-double-soumission sur tous les POST
            // (s'active uniquement si le champ _idempotency_key est présent)
            \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);
        // [SEC-PHASE2 §7] Canal API : un compte désactivé est refusé à chaque
        // requête, indépendamment de l'existence de son token.
        $middleware->api(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // [INVOICE-LOCK] Verrouille les factures encaissées (PUT/PATCH/DELETE → 403)
            'invoice.locked' => \App\Http\Middleware\InvoiceLockGuard::class,
            // [CONCURRENCE-MULTI-USER] Anti-double-soumission de formulaire
            'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);

        // Champs où l'espace est significatif (séparateurs typographiques, codes…)
        // → ne pas appliquer le trim automatique.
        $middleware->trimStrings(except: [
            'thousands_separator',
            'decimal_separator',
            'year_separator',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // En production : masquer les détails des erreurs non-HTTP
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
            return response()->view('errors.404', [], 404);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $code = $e->getStatusCode();
            $view = "errors.{$code}";
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'Erreur HTTP.'], $code);
            }
            if (view()->exists($view)) {
                return response()->view($view, [], $code);
            }
        });

        // Log minimal en production pour les exceptions non gérées
        $exceptions->reportable(function (\Throwable $e): bool {
            // Laisser Laravel logger l'erreur normalement
            return true;
        });

    })->create();
