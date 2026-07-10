<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_wastes', function (Blueprint $table) {
            $table->string('cause', 40)->nullable()->after('reason');
            $table->boolean('validated_by_chef')->default(false)->after('cause');
            $table->boolean('validated_by_quality')->default(false)->after('validated_by_chef');
            $table->text('corrective_action')->nullable()->after('validated_by_quality');
        });
    }

    public function down(): void
    {
        Schema::table('production_wastes', function (Blueprint $table) {
            $table->dropColumn(['cause','validated_by_chef','validated_by_quality','corrective_action']);
        });
    }
};
