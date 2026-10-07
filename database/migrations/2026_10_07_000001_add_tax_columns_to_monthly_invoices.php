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
            $table->unsignedInteger('taxable_amount')->nullable()->after('service_subtotal')
                ->comment('消費税課税対象額（管理費＋自費）');
            $table->unsignedInteger('tax_amount')->nullable()->after('taxable_amount')
                ->comment('消費税額');
            $table->unsignedInteger('tax_rate')->nullable()->after('tax_amount')->default(10)
                ->comment('消費税率（%）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropColumn(['taxable_amount', 'tax_amount', 'tax_rate']);
        });
    }
};
