<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_synonyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete(); // null = 全施設共通
            $table->string('from_word', 100);   // 入力されやすい語（例: 引き落とし）
            $table->string('to_word', 100);     // 正規化後の語（例: 口座振替）
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['facility_id', 'from_word']);
            $table->index(['facility_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_synonyms');
    }
};
