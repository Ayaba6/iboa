<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * [PRIO-6] Ajoute les headers de sécurité HTTP à toutes les réponses.
 *
 * Headers appliqués :
 *  - X-Frame-Options          : DENY (empêche le clickjacking par iframe)
 *  - X-Content-Type-Options   : nosniff (empêche le MIME-sniffing)
 *  - Referrer-Policy          : same-origin (limite la fuite de l'URL en navigation externe)
 *  - X-XSS-Protection         : 0 (déprécié mais explicite — laisse CSP gérer)
 *  - Permissions-Policy       : désactive les API sensibles non utilisées
 *  - Content-Security-Policy  : restrictive en prod, plus permissive en dev (Vite hot-reload)
 *
 * Le CSP autorise :
 *  - script-src : self + inline (Alpine.js inline x-data, scripts blade) + CDN datatables + Vite dev
 *  - style-src  : self + inline (Tailwind utility classes inline) + Google Fonts via Bunny
 *  - img-src    : self + data: (PDF previews, base64 inline)
 *  - font-src   : self + Google Fonts (bunny.net)
 *  - connect-src : self + WS Vite en dev
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Skip pour les réponses streamées (PDF download — DomPDF), API JSON, et assets
        if ($this->shouldSkip($request, $response)) {
            return $response;
        }

        $headers = [
            'X-Frame-Options'        => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'same-origin',
            'X-XSS-Protection'       => '0',
            'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=(), payment=()',
        ];

        // CSP : adapté dev (Vite hot-reload) vs prod
        $isDev = app()->environment('local');

        // [DEV-VITE] Quand `npm run dev` tourne, Laravel sert les assets depuis
        // le dev server Vite (http://[::1]:4000 ou localhost) — il faut donc
        // l'autoriser dans script-src ET style-src (pas seulement connect-src),
        // en couvrant l'hôte IPv6 [::1] que Firefox distingue de localhost.
        $viteDev = $isDev
            ? ' http://localhost:* http://127.0.0.1:* http://[::1]:* ws://localhost:* ws://127.0.0.1:* ws://[::1]:*'
            : '';

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.datatables.net https://cdnjs.cloudflare.com https://code.jquery.com{$viteDev}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://cdn.datatables.net{$viteDev}",
            "img-src 'self' data: blob:{$viteDev}",
            "font-src 'self' data: https://fonts.bunny.net{$viteDev}",
            // connect-src : ajoute les CDN aussi car le navigateur fetch les sourcemaps (.js.map)
            // via cette directive et pas via script-src.
            "connect-src 'self' https://cdn.datatables.net https://cdnjs.cloudflare.com https://code.jquery.com"
                . ($isDev ? ' ws:' : '') . $viteDev,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $headers['Content-Security-Policy'] = implode('; ', $csp);

        // HSTS uniquement en HTTPS
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Ne pas écraser un header déjà défini en aval (rare mais possible)
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Bypass des headers pour les types de réponses sensibles à la modification.
     */
    private function shouldSkip(Request $request, Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        // PDF generation streamée
        if (str_contains($contentType, 'application/pdf')) return true;
        // Excel exports
        if (str_contains($contentType, 'spreadsheetml')) return true;
        // Téléchargements binaires (attachments)
        if (str_contains($response->headers->get('Content-Disposition', ''), 'attachment')) return true;

        return false;
    }
}
