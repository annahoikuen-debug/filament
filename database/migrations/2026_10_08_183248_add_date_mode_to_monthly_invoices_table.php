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
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->string('invoice_date_mode')->default('auto')->comment('請求書日付モード: auto(請求年月), manual(任意)')->after('billing_year_month');
            $table->date('custom_invoice_date')->nullable()->comment('請求書任意日付')->after('invoice_date_mode');
            $table->string('receipt_date_mode')->default('auto')->comment('領収書日付モード: auto(入金日), manual(任意)')->after('receipt_number');
            $table->date('custom_receipt_date')->nullable()->comment('領収書任意日付')->after('receipt_date_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_date_mode',
                'custom_invoice_date',
                'receipt_date_mode',
                'custom_receipt_date',
            ]);
        });
    }
};
