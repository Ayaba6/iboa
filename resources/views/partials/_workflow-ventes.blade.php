{{--
    Barre de progression du workflow ventes.

    Usage:
        @include('partials._workflow-ventes', [
            'currentStep' => 'devis',   // devis | commande | livraison | facture | paiement
            'quote'       => $quote,    // optionnel
            'order'       => $order,    // optionnel
            'deliveryNote'=> $dn,       // optionnel
            'invoice'     => $invoice,  // optionnel
        ])
--}}
@php
    $steps = [
        'devis'     => ['label' => 'Devis',          'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        'commande'  => ['label' => 'Commande',        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
        'livraison' => ['label' => 'Bon de livraison','icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6'],
        'facture'   => ['label' => 'Facture',         'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        'paiement'  => ['label' => 'Paiement',        'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];

    $stepKeys   = array_keys($steps);
    $currentIdx = array_search($currentStep ?? 'devis', $stepKeys);

    // Determine doc state per step
    $stepData = [
        'devis'     => isset($quote)       ? ['done' => in_array($quote->status, ['valide', 'converti', 'accepte', 'annule', 'refuse']), 'url' => route('ventes.devis.show', $quote), 'label' => $quote->number, 'status' => $quote->status] : null,
        'commande'  => isset($order)       ? ['done' => in_array($order->status, ['confirme', 'en_preparation', 'partiellement_livre', 'livre', 'facture', 'annule']), 'url' => route('ventes.commandes.show', $order), 'label' => $order->number, 'status' => $order->status] : null,
        'livraison' => isset($deliveryNote)? ['done' => in_array($deliveryNote->status, ['valide', 'livre', 'annule']),   'url' => route('ventes.bons-livraison.show', $deliveryNote), 'label' => $deliveryNote->number, 'status' => $deliveryNote->status]: null,
        'facture'   => isset($invoice)     ? ['done' => in_array($invoice->status, ['emise', 'envoyee', 'payee', 'partiellement_payee']), 'url' => route('ventes.factures.show', $invoice), 'label' => $invoice->number, 'status' => $invoice->status]: null,
        'paiement'  => isset($invoice)     ? ['done' => in_array($invoice->status, ['payee']),                           'url' => route('ventes.factures.show', $invoice),  'label' => $invoice->status === 'payee' ? 'Payé' : ($invoice->status === 'partiellement_payee' ? 'Partiel' : 'En attente'), 'status' => $invoice->status]: null,
    ];
@endphp

<div class="bg-white rounded-[4px] border border-gray-300 px-5 py-4 overflow-x-auto">
    <div class="flex items-center min-w-max gap-0">
        @foreach($steps as $key => $step)
            @php
                $idx      = array_search($key, $stepKeys);
                $isCurrent= ($key === ($currentStep ?? 'devis'));
                $isPast   = $idx < $currentIdx;
                $isFuture = $idx > $currentIdx;
                $data     = $stepData[$key] ?? null;
            @endphp

            {{-- Step circle + label --}}
            <div class="flex flex-col items-center">
                <div class="flex items-center">
                    @if($data && $data['url'])
                        <a href="{{ $data['url'] }}"
                           class="flex flex-col items-center group">
                    @else
                        <div class="flex flex-col items-center">
                    @endif

                        <div @class([
                            'w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 border-2 transition-colors',
                            'bg-emerald-600 border-emerald-600 text-white shadow-md ring-2 ring-emerald-200' => $isCurrent,
                            'bg-emerald-500 border-emerald-500 text-white'                                => $isPast && ($data['done'] ?? false),
                            'bg-white border-gray-300 text-gray-400'                                      => $isFuture || ($isPast && !($data['done'] ?? false)),
                        ])>
                            @if($isPast && ($data['done'] ?? false))
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $step['icon'] }}"/>
                                </svg>
                            @endif
                        </div>

                        <div class="mt-1.5 text-center">
                            <p @class([
                                'text-xs font-semibold',
                                'text-emerald-800' => $isCurrent,
                                'text-emerald-600'=> $isPast && ($data['done'] ?? false),
                                'text-gray-400'   => $isFuture,
                                'text-gray-500'   => $isPast && !($data['done'] ?? false),
                            ])>{{ $step['label'] }}</p>
                            @if($data && isset($data['label']))
                                <p class="text-xs text-gray-400 font-mono leading-tight">{{ $data['label'] }}</p>
                            @endif
                        </div>

                    @if($data && $data['url'])
                        </a>
                    @else
                        </div>
                    @endif
                </div>
            </div>

            {{-- Connector line --}}
            @if(!$loop->last)
                <div @class([
                    'h-0.5 w-16 mx-1 flex-shrink-0 mt-[-18px]',
                    'bg-emerald-400' => $idx < $currentIdx,
                    'bg-gray-200'    => $idx >= $currentIdx,
                ])></div>
            @endif
        @endforeach
    </div>
</div>
