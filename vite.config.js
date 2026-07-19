import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ command }) => ({
    // [Typo globale] base relative : les url() du CSS bundlé (fontes Inter
    // @fontsource) étaient émises en /build/assets/… — cassées quand l'app vit
    // dans un sous-dossier (Laragon /iboa/public). En relatif, elles se
    // résolvent depuis le fichier CSS lui-même, quel que soit le point de
    // montage. Le helper @vite PHP continue de préfixer via asset().
    base: '',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/erp-theme.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Cible ES2020 : navigateurs modernes = bundle 5-8% plus léger (no legacy polyfills)
        target: 'es2020',
        // Minification agressive en production (esbuild = 20× plus rapide que terser)
        minify: 'esbuild',
        esbuildOptions: {
            // Supprime console.log en prod (hors console.error/warn)
            drop: ['debugger'],
            // Pure annotations pour tree-shaking agressif
            pure: ['console.log', 'console.info', 'console.debug'],
        },
        // Pas de sourcemaps en production (réduit la taille ~30%)
        sourcemap: false,
        // Seuil d'avertissement chunk (1 Mo)
        chunkSizeWarningLimit: 1024,
        rollupOptions: {
            treeshake: {
                // [PERF] Supprime les exports non utilisés dans les librairies
                moduleSideEffects: 'no-external',
                preset: 'smallest',
            },
            output: {
                // Code splitting : sépare les vendors des scripts page
                manualChunks(id) {
                    if (id.includes('node_modules/apexcharts')) return 'apexcharts';
                    if (id.includes('node_modules/@hotwired')) return 'turbo';
                    if (id.includes('node_modules/alpinejs')) return 'alpine';
                },
            },
        },
    },
    // Optimise les dépendances en mode dev (pre-bundle = démarrage rapide)
    optimizeDeps: {
        include: ['alpinejs', 'apexcharts', '@hotwired/turbo'],
    },
    // Compression CSS (supprime commentaires, espaces)
    css: {
        devSourcemap: command === 'serve',
    },
}));
