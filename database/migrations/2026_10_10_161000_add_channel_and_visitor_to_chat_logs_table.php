<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->string('channel')->default('internal')->after('id');
            $table->uuid('visitor_id')->nullable()->after('channel');
            $table->index('channel');
            $table->index('visitor_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropIndex(['visitor_id']);
            $table->dropColumn(['channel', 'visitor_id']);
        });
    }
};
