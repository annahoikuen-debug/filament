<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id');
            $table->text('user_message');
            $table->string('intent');
            $table->text('bot_reply');
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->timestamps();

            $table->index(['facility_id', 'created_at']);
            $table->index('session_id');
            $table->index('intent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};
