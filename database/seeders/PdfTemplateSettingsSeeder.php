<?php

namespace Database\Seeders;

use App\Models\PdfTemplateSetting;
use Illuminate\Database\Seeder;

class PdfTemplateSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 請求書テンプレート（デフォルト）
        PdfTemplateSetting::updateOrCreate(
            ['key' => 'invoice', 'is_default' => true],
            [
                'name' => '請求書（標準）',
                'description' => '月次請求書用の標準テンプレート',
                'key' => 'invoice',
                'paper_size' => 'a4',
                'paper_orientation' => 'portrait',
                'margin_top' => 15,
                'margin_right' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'font_family' => 'YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif',
                'font_size' => 11,
                'line_height' => 1.6,
                'primary_color' => '#1f2937',
                'secondary_color' => '#4b5563',
                'accent_color' => '#dc2626',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'border_color' => '#d1d5db',
                'header_bg_color' => '#f9fafb',
                'total_bg_color' => '#fef3c7',
                'tax_table_header_bg' => '#f3f4f6',
                'show_facility_logo' => false,
                'facility_logo_path' => null,
                'show_facility_info' => true,
                'show_tax_breakdown' => true,
                'show_daily_charges_detail' => true,
                'show_qr_code' => false,
                'qr_code_data' => null,
                'header_html' => null,
                'footer_html' => null,
                'show_page_numbers' => true,
                'table_header_bg' => '#f9fafb',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#f9fafb',
                'table_border_color' => '#e5e7eb',
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'notes' => '請求書の標準テンプレート',
            ]
        );

        // 領収書テンプレート（デフォルト）
        PdfTemplateSetting::updateOrCreate(
            ['key' => 'receipt', 'is_default' => true],
            [
                'name' => '領収書（標準）',
                'description' => '領収書用の標準テンプレート',
                'key' => 'receipt',
                'paper_size' => 'a4',
                'paper_orientation' => 'portrait',
                'margin_top' => 15,
                'margin_right' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'font_family' => 'YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif',
                'font_size' => 11,
                'line_height' => 1.6,
                'primary_color' => '#1f2937',
                'secondary_color' => '#4b5563',
                'accent_color' => '#dc2626',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'border_color' => '#d1d5db',
                'header_bg_color' => '#f9fafb',
                'total_bg_color' => '#fef3c7',
                'tax_table_header_bg' => '#f3f4f6',
                'show_facility_logo' => false,
                'facility_logo_path' => null,
                'show_facility_info' => true,
                'show_tax_breakdown' => true,
                'show_daily_charges_detail' => true,
                'show_qr_code' => false,
                'qr_code_data' => null,
                'header_html' => null,
                'footer_html' => null,
                'show_page_numbers' => true,
                'table_header_bg' => '#f9fafb',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#f9fafb',
                'table_border_color' => '#e5e7eb',
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'notes' => '領収書の標準テンプレート',
            ]
        );

        // 一括請求書テンプレート（デフォルト）
        PdfTemplateSetting::updateOrCreate(
            ['key' => 'invoice_bulk', 'is_default' => true],
            [
                'name' => '一括請求書（標準）',
                'description' => '複数月分一括出力用テンプレート',
                'key' => 'invoice_bulk',
                'paper_size' => 'a4',
                'paper_orientation' => 'portrait',
                'margin_top' => 15,
                'margin_right' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'font_family' => 'YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif',
                'font_size' => 11,
                'line_height' => 1.6,
                'primary_color' => '#1f2937',
                'secondary_color' => '#4b5563',
                'accent_color' => '#dc2626',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'border_color' => '#d1d5db',
                'header_bg_color' => '#f9fafb',
                'total_bg_color' => '#fef3c7',
                'tax_table_header_bg' => '#f3f4f6',
                'show_facility_logo' => false,
                'facility_logo_path' => null,
                'show_facility_info' => true,
                'show_tax_breakdown' => true,
                'show_daily_charges_detail' => true,
                'show_qr_code' => false,
                'qr_code_data' => null,
                'header_html' => null,
                'footer_html' => null,
                'show_page_numbers' => true,
                'table_header_bg' => '#f9fafb',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#f9fafb',
                'table_border_color' => '#e5e7eb',
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'notes' => '一括請求書の標準テンプレート',
            ]
        );
    }
}
