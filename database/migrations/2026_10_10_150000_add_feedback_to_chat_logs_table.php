<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->string('feedback', 20)->nullable()->after('facility_id');
            $table->timestamp('feedback_at')->nullable()->after('feedback');
            $table->index(['facility_id', 'feedback']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->dropIndex(['facility_id', 'feedback']);
            $table->dropColumn(['feedback', 'feedback_at']);
        });
    }
};
