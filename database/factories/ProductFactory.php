<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        static $counter = 1;

        // [D2] Un article vendable doit porter une stratégie d'approvisionnement.
        // La fabrique en produisait sans, ce qui n'est plus un article valide :
        // 44 tests le vendaient sans que rien ne dise s'il fallait chercher du
        // stock ou contrôler une couverture financière.
        //
        // « Acheté-revendu » est le défaut neutre — ni production sur commande,
        // ni fabrication pour stock. La stratégie est posée sur la COLONNE, pas
        // via une catégorie : une catégorie porte aussi `is_manufactured`, et
        // rattacher l'article à « marchandise » lui interdirait d'être fabriqué,
        // ce qui casse les tests de production. Les deux notions sont distinctes.
        //
        // Un test MTO ou MTS pose son mode explicitement et écrase ce défaut.
        return [
            'production_mode'  => 'achat_revente',
            'reference'        => 'PRD-' . str_pad($counter++, 5, '0', STR_PAD_LEFT),
            'name'             => $this->faker->words(3, true),
            'type'             => $this->faker->randomElement(['simple', 'compose', 'service']),
            'is_stockable'     => true,
            'is_purchasable'   => true,
            'is_sellable'      => true,
            'has_lot_number'   => false,
            'has_serial_number'=> false,
            'has_expiry_date'  => false,
            'purchase_price'   => $this->faker->numberBetween(1_000, 500_000),
            'sale_price'       => $this->faker->numberBetween(2_000, 700_000),
            'stock_min'        => 5,
            'stock_max'        => 500,
            'valuation_method' => 'cmp',
            'is_active'        => true,
        ];
    }
}
