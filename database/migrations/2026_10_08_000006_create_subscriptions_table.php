<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 本契約（サブスクリプション）テーブル
     * トライアル→本契約移行を記録する
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trial_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['starter', 'standard', 'enterprise']);
            $table->enum('status', ['trialing', 'active', 'cancelled'])->default('active');
            $table->unsignedInteger('monthly_price'); // 月額（税別）
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // 電子契約の同意記録（電子契約サービス連携の拡張ポイント）
            $table->timestamp('contract_accepted_at')->nullable();
            $table->string('contract_accepted_ip', 45)->nullable();
            $table->text('contract_accepted_user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
