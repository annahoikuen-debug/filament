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
            $table->string('tax_type')->default('standard')->after('default_price')
                ->comment('税区分(non_taxable/standard/reduced)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charge_items', function (Blueprint $table) {
            $table->dropColumn('tax_type');
        });
    }
};
