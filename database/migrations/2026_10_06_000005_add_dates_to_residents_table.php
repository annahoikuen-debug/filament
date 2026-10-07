<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->date('move_in_date')->nullable()->after('status')->comment('入居開始日');
            $table->date('move_out_date')->nullable()->after('move_in_date')->comment('退去日(未定はnull)');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['move_in_date', 'move_out_date']);
        });
    }
};
