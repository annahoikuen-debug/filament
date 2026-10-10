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
        Schema::create('charge_item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_item_id')->constrained()->cascadeOnDelete()->comment('品目ID');
            $table->unsignedInteger('price')->comment('単価');
            $table->date('effective_from')->comment('適用開始日');
            $table->date('effective_until')->nullable()->comment('適用終了日');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();

            $table->index(['charge_item_id', 'effective_from', 'effective_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_item_prices');
    }
};
