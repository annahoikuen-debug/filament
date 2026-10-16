<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            // 通称・旧姓・別表記（チャットボット検索対象）
            $table->string('nickname', 100)->nullable()->after('name_kana');
            $table->string('normalized_nickname', 191)->nullable()->after('normalized_kana');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['nickname', 'normalized_nickname']);
        });
    }
};
