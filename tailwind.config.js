import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'xs': ['14px', { lineHeight: '1.4' }],   // sous-titres sidebar (défaut Tailwind : 12px)
                'sm': ['16px', { lineHeight: '1.5' }],   // titres sidebar     (défaut Tailwind : 14px)
            },
            colors: {
                brand: {
                    dark:   '#0c0c1d',   // auth panel, login bg
                    darker: '#0f0f23',   // dashboard hero
                },
            },
            keyframes: {
                fadeInUp: {
                    '0%':   { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeInDown: {
                    '0%':   { opacity: '0', transform: 'translateY(-8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideInRight: {
                    '0%':   { opacity: '0', transform: 'translateX(20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                scaleIn: {
                    '0%':   { opacity: '0', transform: 'scale(0.95)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                shimmer: {
                    '0%':   { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                progressBar: {
                    '0%':   { width: '100%' },
                    '100%': { width: '0%' },
                },
            },
            animation: {
                'fade-in-up':    'fadeInUp 0.35s ease-out forwards',
                'fade-in-down':  'fadeInDown 0.25s ease-out forwards',
                'slide-in-right':'slideInRight 0.3s ease-out forwards',
                'scale-in':      'scaleIn 0.2s ease-out forwards',
                'progress-bar':  'progressBar linear forwards',
            },
        },
    },

    // ── Safelist — classes assemblées dynamiquement en PHP (Blade/PHP variables) ──
    // Tailwind JIT ne peut pas détecter les classes comme `bg-${color}-50` dans
    // les templates Blade quand la couleur est une variable PHP.
    // Ces patterns couvrent : CRM kanban, badges de statuts, composants ui.stat/badge.
    safelist: [
        // [FIX-VITE-SVG] Classes avec valeurs arbitraires sur SVG — le serveur dev Vite
        // ne les détecte pas toujours via le scanner JIT. Listées explicitement ici.
        'w-[18px]', 'h-[18px]',
        'w-3.5', 'h-3.5',   // spinner autosubmit (resources/js/app.js)

        // CRM kanban + Chaîne de Valeur : toutes les couleurs dynamiques
        { pattern: /^(bg|text|border|ring)-(sky|blue|violet|amber|emerald|red|indigo|gray|orange|teal|cyan|lime|purple|rose|green)-(50|100|200|300|400|500|600|700|800)$/ },

        // [TRESO] Dégradés des chips KPI (tableau de bord trésorerie) — from/to dynamiques
        { pattern: /^(from|to)-(sky|blue|violet|amber|emerald|red|indigo|lime|purple|rose)-(400|500|600)$/ },

        // Badges statuts dynamiques
        'bg-blue-100',   'text-blue-700',
        'bg-green-100',  'text-green-700',
        'bg-emerald-100','text-emerald-700',
        'bg-amber-100',  'text-amber-700',
        'bg-red-100',    'text-red-700',
        'bg-orange-100', 'text-orange-700',
        'bg-purple-100', 'text-purple-700',
        'bg-sky-100',    'text-sky-700',
        'bg-violet-100', 'text-violet-700',
        'bg-teal-100',   'text-teal-700',
        'bg-cyan-100',   'text-cyan-700',
        'bg-indigo-100', 'text-indigo-700',
        'bg-gray-100',   'text-gray-700',
    ],

    plugins: [forms],
};
