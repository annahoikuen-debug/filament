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
            // 既存のユニーク制約を削除
            $table->dropUnique('pdf_template_settings_key_unique');
            
            // 複合ユニークキーを追加 (key, locale, theme)
            $table->unique(['key', 'locale', 'theme'], 'pdf_template_settings_key_locale_theme_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_template_settings', function (Blueprint $table) {
            $table->dropUnique('pdf_template_settings_key_locale_theme_unique');
            $table->unique('key', 'pdf_template_settings_key_unique');
        });
    }
};
