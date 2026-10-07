<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('billing_year_month', 7)->comment('請求年月(YYYY-MM)');
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete()->comment('入居者ID');
            $table->unsignedInteger('rent_subtotal')->default(0)->comment('家賃小計');
            $table->unsignedInteger('management_fee_subtotal')->default(0)->comment('管理費小計');
            $table->unsignedInteger('service_subtotal')->default(0)->comment('自費サービス小計');
            $table->unsignedInteger('total_amount')->default(0)->comment('合計金額');
            $table->string('status')->default(InvoiceStatus::Unbilled->value)->comment('ステータス(未請求/請求済/入金済)');
            $table->timestamps();

            // 同じ入居者に対して同月の請求書が重複作成されるのを防止
            $table->unique(['resident_id', 'billing_year_month']);
            $table->index('billing_year_month');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_invoices');
    }
};
