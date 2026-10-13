<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('トークン名（外部システム名等）');
            $table->string('token', 80)->unique()->comment('ハッシュ化済みトークン');
            $table->text('abilities')->nullable()->comment('権限: invoices.read, invoices.write');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
