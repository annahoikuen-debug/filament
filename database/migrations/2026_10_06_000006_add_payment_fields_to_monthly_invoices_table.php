<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->date('paid_at')->nullable()->after('status')->comment('入金確認日');
            $table->string('payment_method')->nullable()->after('paid_at')->comment('入金方法(銀行振込/口座振替/現金)');
            $table->string('receipt_number')->nullable()->after('payment_method')->comment('領収書番号');
            $table->timestamp('receipt_issued_at')->nullable()->after('receipt_number')->comment('領収書初回発行日時');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'payment_method', 'receipt_number', 'receipt_issued_at']);
        });
    }
};
