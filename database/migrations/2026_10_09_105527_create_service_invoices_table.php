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
        Schema::create('service_invoices', function (Blueprint $table) {
            $table->id();
            
            // 施設・入居者・請求月
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete()->comment('施設ID');
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete()->comment('入居者ID');
            $table->string('billing_year_month', 7)->comment('請求年月 (YYYY-MM)');
            
            // サービス種別
            $table->string('service_type')->comment('サービス種別: visiting_care, day_care, care_planning, home_nursing, short_stay, welfare_equipment, home_modification, other');
            $table->string('service_type_label')->comment('表示用ラベル');
            
            // 外部システム情報
            $table->string('external_system_name')->nullable()->comment('外部システム名 (例: ケアプランデータ連携システム, 請求ソフト名)');
            $table->string('external_invoice_number')->nullable()->comment('外部システム請求番号');
            
            // 金額情報
            $table->integer('amount')->default(0)->comment('請求金額 (税抜)');
            $table->integer('tax_amount')->default(0)->comment('消費税額');
            $table->decimal('tax_rate', 5, 2)->default(10.00)->comment('税率');
            
            // PDFファイル
            $table->string('pdf_path')->nullable()->comment('PDFファイルパス (storage/app/service-invoices/...)');
            $table->string('pdf_original_name')->nullable()->comment('アップロード時の元ファイル名');
            
            // ステータス・送信管理
            $table->string('status')->default('draft')->comment('ステータス: draft, confirmed, sent');
            $table->timestamp('sent_at')->nullable()->comment('送信日時');
            $table->string('sent_via')->nullable()->comment('送信経路: email, zip, portal, manual');
            
            // メモ
            $table->text('notes')->nullable()->comment('備考');
            
            $table->timestamps();
            
            // インデックス
            $table->index(['facility_id', 'billing_year_month']);
            $table->index(['resident_id', 'billing_year_month']);
            $table->index(['service_type', 'billing_year_month']);
            $table->unique(['resident_id', 'billing_year_month', 'service_type', 'external_invoice_number'], 'unique_service_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_invoices');
    }
};