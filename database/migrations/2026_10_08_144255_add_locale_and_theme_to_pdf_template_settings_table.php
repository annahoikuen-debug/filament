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
        Schema::table('pdf_template_settings', function (Blueprint $table) {
            // ロケール（多言語対応）
            $table->string('locale', 10)->default('ja')->after('key')
                ->comment('ロケール (ja, en, zh 等)');

            // テーマ名
            $table->string('theme', 50)->default('standard')->after('locale')
                ->comment('テーマ名 (standard, minimal, classic, modern 等)');

            // テーマ設定JSON（上書き可能なスタイル設定）
            $table->json('theme_config')->nullable()->after('theme')
                ->comment('テーマ固有のスタイル設定（JSON）');

            // カスタムCSS
            $table->text('custom_css')->nullable()->after('footer_html')
                ->comment('カスタムCSS（SCSS記法も可）');

            // 翻訳文字列JSON
            $table->json('translations')->nullable()->after('custom_css')
                ->comment('多言語翻訳文字列（JSON）');

            // インデックス
            $table->index(['key', 'locale', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_template_settings', function (Blueprint $table) {
            $table->dropIndex(['key', 'locale', 'is_active']);
            $table->dropColumn([
                'locale',
                'theme',
                'theme_config',
                'custom_css',
                'translations',
            ]);
        });
    }
};
