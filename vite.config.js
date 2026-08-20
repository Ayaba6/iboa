import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => ({
    // On garde la base vide pour éviter les problèmes de sous-dossiers
    base: '',
    plugins: [
        react(), // Remplacé laravel-vite-plugin par le plugin React
    ],
    build: {
        target: 'es2020',
        minify: 'esbuild',
        esbuildOptions: {
            drop: ['debugger'],
            pure: ['console.log', 'console.info', 'console.debug'],
        },
        sourcemap: false,
        chunkSizeWarningLimit: 1024,
        rollupOptions: {
            treeshake: {
                moduleSideEffects: 'no-external',
                preset: 'smallest',
            },
            output: {
                manualChunks(id) {
                    // Adapté pour un ERP React : on sépare les librairies lourdes
                    if (id.includes('node_modules/apexcharts')) return 'apexcharts';
                    if (id.includes('node_modules/react')) return 'react-vendor';
                    if (id.includes('node_modules/@supabase')) return 'supabase';
                },
            },
        },
    },
    optimizeDeps: {
        // Ajout des dépendances pour React et Supabase
        include: ['react', 'react-dom', 'apexcharts', '@supabase/supabase-js'],
    },
    css: {
        devSourcemap: command === 'serve',
    },
}));