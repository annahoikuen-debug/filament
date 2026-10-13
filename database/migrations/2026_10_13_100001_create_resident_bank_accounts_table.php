<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 4)->comment('金融機関コード(4桁)');
            $table->string('branch_code', 3)->comment('支店コード(3桁)');
            $table->unsignedTinyInteger('account_type')->default(1)->comment('預金種目 1:普通 2:当座');
            $table->string('account_number', 7)->comment('口座番号(7桁)');
            $table->string('account_holder_kana', 30)->comment('口座名義人(半角カナ)');
            $table->string('delegate_code', 10)->nullable()->comment('委託者コード');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['resident_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_bank_accounts');
    }
};
