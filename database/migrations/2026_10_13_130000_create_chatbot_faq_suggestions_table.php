<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_faq_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('cluster_key', 64)->unique(); // 代表メッセージのハッシュ
            $table->text('representative_message');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('last_occurred_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | dismissed
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_faq_id')->nullable()->constrained('chatbot_faqs')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'occurrence_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_faq_suggestions');
    }
};
