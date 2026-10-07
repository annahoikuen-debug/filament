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
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->foreignId('facility_id')
                ->nullable()
                ->after('id')
                ->constrained('facilities')
                ->cascadeOnDelete()
                ->comment('施設ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropForeignId('facility_id');
        });
    }
};
