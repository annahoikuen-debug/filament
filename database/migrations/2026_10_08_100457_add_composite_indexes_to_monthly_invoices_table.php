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
            // facility_id + billing_year_month + status での絞り込みクエリを高速化
            $table->index(['facility_id', 'billing_year_month', 'status'], 'mi_facility_month_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropIndex('mi_facility_month_status_idx');
        });
    }
};