<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Production\Models\MaintenancePlan;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Database\Eloquent\Factories\Factory;
class MaintenancePlanFactory extends Factory {
    protected $model = MaintenancePlan::class;
    public function definition(): array {
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'machine_id' => ProductionMachine::query()->value('id') ?? ProductionMachine::factory(),
            'name' => 'Graissage ' . $this->faker->word(),
            'frequency_days' => $this->faker->randomElement([7, 30, 90]),
            'next_due_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}
