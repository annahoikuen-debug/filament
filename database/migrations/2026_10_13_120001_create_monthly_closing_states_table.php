<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 19: 月次締めステートマシン & ロック機構
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_closing_states', function (Blueprint $table) {
            $table->id();
            $table->string('billing_year_month', 7)->comment('請求年月 YYYY-MM');
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete()->comment('施設ID');
            // unclosed / generated / approved / sent / closed
            $table->string('status', 20)->default('unclosed')->comment('締め状態');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete()->comment('締め実行者');
            $table->timestamp('closed_at')->nullable()->comment('締め日時');
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete()->comment('差戻し実行者');
            $table->timestamp('reopened_at')->nullable()->comment('差戻し日時');
            $table->text('reopen_reason')->nullable()->comment('差戻し理由');
            $table->json('anomaly_report')->nullable()->comment('異常検知スナップショット');
            $table->timestamps();

            $table->unique(['billing_year_month', 'facility_id'], 'closing_year_month_facility_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_closing_states');
    }
};
