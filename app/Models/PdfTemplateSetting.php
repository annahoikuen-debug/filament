<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfTemplateSetting extends Model
{
    use HasFactory;

    protected $table = 'pdf_template_settings';

    protected $fillable = [
        'key',
        'name',
        'description',
        'paper_size',
        'paper_orientation',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
        'font_family',
        'font_size',
        'line_height',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'border_color',
        'header_bg_color',
        'total_bg_color',
        'tax_table_header_bg',
        'show_facility_logo',
        'facility_logo_path',
        'show_facility_info',
        'show_tax_breakdown',
        'show_daily_charges_detail',
        'show_qr_code',
        'qr_code_data',
        'header_html',
        'footer_html',
        'show_page_numbers',
        'table_header_bg',
        'table_row_even_bg',
        'table_row_odd_bg',
        'table_border_color',
        'is_active',
        'is_default',
        'notes',
        'version',
    ];

    protected $casts = [
        'margin_top' => 'integer',
        'margin_right' => 'integer',
        'margin_bottom' => 'integer',
        'margin_left' => 'integer',
        'font_size' => 'integer',
        'line_height' => 'decimal:2',
        'show_facility_logo' => 'boolean',
        'show_facility_info' => 'boolean',
        'show_tax_breakdown' => 'boolean',
        'show_daily_charges_detail' => 'boolean',
        'show_qr_code' => 'boolean',
        'show_page_numbers' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'version' => 'integer',
    ];

    /**
     * 指定キーの有効なテンプレートを取得
     */
    public static function getForKey(string $key): ?self
    {
        return self::where('key', $key)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * デフォルトテンプレートを取得
     */
    public static function getDefault(string $key): ?self
    {
        return self::where('key', $key)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * config/pdf.php 互換の配列を返す
     */
    public function toConfigArray(): array
    {
        return [
            'paper_size' => $this->paper_size,
            'paper_orientation' => $this->paper_orientation,
            'margin_top' => $this->margin_top,
            'margin_right' => $this->margin_right,
            'margin_bottom' => $this->margin_bottom,
            'margin_left' => $this->margin_left,
            'font_family' => $this->font_family,
            'font_size' => $this->font_size,
            'line_height' => (float) $this->line_height,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'border_color' => $this->border_color,
            'header_bg_color' => $this->header_bg_color,
            'total_bg_color' => $this->total_bg_color,
            'tax_table_header_bg' => $this->tax_table_header_bg,
            'show_facility_logo' => $this->show_facility_logo,
            'facility_logo_path' => $this->facility_logo_path,
            'show_facility_info' => $this->show_facility_info,
            'show_tax_breakdown' => $this->show_tax_breakdown,
            'show_daily_charges_detail' => $this->show_daily_charges_detail,
            'show_qr_code' => $this->show_qr_code,
            'qr_code_data' => $this->qr_code_data,
            'header_html' => $this->header_html,
            'footer_html' => $this->footer_html,
            'show_page_numbers' => $this->show_page_numbers,
            'table_header_bg' => $this->table_header_bg,
            'table_row_even_bg' => $this->table_row_even_bg,
            'table_row_odd_bg' => $this->table_row_odd_bg,
            'table_border_color' => $this->table_border_color,
        ];
    }

    /**
     * CSS変数用の配列を返す（Bladeテンプレート用）
     */
    public function toCssVariables(): array
    {
        return [
            '--pdf-primary-color' => $this->primary_color,
            '--pdf-secondary-color' => $this->secondary_color,
            '--pdf-accent-color' => $this->accent_color,
            '--pdf-background-color' => $this->background_color,
            '--pdf-text-color' => $this->text_color,
            '--pdf-border-color' => $this->border_color,
            '--pdf-header-bg' => $this->header_bg_color,
            '--pdf-total-bg' => $this->total_bg_color,
            '--pdf-tax-table-header-bg' => $this->tax_table_header_bg,
            '--pdf-table-header-bg' => $this->table_header_bg,
            '--pdf-table-row-even-bg' => $this->table_row_even_bg,
            '--pdf-table-row-odd-bg' => $this->table_row_odd_bg,
            '--pdf-table-border-color' => $this->table_border_color,
            '--pdf-font-family' => $this->font_family,
            '--pdf-font-size' => $this->font_size . 'pt',
            '--pdf-line-height' => (float) $this->line_height,
        ];
    }

    /**
     * CSS変数文字列を生成
     */
    public function toCssVariableString(): string
    {
        $vars = $this->toCssVariables();
        return implode('; ', array_map(fn($k, $v) => "$k: $v", array_keys($vars), $vars));
    }
}