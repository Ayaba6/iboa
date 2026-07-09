<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette X3 Transfert inter-dépôts] entête enrichie (type, priorité, référence,
// transport, contrôles/validations) + lignes (qté demandée, poids, volume).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->time('transfer_time')->nullable()->after('transfer_date');
            $table->string('type', 20)->default('standard')->after('transfer_time');       // standard|urgent|retour|regularisation
            $table->string('priority', 20)->default('normale')->after('type');
            $table->string('currency_code', 10)->default('XOF')->after('priority');
            $table->string('reference', 80)->nullable()->after('currency_code');
            $table->date('source_document_date')->nullable()->after('reference');
            $table->foreignId('responsible_id')->nullable()->after('source_document_date')->constrained('users')->nullOnDelete();
            // Transport
            $table->string('carrier', 60)->nullable();
            $table->string('transport_mode', 20)->nullable();                               // interne|externe|messagerie
            $table->string('vehicle', 40)->nullable();
            $table->string('driver', 60)->nullable();
            $table->date('planned_date')->nullable();
            $table->time('planned_time')->nullable();
            $table->decimal('transport_cost', 14, 2)->default(0);
            $table->boolean('grouping')->default(false);
            $table->unsignedInteger('packages_count')->nullable();
            $table->decimal('total_weight', 14, 3)->nullable();
            $table->decimal('total_volume', 14, 3)->nullable();
            // Contrôles & validations
            $table->foreignId('controlled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('controlled_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->string('validation_status', 20)->default('en_attente');                 // en_attente|valide|rejete
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->decimal('requested_quantity', 14, 3)->nullable()->after('quantity');    // qté demandée (quantity = à transférer)
            $table->decimal('weight', 14, 3)->nullable()->after('requested_quantity');
            $table->decimal('volume', 14, 3)->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['requested_quantity', 'weight', 'volume']);
        });
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_id');
            $table->dropConstrainedForeignId('controlled_by');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn([
                'transfer_time', 'type', 'priority', 'currency_code', 'reference',
                'source_document_date', 'carrier', 'transport_mode', 'vehicle', 'driver',
                'planned_date', 'planned_time', 'transport_cost', 'grouping',
                'packages_count', 'total_weight', 'total_volume', 'controlled_at',
                'validated_at', 'validation_status',
            ]);
        });
    }
};
