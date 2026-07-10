<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC §13.8/§14] Maintenance — plans préventifs + pièces de rechange consommées.
 *
 * maintenance_plans   : périodicité par machine, génère automatiquement des
 *                       MachineMaintenance (type=preventive) à échéance.
 * maintenance_parts   : pièces sorties de stock pour une intervention — lien
 *                       réel vers product_stocks/stock_movements (traçabilité
 *                       et coût réel, au lieu du champ `cost` saisi à la main).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('production_machines')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('frequency_days'); // intervalle entre interventions
            $table->text('instructions')->nullable();
            $table->date('last_generated_at')->nullable();
            $table->date('next_due_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'next_due_at']);
        });

        Schema::table('machine_maintenances', function (Blueprint $table) {
            $table->foreignId('maintenance_plan_id')->nullable()->after('machine_id')
                ->constrained('maintenance_plans')->nullOnDelete();
        });

        Schema::create('maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_maintenance_id')->constrained('machine_maintenances')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->integer('unit_cost')->default(0); // FCFA, snapshot au moment de la sortie
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_parts');
        Schema::table('machine_maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_plan_id');
        });
        Schema::dropIfExists('maintenance_plans');
    }
};
