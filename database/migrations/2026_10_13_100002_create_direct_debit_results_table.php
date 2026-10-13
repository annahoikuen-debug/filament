<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_debit_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_year_month', 7)->comment('請求年月 Y-m');
            $table->unsignedBigInteger('amount')->comment('引落金額');
            $table->string('result_code', 1)->comment('結果コード 0:振替済 1-9:不能');
            $table->string('reason', 50)->nullable()->comment('不能理由');
            $table->boolean('is_success')->default(false);
            $table->date('processed_at')->comment('処理日');
            $table->timestamps();

            $table->index(['billing_year_month', 'result_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_debit_results');
    }
};
