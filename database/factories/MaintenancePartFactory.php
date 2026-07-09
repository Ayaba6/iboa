<?php
namespace Database\Factories;
use App\Models\Company;
use App\Models\Product;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\MaintenancePart;
use Illuminate\Database\Eloquent\Factories\Factory;
class MaintenancePartFactory extends Factory {
    protected $model = MaintenancePart::class;
    public function definition(): array {
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'machine_maintenance_id' => MachineMaintenance::query()->value('id') ?? MachineMaintenance::factory(),
            'product_id' => Product::query()->value('id') ?? Product::factory(),
            'quantity' => 1,
            'unit_cost' => 1000,
        ];
    }
}
