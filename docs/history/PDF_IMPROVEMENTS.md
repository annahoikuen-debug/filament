# PDF Generation System Review and Improvement Proposals

## Overview
This document reviews the PDF generation system in the `App\Services\InvoicePdfService` class and related templates, identifies issues, and proposes improvements.

## Issues Found

### 1. Inefficient Double PDF Generation (Performance)
When QR code is enabled, the PDF is generated twice:
- First generation without QR code data (lines 64-79)
- Second generation after adding QR code data to template config (lines 94-109)

**Impact:** Unnecessary processing, especially noticeable when generating multiple invoices.

### 2. Broken Bank Transfer QR Code Data (Functionality)
The `getBankTransferQrCodeData` method uses Blade-style `{{ }}` placeholders in a heredoc string that are not processed, resulting in literal `{{ ... }}` in the QR code data.

**Impact:** Generated QR codes for bank transfers contain invalid data and will not work for payments.

### 3. Conflicting Margin and Paper Size Settings (Presentation)
Margins and paper size are set in two places:
- Service method `applyMargins()` via `setPaper()`
- CSS `@page` rules in Blade templates (invoice.blade.php and receipt.blade.php)

According to DomPDF documentation, CSS `@page` settings override those set via `setPaper()`, causing confusion about which settings take effect.

### 4. Hardcoded QR Code Size (Usability)
The `generatePaymentQrCode` method generates QR codes at 200x200 pixels, but the templates display them at 24mm x 24mm. This mismatch may affect scan quality.

### 5. Redundant Font Configuration (Maintainability)
The `applyJapaneseFontSettings` method sets `defaultFont` option, but the CSS `font-family` already specifies the desired font fallback chain. This creates redundant configuration.

### 6. Missing Configuration Customization (Flexibility)
PDF settings (margins, colors, fonts, etc.) are hardcoded in the service's `getTemplateConfig()` method. There's no way to override these via configuration files without modifying the service.

### 7. Potential Font Loading Issues (Reliability)
While the test script succeeded, the font loading logic depends on Noto Sans JP fonts being installed via `installNotoSansJpFonts()`. If these fonts aren't installed, the system falls back to other fonts, but there's no automatic fallback mechanism or clear documentation.

## Proposed Improvements

### 1. Eliminate Double PDF Generation
**Solution:** Generate QR code data before creating the PDF object, then generate the PDF once with all data.

**Implementation:**
```php
public function generatePdfFromInvoice(MonthlyInvoice $invoice, string $type = 'invoice', ?array $facility = null): DomPdfInstance
{
    // ... existing loading ...

    // Prepare template config
    $templateConfig = $this->getTemplateConfig($type);

    // Generate QR code data if needed (BEFORE creating PDF)
    if ($templateConfig['show_qr_code'] ?? false) {
        if ($type === 'invoice') {
            $qrCodeData = $this->getBankTransferQrCodeData($invoice, $facility ?? config('facility'));
        } else {
            $qrCodeData = $this->getReceiptVerificationQrCodeData($invoice);
        }
        $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData);
    }

    // Generate PDF ONCE with all data
    $pdf = Pdf::loadView($view, [
        // ... existing data ...
        'template' => $templateConfig,
    ]);

    // Apply font and margin settings
    $this->applyJapaneseFontSettings($pdf, $templateConfig);
    $this->applyMargins($pdf, $templateConfig);

    return $pdf;
}
```

### 2. Fix Bank Transfer QR Code Data
**Solution:** Replace Blade-style placeholders with actual PHP variables in `getBankTransferQrCodeData`.

**Implementation:**
```php
private function getBankTransferQrCodeData(MonthlyInvoice $invoice, array $facility): string
{
    // Generate proper bank transfer QR code data (JPCQR format example)
    $data = sprintf(
        "STU{\n振:%s\n種:振込\n金:%d\n名:%s\n współ:%s %s\n 口:%s %s\n 住:%s\nREF:%s-%s}",
        $facility['bank']['account_number'] ?? '0123456',
        (int)$invoice->total_with_tax,
        $facility['bank']['account_holder'] ?? '',
        $facility['bank']['name'] ?? '',
        $facility['bank']['branch_name'] ?? '',
        $facility['bank']['account_type'] ?? '普通',
        $facility['bank']['account_number'] ?? '',
        $facility['address'] ?? '',
        str_replace('-', '', $invoice->billing_year_month),
        str_pad($invoice->resident->id, 3, '0', STR_PAD_LEFT)
    );

    return str_replace(["\r\n", "\n", "\r"], '', $data);
}
```

### 3. Unify Margin and Paper Size Configuration
**Solution:** Remove `@page` margin and size settings from Blade templates, relying solely on service configuration.

**Changes to `resources/views/invoices/pdf.blade.php`:**
```diff
- @page {
-     margin: 12mm 15mm 15mm 15mm;
-     size: a4 portrait;
- }
+ /* Margins and paper size set via InvoicePdfService */
```

**Changes to `resources/views/invoices/receipt.blade.php`:**
```diff
- @page {
-     margin: 10mm 12mm 12mm 12mm;
-     size: a4 portrait;
- }
+ /* Margins and paper size set via InvoicePdfService */
```

### 4. Optimize QR Code Size
**Solution:** Adjust QR code generation size to match intended display (24mm ≈ 70px at 72 DPI).

**Implementation:**
```php
private function generatePaymentQrCode(string $data): string
{
    try {
        $renderer = new \BaconQrCode\Renderer\Image\SvgImageRendererBackEnd();
        // Reduced size for 24mm display (approx 70px)
        $renderer = new \BaconQrCode\Renderer\ImageRenderer($renderer, 70, 70);
        $writer = new \BaconQrCode\Writer($renderer);
        $svg = $writer->writeString($data);
        
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    } catch (\Throwable $e) {
        return '';
    }
}
```

### 5. Remove Redundant Font Configuration
**Solution:** Remove the `defaultFont` setting from `applyJapaneseFontSettings` since CSS handles font selection.

**Implementation:**
```php
private function applyJapaneseFontSettings(DomPdfInstance $pdf, array $templateConfig): void
{
    // ... existing code ...
    $options = [
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true,
        'fontHeightRatio' => (float) $lineHeight,
        // 'defaultFont' => 'YuMincho', // Removed - CSS handles font selection
        'font_dir' => $fontDir,
        'font_cache' => storage_path('fonts'),
    ];
    // ... rest unchanged ...
}
```

### 6. Add Configuration Files for PDF Settings
**Solution:** Create `config/pdf.php` to define default settings, allowing overrides via environment or additional config files.

**Create `config/pdf.php`:**
```php
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

        // Font settings
        'font_family' => "'Noto Sans JP', 'Yu Mincho', 'YuMincho', 'Hiragino Mincho Pro', 'HGS明朝E', 'ＭＳ 明朝', serif",
        'font_family_numbers' => "'Noto Sans JP', 'Yu Gothic', 'Meiryo', sans-serif",
        'font_size' => 10.5,
        'line_height' => 1.6,

        // Colors
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
    ],
];
```

**Update `getTemplateConfig()` method:**
```php
private function getTemplateConfig(string $type): array
{
    $config = config('pdf.default', []);
    $typeConfig = config("pdf.{$type}", []);

    // Merge default with type-specific config
    $templateConfig = array_merge($config, $typeConfig);

    // Merge with baseConfig (if keeping some hardcoded values as fallback)
    // Or replace baseConfig entirely with the config system
    // For now, we'll keep the existing baseConfig as fallback for missing keys
    $baseConfig = [/* ... existing baseConfig array ... */];
    return array_replace($baseConfig, $templateConfig);
}
```

### 7. Improve Font Loading Reliability
**Solution:** Add automatic font installation check and fallback mechanism.

**Implementation:** Add a method that ensures Noto Sans JP fonts are available, installing them if missing, and log any issues.

## Priority of Fixes
1. **Critical:** Fix broken bank transfer QR code data (prevents payment processing)
2. **High:** Eliminate double PDF generation (performance improvement)
3. **Medium:** Unify margin/paper size settings (consistent output)
4. **Low:** Other improvements (maintainability, flexibility)

## Testing Recommendations
After implementing changes:
1. Run the existing test script to ensure PDFs still generate
2. Verify QR codes in generated PDFs scan correctly (especially bank transfer QR codes)
3. Test with and without QR code enabled
4. Check that margins and sizing match expectations
5. Verify font rendering for Japanese characters

## Estimated Effort
- Fixing QR code data: 1 hour
- Eliminating double generation: 30 minutes
- Unifying margin/paper size: 1 hour (includes template changes)
- Adding config files: 2 hours
- Other improvements: 1-2 hours

Total: Approximately 5-6 hours for complete implementation.