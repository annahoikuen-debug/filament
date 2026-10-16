<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 電子帳簿保存法（電帳法）スキャナ保存・電子取引データ保存要件:
     * - 取引年月日（transaction_date）
     * - 取引金額（amount）
     * - 取引先名称（partner_name: 入居者名・請求先）
     * - 改ざん防止ハッシュ（sha256_hash）
     * - 範囲検索のための複合インデックス
     */
    public function up(): void
    {
        Schema::create('electronic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('recordable'); // MonthlyInvoice または ServiceInvoice
            $table->string('document_type', 30)->comment('invoice, receipt, service_invoice');
            $table->date('transaction_date')->comment('取引年月日');
            $table->unsignedBigInteger('amount')->comment('取引金額（税込）');
            $table->string('partner_name', 100)->comment('取引先・入居者氏名');
            $table->string('file_path')->comment('保存先ファイルパス');
            $table->string('file_name')->comment('オリジナルファイル名');
            $table->string('sha256_hash', 64)->comment('改ざん検知用SHA-256ハッシュ');
            $table->unsignedInteger('file_size')->comment('バイト数');
            $table->text('correction_reason')->nullable()->comment('訂正・削除理由');
            $table->unsignedInteger('version')->default(1)->comment('版数');
            $table->timestamps();

            // 電帳法の3大検索要件インデックス
            $table->index(['facility_id', 'transaction_date']);
            $table->index(['facility_id', 'amount']);
            $table->index(['facility_id', 'partner_name']);
            $table->index(['facility_id', 'transaction_date', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('electronic_records');
    }
};
