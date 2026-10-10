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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete()->comment('施設ID');
            $table->string('item_type')->comment('品目タイプ: rent, management_fee, service, advance_payment');
            $table->string('account_side')->comment('借方/貸方: debit, credit');
            $table->string('account_code')->comment('勘定科目コード');
            $table->string('account_name')->comment('勘定科目名');
            $table->string('sub_account_code')->nullable()->comment('補助科目コード');
            $table->string('sub_account_name')->nullable()->comment('補助科目名');
            $table->string('tax_code')->nullable()->comment('税区分コード (freee: tax_code, MF: tax_category, 弥生: tax_classification)');
            $table->string('department_code')->nullable()->comment('部門コード');
            $table->string('department_name')->nullable()->comment('部門名');
            $table->string('tag_codes')->nullable()->comment('タグコード (JSON配列)');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->integer('sort_order')->default(0)->comment('表示順');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['facility_id', 'item_type', 'account_side'], 'unique_facility_item_side');
            $table->index(['facility_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
