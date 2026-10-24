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
        Schema::table('residents', function (Blueprint $table) {
            $table->unsignedInteger('base_rent_non_taxable')->after('base_rent')->default(0)
                ->comment('非課税部分の基本家賃');
            $table->unsignedInteger('base_rent_taxable')->after('base_rent_non_taxable')->default(0)
                ->comment('課税部分の基本家賃（共益費等）');
            $table->unsignedInteger('base_management_fee_non_taxable')->after('base_management_fee')->default(0)
                ->comment('非課税部分の基本管理費');
            $table->unsignedInteger('base_management_fee_taxable')->after('base_management_fee_non_taxable')->default(0)
                ->comment('課税部分の基本管理費');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['base_rent_non_taxable', 'base_rent_taxable', 'base_management_fee_non_taxable', 'base_management_fee_taxable']);
        });
    }
};
