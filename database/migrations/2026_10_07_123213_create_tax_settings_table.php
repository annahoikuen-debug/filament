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
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            
            // 基本税率（標準税率）
            $table->unsignedInteger('standard_rate')->default(10)->comment('標準税率（%）');
            
            // 軽減税率（将来の拡張用）
            $table->unsignedInteger('reduced_rate')->default(8)->comment('軽減税率（%）');
            
            // 税率適用開始日
            $table->date('effective_from')->comment('適用開始日');
            
            // 税率適用終了日（nullの場合は無期限）
            $table->date('effective_until')->nullable()->comment('適用終了日');
            
            // 適用対象（将来の拡張用：全品目/特定品目のみ等）
            $table->string('scope')->default('all')->comment('適用範囲（all, specific等）');
            
            // 有効フラグ
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            
            // 備考
            $table->text('notes')->nullable()->comment('備考');
            
            $table->timestamps();
            
            // インデックス
            $table->index(['is_active', 'effective_from', 'effective_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};