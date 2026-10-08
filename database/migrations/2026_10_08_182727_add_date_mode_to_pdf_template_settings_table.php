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
            $table->string('date_mode')->default('auto')->comment('日付モード: auto(請求書主導), manual(任意選択)')->after('show_page_numbers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_template_settings', function (Blueprint $table) {
            $table->dropColumn('date_mode');
        });
    }
};