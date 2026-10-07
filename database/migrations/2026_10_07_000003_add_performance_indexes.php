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
        // monthly_invoicesテーブルの複合インデックス追加
        Schema::table('monthly_invoices', function (Blueprint $table) {
            // 複合インデックス: 入居者別・年月別・ステータス別検索を高速化
            $table->index(['resident_id', 'billing_year_month', 'status'], 'idx_resident_month_status');
            // ステータス別・年月別検索（未入金リストなど）を高速化
            $table->index(['status', 'billing_year_month'], 'idx_status_month');
            // 年月別検索をさらに高速化（既にあるけど明示）
            $table->index('billing_year_month', 'idx_billing_year_month');
        });

        // daily_chargesテーブルの複合インデックス追加
        Schema::table('daily_charges', function (Blueprint $table) {
            // 入居者別・品目別・日付別集計を高速化
            $table->index(['resident_id', 'charge_item_id', 'date'], 'idx_resident_item_date');
            // 品目別集計を高速化
            $table->index('charge_item_id', 'idx_charge_item_id');
            // 日付別集計を高速化（既にあるけど明示）
            $table->index('date', 'idx_date');
        });

        // residentsテーブルの状態別インデックス
        Schema::table('residents', function (Blueprint $table) {
            // 入居日・退去日の範囲検索を高速化
            $table->index(['move_in_date', 'move_out_date'], 'idx_move_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_resident_month_status');
            $table->dropIndex('idx_status_month');
            $table->dropIndex('idx_billing_year_month');
        });

        Schema::table('daily_charges', function (Blueprint $table) {
            $table->dropIndex('idx_resident_item_date');
            $table->dropIndex('idx_charge_item_id');
            $table->dropIndex('idx_date');
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex('idx_move_dates');
        });
    }
};
