<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 同一入居者×同一品目×同一日の二重登録を防ぐユニーク制約を追加する。
     * 既存の重複行は dedup コマンドで事前に整合を取ることを推奨する。
     */
    public function up(): void
    {
        Schema::table('daily_charges', function (Blueprint $table) {
            $table->unique(
                ['resident_id', 'charge_item_id', 'date'],
                'daily_charges_resident_item_date_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_charges', function (Blueprint $table) {
            $table->dropUnique('daily_charges_resident_item_date_unique');
        });
    }
};
