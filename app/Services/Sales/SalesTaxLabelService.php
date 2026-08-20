<?php

namespace App\Services\Sales;

use App\Models\Client;

/**
 * [UI — doublon retiré] Libellé de taxation DÉRIVÉ de l'état réel du document.
 *
 * `default_tax_label` était saisi dans un champ « Taxes » présent sur les
 * formulaires devis, commande, facture et facture fournisseur. Ce libellé était
 * stocké, validé, `fillable` — et JAMAIS lu par aucune logique métier, ni affiché
 * sur aucune fiche ni aucun PDF. Il doublonnait « TVA par défaut », seul champ
 * pilotant réellement la TVA des lignes, et pouvait le CONTREDIRE : « Taxes =
 * Exonéré » avec des lignes à 18 % produisait un document étiqueté exonéré et
 * taxé.
 *
 * Trois sources d'exonération coexistaient à l'écran : l'exonération du client,
 * le mode de prix « exonéré », et ce libellé — le seul à pouvoir mentir,
 * précisément parce qu'il n'était pas lu.
 *
 * Ce service est l'implémentation UNIQUE de la règle. QuoteService, OrderService
 * et InvoiceService y délèguent : recopiée dans chacun, elle finirait par
 * diverger.
 */
class SalesTaxLabelService
{
    /**
     * @param  array<string,mixed>  $data   En-tête du document (price_mode, client_id)
     * @param  array<int,array<string,mixed>>  $items  Lignes, pour leur taux réel
     */
    public function derive(array $data, array $items): string
    {
        if (($data['price_mode'] ?? null) === 'exonere') {
            return 'Exonéré';
        }

        $client = ! empty($data['client_id']) ? Client::find($data['client_id']) : null;
        if ($client?->isTaxExempt()) {
            return 'Exonéré';
        }

        // Taux le PLUS ÉLEVÉ réellement appliqué : c'est celui que le client lit sur
        // le document. Aucune ligne taxée ⇒ exonéré, sans déclaration parallèle.
        $rates = array_map(fn ($item) => (float) ($item['tax_rate_value'] ?? 0), $items);
        $max   = $rates === [] ? 0.0 : max($rates);

        if ($max <= 0) {
            return 'Exonéré';
        }

        // « TVA 18% » et non « TVA 18,00% » : on retire les décimales inutiles sans
        // perdre celles qui portent une information (« TVA 7,5% »).
        return 'TVA '.rtrim(rtrim(number_format($max, 2, ',', ''), '0'), ',').'%';
    }
}
