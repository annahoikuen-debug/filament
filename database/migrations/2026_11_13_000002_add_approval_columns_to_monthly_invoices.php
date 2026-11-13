<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // monthly_invoices に承認ワークフロー用カラムを追加
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->after('status')->comment('承認申請者ID');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by')->comment('承認申請日時');
            $table->unsignedBigInteger('approved_by')->nullable()->after('submitted_at')->comment('承認者ID');
            $table->timestamp('approved_at')->nullable()->after('approved_by')->comment('承認日時');
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            // 既存インデックスがあれば活用しつつ、承認待ち検索用インデックスを追加
            $table->index(['status', 'facility_id', 'billing_year_month'], 'monthly_invoices_status_facility_ym_idx');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['submitted_by', 'submitted_at', 'approved_by', 'approved_at']);
            $table->dropIndex('monthly_invoices_status_facility_ym_idx');
        });
    }
};
