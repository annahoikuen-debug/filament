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
        Schema::table('monthly_invoices', function (Blueprint $table) {
            // 税率内訳（標準税率・軽減税率・非課税の内訳をJSONで保存）
            // インボイス制度対応のため、税率ごとの課税額・税額を保持
            $table->json('tax_breakdown')->nullable()->comment('税率内訳（標準・軽減・非課税の課税額・税額・税率）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropColumn('tax_breakdown');
        });
    }
};
