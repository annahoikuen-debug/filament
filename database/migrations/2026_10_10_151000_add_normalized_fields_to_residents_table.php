<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->after('name');
            $table->string('normalized_kana')->nullable()->after('name_kana');

            $table->index(['facility_id', 'normalized_kana']);
            $table->index(['facility_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex(['facility_id', 'normalized_kana']);
            $table->dropIndex(['facility_id', 'normalized_name']);

            $table->dropColumn(['normalized_name', 'normalized_kana']);
        });
    }
};
