<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Default Settings
    |--------------------------------------------------------------------------
    |
    | These settings apply to both invoices and receipts unless overridden
    | in the specific sections below.
    |
    */

    'default' => [
        // Paper settings
        'paper_size' => 'a4',
        'paper_orientation' => 'portrait',

        // Margins (mm)
        'margin_top' => 15,
        'margin_right' => 15,
        'margin_bottom' => 15,
        'margin_left' => 15,

        // Font settings (ipaexg、Windows標準フォント優先、Noto Sans JPはフォールバック)
        'font_family' => "'ipaexg', 'Yu Mincho', 'YuMincho', 'Yu Gothic', 'YuGothic', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif",
        'font_family_numbers' => "'ipaexg', 'Yu Gothic', 'YuGothic', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif",
        'font_size' => 10.5,
        'line_height' => 1.6,

        // Colors
        'primary_color' => '#1e3a8a',
        'colors' => [
            'primary' => '#1e3a8a',
            'primary_light' => '#3b82f6',
            'secondary' => '#374151',
            'secondary_light' => '#6b7280',
            'accent' => '#dc2626',
            'success' => '#059669',
            'background' => '#ffffff',
            'background_alt' => '#fafafa',
            'border' => '#e5e7eb',
            'border_light' => '#f3f4f6',
            'text' => '#111827',
            'text_light' => '#4b5563',
        ],

        // Typography
        'typography' => [
            'font_family' => "'ipaexg', 'Yu Mincho', 'YuMincho', 'Yu Gothic', 'YuGothic', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif",
            'font_size_base' => 10.5,
            'font_size_sm' => 9,
            'font_size_lg' => 12,
            'font_size_title' => 18,
            'font_size_header' => 22,
            'font_size_amount' => 24,
            'font_weight_normal' => 400,
            'font_weight_medium' => 500,
            'font_weight_semibold' => 600,
            'font_weight_bold' => 700,
        ],

        // Spacing (mm)
        'spacing' => [
            'page_margin' => 15,
            'xs' => 2,
            'sm' => 4,
            'md' => 6,
            'lg' => 8,
            'xl' => 12,
            '2xl' => 16,
        ],

        // Invoice compliance
        'invoice_compliance' => [
            'show_registration_number_prominently' => true,
            'registration_number_position' => 'header_right', // or 'below_total'
            'separate_tax_rates' => true, // 10%と8%を分けて表示
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

        // Feature toggles
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
        'table_header_bg' => '#f8fafc',
        'table_row_even_bg' => '#ffffff',
        'table_row_odd_bg' => '#fafafa',
        'table_border_color' => '#e5e7eb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice-Specific Settings
    |--------------------------------------------------------------------------
    |
    | These settings override the default settings for invoices only.
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        // Invoice-specific overrides can go here
        // Example:
        // 'margin_top' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt-Specific Settings
    |--------------------------------------------------------------------------
    |
    | These settings override the default settings for receipts only.
    |--------------------------------------------------------------------------
    */

    'receipt' => [
        // Receipt-specific overrides can go here
        // Example:
        // 'show_qr_code' => true,
    ],
];
