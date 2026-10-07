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
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            
            // 基本情報
            $table->string('name')->comment('施設名');
            $table->string('operator')->comment('運営事業者名');
            $table->string('postal_code', 7)->comment('郵便番号');
            $table->string('address')->comment('住所');
            $table->string('phone')->nullable()->comment('電話番号');
            $table->string('fax')->nullable()->comment('FAX番号');
            $table->string('email')->nullable()->comment('メールアドレス');
            
            // インボイス制度
            $table->string('invoice_registration_number', 14)
                ->unique()
                ->comment('適格請求書発行事業者登録番号（T+13桁）');
            
            // 銀行口座情報
            $table->json('bank')->nullable()->comment('銀行口座情報（name, branch_name, account_type, account_number, account_holder）');
            
            // 請求サイクル設定
            $table->json('billing')->nullable()->comment('請求サイクル設定（direct_debit_day, bank_transfer_due_days）');
            
            // メタ情報
            $table->boolean('is_active')->default(true)->comment('有効フラグ（単一レコード運用時の切替用）');
            $table->text('notes')->nullable()->comment('備考');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};