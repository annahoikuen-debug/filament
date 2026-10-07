<?php

use App\Enums\ResidentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 10)->comment('部屋番号');
            $table->string('name')->comment('氏名');
            $table->string('name_kana')->nullable()->comment('フリガナ');
            $table->unsignedInteger('base_rent')->default(0)->comment('基本家賃(月額)');
            $table->unsignedInteger('base_management_fee')->default(0)->comment('基本管理費(月額)');
            $table->string('status')->default(ResidentStatus::Active->value)->comment('ステータス');
            $table->timestamps();

            // 検索・並び替え用インデックス
            $table->index('room_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
