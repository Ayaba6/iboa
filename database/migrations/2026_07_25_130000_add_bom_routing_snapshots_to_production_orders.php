<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->json('bom_snapshot')->nullable()->after('routing_version');
            $table->char('bom_snapshot_sha256', 64)->nullable()->after('bom_snapshot');
            $table->json('routing_snapshot')->nullable()->after('bom_snapshot_sha256');
            $table->char('routing_snapshot_sha256', 64)->nullable()->after('routing_snapshot');
            $table->timestamp('snapshotted_at')->nullable()->after('routing_snapshot_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn([
                'bom_snapshot', 'bom_snapshot_sha256', 'routing_snapshot',
                'routing_snapshot_sha256', 'snapshotted_at',
            ]);
        });
    }
};
