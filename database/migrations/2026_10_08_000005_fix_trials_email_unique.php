<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ソフトデリートと併用するため、emailのDBレベル一意制約を解除し
     * 通常インデックスに変更（一意性はアプリケーション層で担保）
     * （既にunique制約でマイグレーション済みのDBのみ対処）
     */
    public function up(): void
    {
        $indexes = $this->trialIndexes();

        if (in_array('trials_email_unique', $indexes, true)) {
            // SQLiteはALTER TABLE DROP INDEX非対応のため生SQLで削除
            DB::statement('DROP INDEX trials_email_unique');

            Schema::table('trials', function (Blueprint $table) {
                $table->index('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = $this->trialIndexes();

        if (in_array('trials_email_index', $indexes, true)) {
            Schema::table('trials', function (Blueprint $table) {
                $table->dropIndex(['email']);
            });

            DB::statement('CREATE UNIQUE INDEX trials_email_unique ON trials (email)');
        }
    }

    private function trialIndexes(): array
    {
        return collect(DB::select('SELECT name FROM sqlite_master WHERE type = "index" AND tbl_name = "trials"'))
            ->pluck('name')
            ->map(fn (string $name) => strtolower($name))
            ->all();
    }
};
