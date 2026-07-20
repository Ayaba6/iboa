<?php

namespace App\Services\Sales;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\SalesDiscount;
use App\Models\SalesSetting;
use Carbon\Carbon;

/**
 * [Paramétrage Vente X3] Résolution centralisée du prix de vente.
 *
 * Ordre de résolution (le plus spécifique gagne) :
 *   1. Tarif client + article + unité
 *   2. Tarif client + article
 *   3. Tarif famille tarifaire du client + article
 *   4. Tarif catégorie client + article
 *   5. Prix de base article (sale_price)
 *
 * Puis application des remises paramétrées (sales_discounts, la plus favorable),
 * plafonnée au prix plancher (min_sale_price) si enforce_price_floor.
 *
 * Adapté tôle bac / fer à béton : un même article peut avoir un tarif au ML,
 * au m², à la pièce, au kg, à la tonne ou à la barre (unit_id sur le tier).
 */
class SalesPricingService
{
    /**
     * @return array{price: float, base_price: float, source: string, discount_percent: float,
     *               discount_name: ?string, floor: float, below_floor: bool,
     *               requires_validation: bool, unit_id: ?int}
     */
    public function resolve(?Client $client, Product $product, ?int $unitId = null, float $quantity = 1, ?Carbon $date = null): array
    {
        $date = $date ?? now();
        $settings = SalesSetting::current();

        [$basePrice, $source, $tierUnit] = $this->resolveBasePrice($client, $product, $unitId, $quantity, $date);

        [$discountPercent, $discountName, $discountValidation] =
            $this->resolveDiscount($client, $product, $quantity, $date);

        $price = round($basePrice * (1 - $discountPercent / 100), 2);
        $floor = (float) ($product->min_sale_price ?? 0);
        $belowFloor = $floor > 0 && $price < $floor;

        // [CDC Tarifaire] Prix plafond indicatif : dépassement signalé (alerte), non bloquant.
        $ceiling = (float) ($product->max_sale_price ?? 0);
        $aboveCeiling = $ceiling > 0 && $price > $ceiling;

        // Prix plancher strict : la remise est écrêtée au plancher, la vente
        // sous plancher exige une validation DG/DAF (flag remonté au caller).
        $requiresValidation = $discountValidation
            || ($belowFloor && $settings->enforce_price_floor)
            || $discountPercent > (float) $settings->discount_validation_threshold;

        return [
            'price'               => $price,
            'base_price'          => $basePrice,
            'source'              => $source,
            'discount_percent'    => $discountPercent,
            'discount_name'       => $discountName,
            'floor'               => $floor,
            'below_floor'         => $belowFloor,
            'ceiling'             => $ceiling,
            'above_ceiling'       => $aboveCeiling,
            'requires_validation' => $requiresValidation,
            'unit_id'             => $tierUnit ?? $unitId,
        ];
    }

    /** @return array{0: float, 1: string, 2: ?int} [prix, source, unit_id du tier] */
    private function resolveBasePrice(?Client $client, Product $product, ?int $unitId, float $quantity, Carbon $date): array
    {
        $tiers = ProductPriceTier::where('product_id', $product->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $date))
            ->where(fn ($q) => $q->whereNull('min_quantity')->orWhere('min_quantity', '<=', $quantity))
            ->get()
            // [CDC Tarifaire] Zone = agence : ne garder que les paliers du site du client ou « tous sites » (site_id NULL).
            ->filter(fn ($t) => ! $t->site_id || ($client && (int) $t->site_id === (int) $client->site_id))
            ->values();

        $candidates = [
            // [filtre, source]
            [fn ($t) => $client && $t->client_id === $client->id && $unitId && $t->unit_id === $unitId, 'tarif client + unité'],
            [fn ($t) => $client && $t->client_id === $client->id && !$t->unit_id,                        'tarif client'],
            [fn ($t) => $client && $client->famille_tarifaire && $t->famille_tarifaire === $client->famille_tarifaire
                        && (!$unitId || !$t->unit_id || $t->unit_id === $unitId),                        'famille tarifaire'],
            [fn ($t) => $client && $client->category && $t->client_category === $client->category
                        && (!$unitId || !$t->unit_id || $t->unit_id === $unitId),                        'catégorie client'],
            [fn ($t) => !$t->client_id && !$t->client_category && !$t->famille_tarifaire
                        && $unitId && $t->unit_id === $unitId,                                           'tarif unité'],
        ];

        foreach ($candidates as [$filter, $source]) {
            // Palier spécifique à l'agence prioritaire, puis qté min la plus élevée satisfaite
            // (tarif volume le plus avantageux).
            $tier = $tiers->filter($filter)
                ->sortByDesc(fn ($t) => [$t->site_id ? 1 : 0, (float) ($t->min_quantity ?? 0)])
                ->first();
            if ($tier) {
                return [(float) $tier->price, $source, $tier->unit_id];
            }
        }

        return [(float) ($product->sale_price ?? 0), 'prix de base', null];
    }

    /** @return array{0: float, 1: ?string, 2: bool} [taux, nom, validation requise] */
    private function resolveDiscount(?Client $client, Product $product, float $quantity, Carbon $date): array
    {
        $discounts = SalesDiscount::validOn($date)
            ->where(fn ($q) => $q->whereNull('min_quantity')->orWhere('min_quantity', '<=', $quantity))
            ->get()
            ->filter(function ($d) use ($client, $product) {
                return match ($d->discount_type) {
                    'client'            => $client && $d->client_id === $client->id,
                    'groupe_client'     => $client && $client->groupe_client && $d->client_group === $client->groupe_client,
                    'categorie_client'  => $client && $client->category && $d->client_category === $client->category,
                    'article'           => $d->product_id === $product->id,
                    'famille_article'   => $d->product_family_id && $d->product_family_id === $product->product_family_id,
                    'volume'            => $d->product_id === $product->id || !$d->product_id,
                    'promotionnelle', 'exceptionnelle' =>
                        (!$d->product_id || $d->product_id === $product->id)
                        && (!$d->client_id || ($client && $d->client_id === $client->id)),
                    default => false,
                };
            });

        // Remise client par défaut (fiche client) en concurrence avec les remises paramétrées
        $best = $discounts->sortByDesc('rate_percent')->first();
        $clientDefault = (float) ($client->default_discount ?? 0);

        if ($best && (float) $best->rate_percent >= $clientDefault) {
            return [(float) $best->rate_percent, $best->name, (bool) $best->requires_validation];
        }

        return [$clientDefault, $clientDefault > 0 ? 'Remise client par défaut' : null, false];
    }
}
