<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charge_items', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name')->comment('表示名（請求書・領収書用）');
            $table->text('description')->nullable()->after('display_name')->comment('説明・備考');
        });
    }

    public function down(): void
    {
        Schema::table('charge_items', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description']);
        });
    }
};