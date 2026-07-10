<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code'      => 'FOUR-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'type'      => 'entreprise',
            'name'      => $this->faker->company(),
            'email'     => $this->faker->unique()->companyEmail(),
            'phone'     => $this->faker->numerify('+225 ## ## ## ## ##'),
            'is_active' => true,
        ];
    }
}
