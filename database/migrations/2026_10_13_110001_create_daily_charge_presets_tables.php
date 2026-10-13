<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 9: 自費プリセットマスター（daily_charge_presets / daily_charge_preset_items）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_charge_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete()->comment('施設ID（NULL=共通）');
            $table->string('name')->comment('プリセット名');
            $table->string('description')->nullable()->comment('説明');
            $table->unsignedInteger('sort_order')->default(0)->comment('並び順');
            $table->boolean('is_active')->default(true)->comment('有効');
            $table->timestamps();

            $table->index(['facility_id', 'is_active', 'sort_order']);
        });

        Schema::create('daily_charge_preset_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_charge_preset_id')->constrained('daily_charge_presets')->cascadeOnDelete()->comment('プリセットID');
            $table->foreignId('charge_item_id')->constrained('charge_items')->restrictOnDelete()->comment('品目ID');
            $table->unsignedInteger('default_quantity')->default(1)->comment('既定数量');
            $table->unsignedInteger('sort_order')->default(0)->comment('並び順');
            $table->timestamps();

            $table->unique(['daily_charge_preset_id', 'charge_item_id'], 'preset_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_charge_preset_items');
        Schema::dropIfExists('daily_charge_presets');
    }
};
