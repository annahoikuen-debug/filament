<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_charges', function (Blueprint $table) {
            $table->id();
            $table->date('date')->comment('利用日付');
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete()->comment('入居者ID');
            $table->foreignId('charge_item_id')->constrained('charge_items')->restrictOnDelete()->comment('品目ID');
            $table->unsignedInteger('unit_price')->comment('発生時単価(スナップショット)');
            $table->unsignedSmallInteger('quantity')->default(1)->comment('数量');
            $table->text('note')->nullable()->comment('備考(フリーテキスト)');
            $table->timestamps();

            // 月次請求集計時によく検索される複合インデックス
            $table->index(['resident_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_charges');
    }
};
