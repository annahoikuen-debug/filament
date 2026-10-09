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
        Schema::table('pdf_template_settings', function (Blueprint $table) {
            $table->boolean('show_calculation_basis')->default(true)->after('show_daily_charges_detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_template_settings', function (Blueprint $table) {
            $table->dropColumn('show_calculation_basis');
        });
    }
};
