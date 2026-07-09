<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MODIFY COLUMN is MySQL-only; SQLite (used in tests) ignores enum constraints.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clients MODIFY COLUMN type ENUM('particulier','entreprise','distributeur','minier') NOT NULL DEFAULT 'entreprise'");
        }

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'payment_mode')) {
                $table->enum('payment_mode', ['cash', 'credit'])->default('credit')->after('credit_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clients MODIFY COLUMN type ENUM('particulier','entreprise') NOT NULL DEFAULT 'entreprise'");
        }
    }
};
