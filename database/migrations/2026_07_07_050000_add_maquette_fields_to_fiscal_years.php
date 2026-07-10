<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette Exercices fiscaux] code, périodicité, type, responsable, régime, devise,
// références précédent/suivant, clôture effective, paramètres de gestion, commentaires.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');                    // EX-2026
            $table->string('periodicity', 20)->default('mensuelle')->after('status'); // mensuelle|trimestrielle|annuelle
            $table->string('exercise_type', 20)->default('normal')->after('periodicity'); // normal|premier|cloture
            $table->foreignId('responsible_id')->nullable()->after('exercise_type')
                  ->constrained('users')->nullOnDelete();
            $table->string('fiscal_regime', 50)->nullable()->after('responsible_id');
            $table->string('base_currency', 10)->default('XOF')->after('fiscal_regime');
            $table->string('previous_reference', 30)->nullable()->after('base_currency');
            $table->string('next_reference', 30)->nullable()->after('previous_reference');
            $table->date('actual_close_date')->nullable()->after('ends_at');
            $table->text('comment')->nullable()->after('next_reference');
            $table->text('internal_notes')->nullable()->after('comment');
            // Paramètres de gestion [Maquette]
            $table->boolean('allow_entries_after_provisional_close')->default(false)->after('internal_notes');
            $table->boolean('monthly_close_required')->default(true)->after('allow_entries_after_provisional_close');
            $table->boolean('auto_centralization')->default(true)->after('monthly_close_required');
            $table->boolean('analytics_active')->default(true)->after('auto_centralization');
            $table->boolean('vat_lock_after_validation')->default(true)->after('analytics_active');
            $table->unsignedTinyInteger('tolerated_days')->default(5)->after('vat_lock_after_validation');
            $table->date('last_monthly_close')->nullable()->after('tolerated_days');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_id');
            $table->dropColumn([
                'code', 'periodicity', 'exercise_type', 'fiscal_regime', 'base_currency',
                'previous_reference', 'next_reference', 'actual_close_date', 'comment', 'internal_notes',
                'allow_entries_after_provisional_close', 'monthly_close_required', 'auto_centralization',
                'analytics_active', 'vat_lock_after_validation', 'tolerated_days', 'last_monthly_close',
            ]);
        });
    }
};
