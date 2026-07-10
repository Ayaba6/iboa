<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC §13.10] Modification OF exceptionnelle — workflow à 4 étapes séquentielles
 * (Chef Production → Commercial → Finance → DG), remplace l'ancien circuit à
 * 2 étapes génériques (request/approve) qui ne traçait pas les acteurs distincts
 * et ne débloquait jamais réellement l'édition d'un OF en_cours/termine_partiellement.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->string('modification_status', 20)->default('aucune')->after('payment_rate'); // aucune/en_attente/approuvee/refusee
            $table->string('modification_reason', 500)->nullable()->after('modification_status');

            $table->timestamp('modification_requested_at')->nullable()->after('modification_reason');
            $table->foreignId('modification_requested_by')->nullable()->after('modification_requested_at')->constrained('users')->nullOnDelete();

            $table->timestamp('modification_chef_avis_at')->nullable()->after('modification_requested_by');
            $table->foreignId('modification_chef_avis_by')->nullable()->after('modification_chef_avis_at')->constrained('users')->nullOnDelete();
            $table->string('modification_chef_comment', 500)->nullable()->after('modification_chef_avis_by');

            $table->timestamp('modification_commercial_avis_at')->nullable()->after('modification_chef_comment');
            $table->foreignId('modification_commercial_avis_by')->nullable()->after('modification_commercial_avis_at')->constrained('users')->nullOnDelete();
            $table->string('modification_commercial_comment', 500)->nullable()->after('modification_commercial_avis_by');

            $table->timestamp('modification_finance_avis_at')->nullable()->after('modification_commercial_comment');
            $table->foreignId('modification_finance_avis_by')->nullable()->after('modification_finance_avis_at')->constrained('users')->nullOnDelete();
            $table->string('modification_finance_comment', 500)->nullable()->after('modification_finance_avis_by');

            $table->timestamp('modification_dg_approved_at')->nullable()->after('modification_finance_comment');
            $table->foreignId('modification_dg_approved_by')->nullable()->after('modification_dg_approved_at')->constrained('users')->nullOnDelete();
            $table->string('modification_dg_comment', 500)->nullable()->after('modification_dg_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropForeign(['modification_requested_by']);
            $table->dropForeign(['modification_chef_avis_by']);
            $table->dropForeign(['modification_commercial_avis_by']);
            $table->dropForeign(['modification_finance_avis_by']);
            $table->dropForeign(['modification_dg_approved_by']);
            $table->dropColumn([
                'modification_status', 'modification_reason',
                'modification_requested_at', 'modification_requested_by',
                'modification_chef_avis_at', 'modification_chef_avis_by', 'modification_chef_comment',
                'modification_commercial_avis_at', 'modification_commercial_avis_by', 'modification_commercial_comment',
                'modification_finance_avis_at', 'modification_finance_avis_by', 'modification_finance_comment',
                'modification_dg_approved_at', 'modification_dg_approved_by', 'modification_dg_comment',
            ]);
        });
    }
};
