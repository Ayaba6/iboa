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

        DB::statement("ALTER TABLE clients MODIFY payment_mode VARCHAR(20) NOT NULL DEFAULT 'credit'");
        DB::statement("ALTER TABLE invoices MODIFY type VARCHAR(20) NOT NULL DEFAULT 'standard'");
        DB::statement("ALTER TABLE employees MODIFY category VARCHAR(20) NOT NULL DEFAULT 'employe'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE clients MODIFY payment_mode ENUM('cash','credit') NOT NULL DEFAULT 'credit'");
        DB::statement("ALTER TABLE invoices MODIFY type ENUM('standard','acompte','partielle','recurrente','proforma') NOT NULL DEFAULT 'standard'");
        DB::statement("ALTER TABLE employees MODIFY category ENUM('cadre','agent_maitrise','employe','ouvrier') NOT NULL DEFAULT 'employe'");
    }
};
