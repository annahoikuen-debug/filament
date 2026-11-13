<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->constrained('monthly_invoices')->cascadeOnDelete();
            $table->string('type')->comment('証憑種別: receipt=受領書, statement=明細書, transfer_log=振込明細');
            $table->string('file_path')->comment('保存パス');
            $table->string('original_name')->comment('元ファイル名');
            $table->foreignId('uploaded_by')->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['monthly_invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_evidences');
    }
};
