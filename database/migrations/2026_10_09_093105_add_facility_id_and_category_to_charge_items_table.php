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
        Schema::table('charge_items', function (Blueprint $table) {
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete()->after('id')->comment('施設ID');
            $table->string('category')->nullable()->after('tax_type')->comment('カテゴリ(日用品/サービス等)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charge_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facility_id');
            $table->dropColumn('category');
        });
    }
};
