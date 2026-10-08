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
        Schema::create('recurring_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete()->comment('入居者ID');
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete()->comment('施設ID');
            $table->foreignId('charge_item_id')->constrained()->cascadeOnDelete()->comment('品目ID');
            $table->unsignedInteger('quantity')->default(1)->comment('数量');
            $table->date('start_date')->comment('開始日');
            $table->date('end_date')->nullable()->comment('終了日（nullの場合は無期限）');
            $table->enum('frequency', ['daily', 'monthly'])->default('monthly')->comment('頻度');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();

            $table->index(['resident_id', 'is_active', 'start_date', 'end_date']);
            $table->index(['facility_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_charges');
    }
};