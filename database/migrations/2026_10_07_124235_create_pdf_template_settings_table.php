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
        Schema::create('pdf_template_settings', function (Blueprint $table) {
            $table->id();
            
            // テンプレート識別
            $table->string('key')->unique()->comment('テンプレートキー (invoice, receipt 等)');
            $table->string('name')->comment('表示名');
            $table->string('description')->nullable()->comment('説明');
            
            // 用紙設定
            $table->string('paper_size')->default('a4')->comment('用紙サイズ (a4, a5, letter 等)');
            $table->string('paper_orientation')->default('portrait')->comment('向き');
            
            // 余白設定 (mm)
            $table->unsignedInteger('margin_top')->default(15)->comment('上余白');
            $table->unsignedInteger('margin_right')->default(15)->comment('右余白');
            $table->unsignedInteger('margin_bottom')->default(15)->comment('下余白');
            $table->unsignedInteger('margin_left')->default(15)->comment('左余白');
            
            // フォント設定
            $table->string('font_family')->default('YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif')->comment('フォントファミリ');
            $table->unsignedInteger('font_size')->default(11)->comment('基本フォントサイズ');
            $table->decimal('line_height', 3, 2)->default(1.6)->comment('行間倍率');
            
            // 色設定 (HEX)
            $table->string('primary_color', 7)->default('#1f2937')->comment('プライマリカラー');
            $table->string('secondary_color', 7)->default('#4b5563')->comment('セカンダリカラー');
            $table->string('accent_color', 7)->default('#dc2626')->comment('アクセントカラー');
            $table->string('background_color', 7)->default('#ffffff')->comment('背景色');
            $table->string('text_color', 7)->default('#111827')->comment('テキスト色');
            $table->string('border_color', 7)->default('#d1d5db')->comment('罫線色');
            $table->string('header_bg_color', 7)->default('#f9fafb')->comment('ヘッダー背景色');
            $table->string('total_bg_color', 7)->default('#fef3c7')->comment('合計欄背景色');
            $table->string('tax_table_header_bg', 7)->default('#f3f4f6')->comment('税内訳テーブルヘッダー背景');
            
            // 表示項目制御
            $table->boolean('show_facility_logo')->default(false)->comment('施設ロゴ表示');
            $table->string('facility_logo_path')->nullable()->comment('ロゴ画像パス');
            $table->boolean('show_facility_info')->default(true)->comment('施設情報表示');
            $table->boolean('show_tax_breakdown')->default(true)->comment('税内訳表示');
            $table->boolean('show_daily_charges_detail')->default(true)->comment('日々明細表示');
            $table->boolean('show_qr_code')->default(false)->comment('QRコード表示');
            $table->string('qr_code_data')->nullable()->comment('QRコードデータテンプレート');
            
            // ヘッダー・フッター
            $table->text('header_html')->nullable()->comment('カスタムヘッダーHTML');
            $table->text('footer_html')->nullable()->comment('カスタムフッターHTML');
            $table->boolean('show_page_numbers')->default(true)->comment('ページ番号表示');
            
            // テーブルスタイル
            $table->string('table_header_bg', 7)->default('#f9fafb')->comment('テーブルヘッダー背景');
            $table->string('table_row_even_bg', 7)->default('#ffffff')->comment('テーブル偶数行背景');
            $table->string('table_row_odd_bg', 7)->default('#f9fafb')->comment('テーブル奇数行背景');
            $table->string('table_border_color', 7)->default('#e5e7eb')->comment('テーブル罫線色');
            
            // 有効フラグ
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->boolean('is_default')->default(false)->comment('デフォルトテンプレート');
            
            // メタ
            $table->text('notes')->nullable()->comment('備考');
            $table->unsignedInteger('version')->default(1)->comment('バージョン');
            
            $table->timestamps();
            
            // インデックス
            $table->index(['key', 'is_active']);
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_template_settings');
    }
};