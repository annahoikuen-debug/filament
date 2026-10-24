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
        Schema::table('charge_items', function (Blueprint $table) {
            $table->string('tax_legal_basis')->nullable()->after('tax_type')
                ->comment('税区分の法律的根拠（例：国税庁告示該当条文）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charge_items', function (Blueprint $table) {
            $table->dropColumn('tax_legal_basis');
        });
    }
};
