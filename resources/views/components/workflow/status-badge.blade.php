{{--
    Composant : badge de statut d'un document commercial.

    Props :
      $status : string   — valeur du statut (ex. 'en_attente_validation')
      $label  : string   — texte affiché (ex. 'En attente de validation')
      $size   : string   — 'sm' | 'md' (défaut 'md')
--}}
@props([
    'status' => 'brouillon',
    'label'  => null,
    'size'   => 'md',
])

@php
    $colorMap = [
        'brouillon'             => 'bg-gray-100   text-gray-700   ring-gray-300',
        'en_attente_validation' => 'bg-yellow-100 text-yellow-800 ring-yellow-400 animate-pulse',
        'envoye'                => 'bg-blue-100   text-blue-700   ring-blue-300',
        'confirme'              => 'bg-green-100  text-green-700  ring-green-300',
        'valide'                => 'bg-green-100  text-green-700  ring-green-300',
        'validee'               => 'bg-green-100  text-green-700  ring-green-300',
        'emise'                 => 'bg-blue-100   text-blue-700   ring-blue-300',
        'envoyee'               => 'bg-indigo-100 text-indigo-700 ring-indigo-300',
        'accepte'               => 'bg-green-100  text-green-700  ring-green-300',
        'partiellement_payee'   => 'bg-amber-100  text-amber-700  ring-amber-300',
        'payee'                 => 'bg-green-200  text-green-800  ring-green-400',
        'en_retard'             => 'bg-red-100    text-red-700    ring-red-300',
        'partiellement_livre'   => 'bg-indigo-100 text-indigo-700 ring-indigo-300',
        'livre'                 => 'bg-teal-100   text-teal-700   ring-teal-300',
        'facture'               => 'bg-purple-100 text-purple-700 ring-purple-300',
        'applique'              => 'bg-teal-100   text-teal-700   ring-teal-300',
        'converti'              => 'bg-green-100  text-green-700  ring-green-300',
        'annule'                => 'bg-red-100    text-red-600    ring-red-300',
        'annulee'               => 'bg-red-100    text-red-600    ring-red-300',
        'refuse'                => 'bg-red-100    text-red-600    ring-red-300',
        'expire'                => 'bg-orange-100 text-orange-700 ring-orange-300',
    ];

    $defaultLabels = [
        'brouillon'             => 'Brouillon',
        'en_attente_validation' => 'En attente',
        'envoye'                => 'Envoyé',
        'confirme'              => 'Confirmé',
        'valide'                => 'Validé',
        'validee'               => 'Validée',
        'emise'                 => 'Émise',
        'envoyee'               => 'Envoyée',
        'accepte'               => 'Accepté',
        'partiellement_payee'   => 'Part. payée',
        'payee'                 => 'Payée',
        'en_retard'             => 'En retard',
        'partiellement_livre'   => 'Part. livré',
        'livre'                 => 'Livré',
        'facture'               => 'Facturé',
        'applique'              => 'Appliqué',
        'converti'              => 'Converti',
        'annule'                => 'Annulé',
        'annulee'               => 'Annulée',
        'refuse'                => 'Refusé',
        'expire'                => 'Expiré',
    ];

    $colors     = $colorMap[$status]    ?? 'bg-gray-100 text-gray-600 ring-gray-200';
    $text       = $label                ?? $defaultLabels[$status] ?? ucfirst($status);
    $sizeClass  = $size === 'sm' ? 'px-2 py-0.5 text-[12px]' : 'px-2.5 py-1 text-[12px] font-medium';
@endphp

<span class="inline-flex items-center rounded-full ring-1 ring-inset {{ $colors }} {{ $sizeClass }} gap-1">
    @if($status === 'en_attente_validation')
        <span class="size-1.5 rounded-full bg-yellow-500 inline-block"></span>
    @elseif(in_array($status, ['payee', 'valide', 'validee', 'confirme', 'accepte', 'converti']))
        <span class="size-1.5 rounded-full bg-green-500 inline-block"></span>
    @elseif(in_array($status, ['annule', 'annulee', 'en_retard', 'refuse']))
        <span class="size-1.5 rounded-full bg-red-500 inline-block"></span>
    @endif
    {{ $text }}
</span>
