{{--
    Keyboard Shortcuts Help Overlay — Press `?` to show
--}}
<div x-data="{ open: false }"
     @keydown.?.window="open = true"
     @keydown.escape.window="open = false"
     @open-shortcuts.window="open = true"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-[9998] flex items-center justify-center px-4"
     style="display:none;">

    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"></div>

    <div class="relative bg-white rounded-[4px] shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Raccourcis clavier</h3>
            <button @click="open = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-5 grid grid-cols-2 gap-x-8 gap-y-1.5">
            @foreach([
                ['Navigation',    null],
                ['Ctrl K',        'Ouvrir la palette de commandes'],
                ['?',             'Afficher les raccourcis'],
                ['Esc',           'Fermer / Annuler'],
                ['Actions',       null],
                ['Alt N',         'Nouvelle facture'],
                ['Alt D',         'Nouveau devis'],
                ['Alt C',         'Nouveau contact CRM'],
                ['Interface',     null],
                ['Ctrl B',        'Replier/déployer la barre latérale'],
                ['Alt M',         'Basculer mode sombre'],
            ] as [$key, $label])
                @if($label === null)
                    <div class="col-span-2 pt-2 pb-0.5">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ $key }}</p>
                    </div>
                @else
                    <div class="flex items-center justify-between py-1">
                        <kbd class="text-xs font-semibold bg-gray-100 border border-gray-200 rounded px-2 py-0.5 shadow-sm">{{ $key }}</kbd>
                        <span class="text-xs text-gray-600 text-right ml-4">{{ $label }}</span>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 text-center">
            <p class="text-xs text-gray-400">Appuyez sur <kbd class="bg-white border border-gray-200 rounded px-1.5 py-0.5 font-semibold">?</kbd> pour afficher / masquer</p>
        </div>
    </div>
</div>

@push('scripts')
{{-- data-turbo-eval="false" → exécuté UNE SEULE FOIS (chargement initial).
     Sans ceci, Turbo ré-exécute le script à chaque navigation et empile les
     addEventListener, ce qui provoque N déclenchements pour N navigations. --}}
<script data-turbo-eval="false">
// ── Keyboard shortcuts globaux ───────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    // Ignorer si on est dans un input/textarea
    if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) return;

    // Alt + N → Nouvelle facture
    if (e.altKey && e.key === 'n') {
        e.preventDefault();
        @can('invoices.create')
        window.location.href = '{{ route("ventes.factures.create") }}';
        @endcan
        return;
    }
    // Alt + D → Nouveau devis
    if (e.altKey && e.key === 'd') {
        e.preventDefault();
        @can('quotes.create')
        window.location.href = '{{ route("ventes.devis.create") }}';
        @endcan
        return;
    }
    // Alt + C → Nouveau contact CRM
    if (e.altKey && e.key === 'c') {
        e.preventDefault();
        window.location.href = '{{ route("crm.contacts.create") }}';
        return;
    }
    // Alt + M → Dark mode toggle (via store Alpine pour synchroniser l'icône)
    if (e.altKey && e.key === 'm') {
        e.preventDefault();
        window.Alpine?.store('darkMode')?.toggle();
        return;
    }
    // Ctrl + B → Toggle sidebar
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        const sb = window.Alpine?.store('sidebar');
        if (sb) sb.collapsed = !sb.collapsed;
        return;
    }
});
</script>
@endpush
