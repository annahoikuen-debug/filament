<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('品目名(おむつ、理美容、立替など)');
            $table->unsignedInteger('default_price')->default(0)->comment('デフォルト単価');
            $table->boolean('is_active')->default(true)->comment('利用可能フラグ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_items');
    }
};
