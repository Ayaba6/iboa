{{-- <x-erp.badge status="valide" /> ou <x-erp.badge variant="success" dot>Validé</x-erp.badge> --}}
@props(['variant' => null, 'status' => null, 'dot' => false])
@php
    // Mapping des statuts métier → variante + libellé
    $map = [
        'brouillon'             => ['muted',   'Brouillon'],
        'en_attente'            => ['warning', 'En attente'],
        'en_attente_validation' => ['warning', 'En attente de validation'],
        'soumis'                => ['info',    'Soumis'],
        'valide'                => ['success', 'Validé'],
        'confirme'              => ['success', 'Confirmé'],
        'rejete'                => ['danger',  'Rejeté'],
        'annule'                => ['muted',   'Annulé'],
        'cloture'               => ['muted',   'Clôturé'],
        'paye'                  => ['success', 'Payé'],
        'payee'                 => ['success', 'Payée'],
        'partiellement_payee'   => ['warning', 'Partiellement payée'],
        'en_cours'              => ['info',    'En cours'],
        'bloque'                => ['danger',  'Bloqué'],
        'conforme'              => ['success', 'Conforme'],
        'non_conforme'          => ['danger',  'Non conforme'],
        'en_retard'             => ['danger',  'En retard'],
    ];
    if ($status !== null) {
        [$variant, $label] = $map[$status] ?? ['muted', ucfirst(str_replace('_', ' ', (string) $status))];
    }
    $variant = $variant ?: 'muted';
@endphp
<span {{ $attributes->merge(['class' => 'erp-badge erp-badge-' . $variant . ($dot ? ' erp-badge-dot' : '')]) }}>{{ $slot->isEmpty() ? ($label ?? '') : $slot }}</span>
