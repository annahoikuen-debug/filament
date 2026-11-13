<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->nullable()->constrained('monthly_invoices')->nullOnDelete();
            $table->string('type')->comment('帳票種別: invoice=請求書, receipt=領収書');
            $table->string('file_path')->comment('保存パス');
            $table->string('file_hash', 64)->comment('SHA-256ハッシュ（改ざん検知用）');
            $table->string('issued_to')->comment('宛先（入居者名等）');
            $table->date('issued_at')->comment('発行日');
            $table->integer('total_amount')->nullable()->comment('合計金額（電帳法検索要件）');
            $table->string('sent_to')->nullable()->comment('メール送信先（電子取引の場合）');
            $table->timestamps();

            $table->index(['issued_at', 'issued_to']);
            $table->index(['total_amount']);
            $table->index('file_hash');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_documents');
    }
};
