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
        Schema::table('residents', function (Blueprint $table) {
            // facility_id + status + move_in_date + move_out_date での絞り込みクエリを高速化
            // InvoiceCalculationService::generateForMonth() のメインクエリで使用
            $table->index(['facility_id', 'status', 'move_in_date', 'move_out_date'], 'res_facility_status_dates_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex('res_facility_status_dates_idx');
        });
    }
};