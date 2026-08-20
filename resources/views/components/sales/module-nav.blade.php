@php
    $tabs = [
        ['route' => 'ventes.dashboard',               'pattern' => 'ventes.dashboard',               'label' => 'Tableau de bord',      'permission' => 'invoices.view'],
        ['route' => 'ventes.devis.index',             'pattern' => 'ventes.devis.*',                 'label' => 'Devis',                'permission' => 'quotes.view'],
        ['route' => 'ventes.commandes.index',         'pattern' => 'ventes.commandes.*',             'label' => 'Commandes',            'permission' => 'orders.view'],
        // [Ventes §4.3] Deux entrées distinctes, volontairement :
        //  - « Préparations » = bons QUANTIFIÉS (lignes, allocations, contrôle) ;
        //  - « Chargements » = bons historiques sans lignes (LEGACY_UNQUANTIFIED).
        // Les fusionner laisserait croire que les anciens portent des quantités.
        ['route' => 'ventes.preparations.index',      'pattern' => 'ventes.preparations.*',          'label' => 'Préparations',         'permission' => 'bon_preparations.view'],
        ['route' => 'ventes.bons-preparation.index',  'pattern' => 'ventes.bons-preparation.*',      'label' => 'Chargements',          'permission' => 'bon_preparations.view'],
        ['route' => 'ventes.bons-livraison.index',    'pattern' => 'ventes.bons-livraison.*',        'label' => 'Livraisons',           'permission' => 'deliveries.view'],
        ['route' => 'ventes.factures.index',          'pattern' => 'ventes.factures.*',              'label' => 'Factures',             'permission' => 'invoices.view'],
        ['route' => 'ventes.avoirs.index',            'pattern' => 'ventes.avoirs.*',                'label' => 'Avoirs',               'permission' => 'credit_notes.view'],
        // [Ventes §4] Contrats — huitième sous-module du cahier des charges.
        // Il était entièrement implémenté (route, contrôleur, liste et fiche,
        // permission `orders.view`) mais ABSENT de cette navigation : le seul
        // moyen d'y accéder était de taper l'URL. Un écran livré et injoignable
        // équivaut à un écran manquant.
        ['route' => 'ventes.contrats.index',          'pattern' => 'ventes.contrats.*',              'label' => 'Contrats',             'permission' => 'orders.view'],
    ];
@endphp

<nav aria-label="Navigation du module Ventes" class="bg-white border border-gray-300 rounded-[4px] overflow-x-auto">
    <div class="flex min-w-max px-1">
        @foreach($tabs as $tab)
            @can($tab['permission'])
                @php $active = request()->routeIs($tab['pattern']); @endphp
                <a href="{{ route($tab['route']) }}"
                   @if($active) aria-current="page" @endif
                   class="inline-flex items-center h-10 px-3 border-b-2 text-[12px] font-semibold transition-colors {{ $active ? 'border-emerald-700 text-emerald-800 bg-emerald-50/70' : 'border-transparent text-gray-600 hover:text-emerald-800 hover:bg-gray-50' }}">
                    {{ $tab['label'] }}
                </a>
            @endcan
        @endforeach
    </div>
</nav>