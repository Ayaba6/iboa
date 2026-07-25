<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE employees MODIFY status ENUM('actif','suspendu','licencie','demissionne','sorti') NOT NULL DEFAULT 'actif'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE employees SET status = 'demissionne' WHERE status = 'sorti'");
        DB::statement("ALTER TABLE employees MODIFY status ENUM('actif','suspendu','licencie','demissionne') NOT NULL DEFAULT 'actif'");
    }
};