{{--
    Layout — bloc <head> complet.
    Inclus depuis layouts/erp.blade.php.

    Charge :
      - Meta (viewport, CSRF, turbo-prefetch off)
      - Favicon SVG inline (data URI — pas de 404 selon sous-chemin)
      - Police Inter locale (@fontsource, bundlée via app.css — pas de CDN)
      - Vite (app.css + app.js)
      - DataTables CDN (CSS + JS)
      - @stack('styles') et @stack('head_scripts') pour extensions par vue
--}}
{{-- Dark mode: appliqué immédiatement en inline pour éviter le flash (avant Vite).
     data-turbo-eval="false" → Turbo ne réexécute PAS ce script lors des navigations
     (la classe 'dark' sur <html> persiste entre pages car <html> n'est jamais remplacé). --}}
<script data-turbo-eval="false">
(function(){var d=localStorage.getItem('erp_dark');if(d==='true'||(d===null&&window.matchMedia('(prefers-color-scheme:dark)').matches))document.documentElement.classList.add('dark');})();
</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
{{-- Garde précoce : avale les rejets fetch « NetworkError » bénins (prefetch/navigation
     annulée, CDN police, extension). Inline pour être actif AVANT tout autre script. --}}
<script>
    window.addEventListener('unhandledrejection', function (e) {
        var r = e.reason, m = (r && (r.message || r.name)) || String(r || '');
        if (/NetworkError|Failed to fetch|Load failed|aborted|AbortError/i.test(m)) {
            e.preventDefault();
        }
    });
</script>
{{-- Désactive le prefetch automatique de Turbo 8 sur hover (source de NetworkError
     quand le serveur répond lentement). Turbo vérifie cette meta à chaque tentative. --}}
<meta name="turbo-prefetch" content="false">
{{-- [FIX Turbo/CSRF] Formulaires create/edit : pas de snapshot cache Turbo. Une page de
     formulaire restaurée depuis le cache Turbo porte un token @csrf figé au moment du
     cache ; après régénération de session (login), la soumission part avec un token
     périmé → 419 silencieux (rechargement, rien enregistré, aucun message). `no-cache`
     force un rendu frais avec token courant. Ciblé aux routes de saisie. --}}
@if(request()->routeIs('*.create') || request()->routeIs('*.edit'))
<meta name="turbo-cache-control" content="no-cache">
@endif
<title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
{{-- Favicon en SVG inline (data URI) — évite tout 404 quel que soit le sous-chemin --}}
<link rel="icon" href="data:image/svg+xml,&lt;svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22&gt;&lt;rect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%234f46e5%22/&gt;&lt;text x=%2250%22 y=%2270%22 font-size=%2270%22 font-family=%22Arial%22 font-weight=%22700%22 fill=%22white%22 text-anchor=%22middle%22&gt;A&lt;/text&gt;&lt;/svg&gt;">
{{-- [Typo globale] Inter servie en local via @fontsource (bundlée app.css) — CDN bunny.net supprimé --}}
@vite(['resources/css/app.css', 'resources/css/erp-theme.css', 'resources/js/app.js'])

{{-- DataTables --}}
<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.tailwindcss.min.css">
{{-- buttons.tailwindcss.min.css n'existe pas sur le CDN v3.0.2 — le style est géré en bas de ce fichier --}}

{{-- [FIX BUG-009] Masque les flèches spinner des champs number : dans les tableaux
     de lignes serrés (devis/commande/facture) elles masquaient les chiffres. --}}
<style>
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; appearance: textfield; }
</style>

@stack('styles')

{{-- Scripts chargés une seule fois dans <head> par les vues qui en ont besoin --}}
@stack('head_scripts')

{{-- Utilitaires globaux statiques (data-turbo-eval="false" = chargé une seule fois) --}}
<script data-turbo-eval="false">
    window.fcfa = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n) + ' FCFA';
</script>

{{-- jQuery + DataTables : chargés une seule fois grâce à data-turbo-eval="false" --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.tailwindcss.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js" data-turbo-eval="false"></script>
{{-- buttons.tailwindcss.min.js n'existe pas sur le CDN v3.0.2 — le rendu est géré dans initDataTables (app.js) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" data-turbo-eval="false"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" data-turbo-eval="false"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js" data-turbo-eval="false"></script>
