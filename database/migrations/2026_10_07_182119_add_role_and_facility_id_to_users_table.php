<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['corporate_admin', 'facility_admin'])->default('facility_admin')->after('is_admin')->comment('役割: corporate_admin=法人管理者, facility_admin=施設管理者');
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete()->after('role')->comment('所属施設ID（施設管理者の場合）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['facility_id']);
            $table->dropColumn(['role', 'facility_id']);
        });
    }
};
