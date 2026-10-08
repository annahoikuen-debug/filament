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
        Schema::table('daily_charges', function (Blueprint $table) {
            // facility_id + resident_id + date での絞り込みクエリを高速化（施設スコープの日々課金検索）
            $table->index(['facility_id', 'resident_id', 'date'], 'dc_facility_resident_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_charges', function (Blueprint $table) {
            $table->dropIndex('dc_facility_resident_date_idx');
        });
    }
};