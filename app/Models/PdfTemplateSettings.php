<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class PdfTemplateSettings extends Model
{
    protected $table = 'pdf_template_settings';

    protected $fillable = [
        'key',
        'locale',
        'theme',
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
        'custom_css',
        'translations',
        'show_page_numbers',
        'table_header_bg',
        'table_row_even_bg',
        'table_row_odd_bg',
        'table_border_color',
        'is_active',
        'is_default',
        'notes',
        'version',
        'theme_config',
    ];

    protected $casts = [
        'font_size' => 'integer',
        'margin_top' => 'integer',
        'margin_right' => 'integer',
        'margin_bottom' => 'integer',
        'margin_left' => 'integer',
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
        'theme_config' => 'array',
        'translations' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $template) {
            if ($template->is_default) {
                // 他の同じkeyのテンプレートのis_defaultをfalseに
                self::where('key', $template->key)
                    ->where('id', '!=', $template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * 指定ロケール・テーマのアクティブな最新テンプレートを取得
     */
    public static function getActive(string $key, ?string $locale = null, ?string $theme = null): ?self
    {
        $locale = $locale ?? app()->getLocale();
        $theme = $theme ?? 'standard';

        return self::where('key', $key)
            ->where('locale', $locale)
            ->where('theme', $theme)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * 指定ロケールのデフォルトテンプレートを取得
     */
    public static function getDefault(string $key, ?string $locale = null): ?self
    {
        $locale = $locale ?? app()->getLocale();

        return self::where('key', $key)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * フォールバック付きでテンプレートを取得
     * 1. 指定ロケールのデフォルト
     * 2. 指定ロケールのアクティブ
     * 3. デフォルトロケール(ja)のデフォルト
     * 4. デフォルトロケール(ja)のアクティブ
     */
    public static function getWithFallback(string $key, ?string $locale = null): ?self
    {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'ja');

        // 1. 指定ロケールのデフォルト
        $template = self::getDefault($key, $locale);
        if ($template) return $template;

        // 2. 指定ロケールのアクティブ
        $template = self::getActive($key, $locale);
        if ($template) return $template;

        // 3. フォールバックロケールのデフォルト
        if ($locale !== $fallbackLocale) {
            $template = self::getDefault($key, $fallbackLocale);
            if ($template) return $template;

            // 4. フォールバックロケールのアクティブ
            $template = self::getActive($key, $fallbackLocale);
            if ($template) return $template;
        }

        return null;
    }

    /**
     * 利用可能なロケール一覧を取得
     */
    public static function getAvailableLocales(string $key): array
    {
        return self::where('key', $key)
            ->where('is_active', true)
            ->distinct('locale')
            ->pluck('locale')
            ->toArray();
    }

    /**
     * 利用可能なテーマ一覧を取得
     */
    public static function getAvailableThemes(): array
    {
        return [
            'standard' => '標準',
            'minimal' => 'ミニマル',
            'classic' => 'クラシック',
            'modern' => 'モダン',
            'compact' => 'コンパクト',
        ];
    }

    /**
     * テーマごとのデフォルト設定を取得
     */
    public static function getThemeDefaults(string $theme): array
    {
        $defaults = [
            'standard' => [
                'primary_color' => '#1e3a8a',
                'secondary_color' => '#374151',
                'accent_color' => '#dc2626',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'border_color' => '#e5e7eb',
                'header_bg_color' => '#f9fafb',
                'total_bg_color' => '#fef3c7',
                'tax_table_header_bg' => '#f3f4f6',
                'table_header_bg' => '#f8fafc',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#fafafa',
                'table_border_color' => '#e5e7eb',
            ],
            'minimal' => [
                'primary_color' => '#374151',
                'secondary_color' => '#6b7280',
                'accent_color' => '#9ca3af',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'border_color' => '#e5e7eb',
                'header_bg_color' => '#f9fafb',
                'total_bg_color' => '#f3f4f6',
                'tax_table_header_bg' => '#f3f4f6',
                'table_header_bg' => '#f3f4f6',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#f9fafb',
                'table_border_color' => '#e5e7eb',
            ],
            'classic' => [
                'primary_color' => '#1c1c1c',
                'secondary_color' => '#4a4a4a',
                'accent_color' => '#8b0000',
                'background_color' => '#fafafa',
                'text_color' => '#1a1a1a',
                'border_color' => '#c0c0c0',
                'header_bg_color' => '#2c2c2c',
                'total_bg_color' => '#f0f0f0',
                'tax_table_header_bg' => '#e0e0e0',
                'table_header_bg' => '#3c3c3c',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#f5f5f5',
                'table_border_color' => '#d0d0d0',
            ],
            'modern' => [
                'primary_color' => '#0f172a',
                'secondary_color' => '#334155',
                'accent_color' => '#06b6d4',
                'background_color' => '#ffffff',
                'text_color' => '#0f172a',
                'border_color' => '#e2e8f0',
                'header_bg_color' => '#0f172a',
                'total_bg_color' => '#ecfeff',
                'tax_table_header_bg' => '#f0f9ff',
                'table_header_bg' => '#0f172a',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#f8fafc',
                'table_border_color' => '#e2e8f0',
            ],
            'compact' => [
                'primary_color' => '#1e3a8a',
                'secondary_color' => '#374151',
                'accent_color' => '#dc2626',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'border_color' => '#e5e7eb',
                'header_bg_color' => '#f9fafb',
                'total_bg_color' => '#fef3c7',
                'tax_table_header_bg' => '#f3f4f6',
                'table_header_bg' => '#f8fafc',
                'table_row_even_bg' => '#ffffff',
                'table_row_odd_bg' => '#fafafa',
                'table_border_color' => '#e5e7eb',
            ],
        ];

        return $defaults[$theme] ?? $defaults['standard'];
    }

    /**
     * 設定を配列として取得（config/pdf.php互換形式）
     */
    public function toConfigArray(): array
    {
        // テーマのデフォルト設定をベースにする
        $themeDefaults = self::getThemeDefaults($this->theme ?? 'standard');
        
        // theme_configで上書き
        $themeConfig = $this->theme_config ?? [];
        
        // 色設定をマージ（theme_config > インスタンス > テーマデフォルト）
        $colors = array_merge(
            $themeDefaults,
            [
                'primary_color' => $this->primary_color,
                'secondary_color' => $this->secondary_color,
                'accent_color' => $this->accent_color,
                'background_color' => $this->background_color,
                'text_color' => $this->text_color,
                'border_color' => $this->border_color,
                'header_bg_color' => $this->header_bg_color,
                'total_bg_color' => $this->total_bg_color,
                'tax_table_header_bg' => $this->tax_table_header_bg,
                'table_header_bg' => $this->table_header_bg,
                'table_row_even_bg' => $this->table_row_even_bg,
                'table_row_odd_bg' => $this->table_row_odd_bg,
                'table_border_color' => $this->table_border_color,
            ],
            $themeConfig
        );

        $logoPath = null;
        if ($this->show_facility_logo && $this->facility_logo_path) {
            // 相対パスの場合はstorage/app/publicからの相対パスとして扱う
            if (Storage::disk('public')->exists($this->facility_logo_path)) {
                $logoPath = Storage::disk('public')->url($this->facility_logo_path);
            } elseif (file_exists(storage_path("app/public/{$this->facility_logo_path}"))) {
                $logoPath = Storage::disk('public')->url($this->facility_logo_path);
            } else {
                $logoPath = $this->facility_logo_path;
            }
        }

        // 翻訳文字列を取得
        $translations = $this->translations ?? [];

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
            'locale' => $this->locale,
            'theme' => $this->theme,
            'theme_config' => $themeConfig,
            'custom_css' => $this->custom_css,
            'translations' => $translations,
            ...$colors,
            'show_facility_logo' => $this->show_facility_logo,
            'facility_logo_path' => $logoPath,
            'show_facility_info' => $this->show_facility_info,
            'show_tax_breakdown' => $this->show_tax_breakdown,
            'show_daily_charges_detail' => $this->show_daily_charges_detail,
            'show_qr_code' => $this->show_qr_code,
            'qr_code_data' => $this->qr_code_data,
            'header_html' => $this->header_html,
            'footer_html' => $this->footer_html,
            'show_page_numbers' => $this->show_page_numbers,
            'invoice_compliance' => [
                'show_registration_number_prominently' => true,
                'registration_number_position' => 'header_right',
                'separate_tax_rates' => true,
                'show_tax_breakdown_by_rate' => true,
                'required_fields' => [
                    'issuer_name',
                    'issuer_address',
                    'issuer_registration_number',
                    'issue_date',
                    'recipient_name',
                    'description_of_items',
                    'total_amount_with_tax',
                    'consumption_tax_amount',
                    'applicable_tax_rate'
                ]
            ],
            'version' => $this->version,
        ];
    }

    /**
     * 翻訳文字列を取得（キーが存在しない場合はキーを返す）
     */
    public function translate(string $key, array $params = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $translations = $this->translations ?? [];
        
        // ネストしたキー対応（ドット区切り）
        $value = data_get($translations, $locale . '.' . $key);
        
        // フォールバック: 設定されたフォールバックロケール
        if ($value === null && $locale !== config('app.fallback_locale', 'ja')) {
            $value = data_get($translations, config('app.fallback_locale', 'ja') . '.' . $key);
        }
        
        if ($value === null) {
            return $key;
        }
        
        // パラメータ置換
        if (!empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $value = str_replace('{' . $paramKey . '}', $paramValue, $value);
            }
        }
        
        return $value;
    }

    /**
     * デフォルト設定を作成（多言語・テーマ対応版）
     */
    public static function createDefaults(): void
    {
        $locales = ['ja', 'en'];
        $themes = ['standard', 'minimal', 'classic', 'modern', 'compact'];

        foreach ($locales as $locale) {
            foreach ($themes as $theme) {
                $isDefault = ($locale === 'ja' && $theme === 'standard');
                
                foreach (['invoice', 'receipt'] as $key) {
                    self::firstOrCreate(
                        ['key' => $key, 'locale' => $locale, 'theme' => $theme, 'is_default' => $isDefault],
                        array_merge([
                            'key' => $key,
                            'locale' => $locale,
                            'theme' => $theme,
                            'name' => self::getDefaultName($key, $locale, $theme),
                            'description' => self::getDefaultDescription($key, $locale, $theme),
                            'is_default' => $isDefault,
                            'is_active' => true,
                            'version' => 1,
                        ], self::getDefaultAttributes($theme))
                    );
                }
            }
        }
    }

    /**
     * デフォルト名を取得
     */
    private static function getDefaultName(string $key, string $locale, string $theme): string
    {
        $names = [
            'ja' => [
                'invoice' => '請求書テンプレート',
                'receipt' => '領収証テンプレート',
            ],
            'en' => [
                'invoice' => 'Invoice Template',
                'receipt' => 'Receipt Template',
            ],
        ];

        $baseName = $names[$locale][$key] ?? $names['ja'][$key];
        $themeName = self::getAvailableThemes()[$theme] ?? $theme;
        
        return $baseName . '（' . $themeName . '）';
    }

    /**
     * デフォルト説明を取得
     */
    private static function getDefaultDescription(string $key, string $locale, string $theme): string
    {
        $descriptions = [
            'ja' => [
                'invoice' => '月次請求書用の標準テンプレート',
                'receipt' => '領収証用の標準テンプレート',
            ],
            'en' => [
                'invoice' => 'Standard template for monthly invoices',
                'receipt' => 'Standard template for receipts',
            ],
        ];

        return $descriptions[$locale][$key] ?? $descriptions['ja'][$key];
    }

    /**
     * テーマごとのデフォルト属性を取得
     */
    private static function getDefaultAttributes(string $theme): array
    {
        $themeDefaults = self::getThemeDefaults($theme);
        
        return array_merge([
            'paper_size' => 'a4',
            'paper_orientation' => 'portrait',
            'margin_top' => 15,
            'margin_right' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'font_family' => "'Yu Mincho', 'YuMincho', 'Yu Gothic', 'YuGothic', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif",
            'font_size' => 10.5,
            'line_height' => 1.6,
            'show_facility_logo' => false,
            'facility_logo_path' => null,
            'show_facility_info' => true,
            'show_tax_breakdown' => true,
            'show_daily_charges_detail' => true,
            'show_qr_code' => false,
            'qr_code_data' => null,
            'header_html' => null,
            'footer_html' => null,
            'custom_css' => null,
            'translations' => null,
            'show_page_numbers' => true,
            'theme_config' => null,
        ], $themeDefaults);
    }
}