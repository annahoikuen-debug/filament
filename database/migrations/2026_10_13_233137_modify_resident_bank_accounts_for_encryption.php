<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 暗号化文字列（base64エンコードされたペイロード）を収容するため、
     * 固定長VARCHARからTEXT型へ拡張する。
     */
    public function up(): void
    {
        Schema::table('resident_bank_accounts', function (Blueprint $table) {
            $table->text('bank_code')->change();
            $table->text('branch_code')->change();
            $table->text('account_number')->change();
            $table->text('account_holder_kana')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resident_bank_accounts', function (Blueprint $table) {
            $table->string('bank_code', 4)->change();
            $table->string('branch_code', 3)->change();
            $table->string('account_number', 7)->change();
            $table->string('account_holder_kana', 30)->change();
        });
    }
};
