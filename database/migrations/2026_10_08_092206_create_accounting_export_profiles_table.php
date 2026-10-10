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
        Schema::create('accounting_export_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete()->comment('施設ID');
            $table->string('name')->comment('プロファイル名 (例: freee標準, MFクラウド会計, 弥生会計, 勘定奉行)');
            $table->string('software_type')->comment('会計ソフト種類: freee, mf, yayoi, kanjobugyo, custom');
            $table->json('header_mapping')->nullable()->comment('ヘッダー列マッピング (CSV列順・名称)');
            $table->json('field_mapping')->nullable()->comment('フィールドマッピング (必須項目・任意項目の対応)');
            $table->json('tax_code_mapping')->nullable()->comment('税区分コードマッピング (標準→ソフト固有)');
            $table->json('department_mapping')->nullable()->comment('部門マッピング設定');
            $table->json('tag_mapping')->nullable()->comment('タグマッピング設定');
            $table->json('sub_account_mapping')->nullable()->comment('補助科目マッピング設定');
            $table->string('date_format')->default('Y/m/d')->comment('日付フォーマット');
            $table->string('encoding')->default('UTF-8')->comment('文字エンコーディング (UTF-8, SJIS, CP932)');
            $table->boolean('include_header')->default(true)->comment('ヘッダー行含む');
            $table->boolean('bom')->default(true)->comment('BOM付与');
            $table->string('line_ending')->default('CRLF')->comment('改行コード (CRLF, LF)');
            $table->json('default_values')->nullable()->comment('デフォルト値設定 (摘要テンプレート等)');
            $table->boolean('is_default')->default(false)->comment('デフォルトプロファイルフラグ');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['facility_id', 'software_type', 'name'], 'unique_facility_software_name');
            $table->index(['facility_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_export_profiles');
    }
};
