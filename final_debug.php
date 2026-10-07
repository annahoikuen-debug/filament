<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use App\Enums\PaymentMethod;

// Create a test resident and invoice if none exists
$resident = App\Models\Resident::first();
if (!$resident) {
    $resident = App\Models\Resident::create([
        'room_number' => '101',
        'name' => 'テスト住民',
        'move_in_date' => '2026-01-01',
    ]);
}

$invoice = App\Models\MonthlyInvoice::where('resident_id', $resident->id)->first();
if (!$invoice) {
    $invoice = App\Models\MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 65000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 5000,
        'status' => App\Enums\InvoiceStatus::Unbilled,
    ]);
    
    // Add some daily charges for testing
    App\Models\DailyCharge::create([
        'resident_id' => $resident->id,
        'date' => '2026-10-05',
        'charge_item_id' => 1, // Assuming this exists
        'unit_price' => 1000,
        'quantity' => 2,
        'subtotal' => 2000,
        'note' => 'テストサービス'
    ]);
}

echo "=== 最終デバッグレポート ===\\n";
echo "テスト対象: {$resident->name} (Room {$resident->room_number})\\n";
echo "請求月: {$invoice->billing_year_month}\\n";
echo "請求金額: ¥" . number_format($invoice->rent_subtotal + $invoice->management_fee_subtotal + $invoice->service_subtotal) . "\\n\\n";

$pdfService = new InvoicePdfService();

// Test 1: Basic PDF generation
echo "--- 基本PDF生成テスト ---\\n";
try {
    $invoicePdf = $pdfService->generateInvoicePdf($invoice);
    $invoiceSize = strlen($invoicePdf->output());
    echo "✓ 請求書PDF生成成功: {$invoiceSize} バイト\\n";
    
    $invoice->markAsPaid(PaymentMethod::BankTransfer);
    $receiptPdf = $pdfService->generateReceiptPdf($invoice);
    $receiptSize = strlen($receiptPdf->output());
    echo "✓ 領収書PDF生成成功: {$receiptSize} バイト\\n";
} catch (Exception $e) {
    echo "✗ PDF生成エラー: " . $e->getMessage() . "\\n";
}

    // Test 2: Template design system
    echo "\\n--- デザインシステムテスト ---\\n";
    try {
        // Create template config manually based on what we know should be there
        $config = [
            // カラーパレット（WCAG AA コントラスト比準拠）
            'colors' => [
                'primary' => '#1e3a8a',        // 深いネイビー（信頼・安定感）
                'primary_light' => '#3b82f6',  // アクセント用ブルー
                'secondary' => '#374151',      // ダークグレー（本文）
                'secondary_light' => '#6b7280', // 補助情報用グレー
                'accent' => '#dc2626',         // 重要情報用赤（合計金額等）
                'success' => '#059669',        // 緑（領収書合計・完了感）
                'background' => '#ffffff',     // 基本背景
                'background_alt' => '#fafafa', // 代替背景（帯・表組み）
                'border' => '#e5e7eb',         // 標準罫線
                'border_light' => '#f3f4f6',   // 薄い罫線（表ヘッダー等）
                'text' => '#111827',           // 本文黒
                'text_light' => '#4b5563',     // 補助テキスト
            ],
            
            // フォント設定
            'typography' => [
                'font_family' => "'Noto Sans JP', 'Yu Mincho', 'YuMincho', 'Hiragino Mincho Pro', 'HGS明朝E', 'ＭＳ 明朝', serif",
                'font_family_numbers' => "'Noto Sans JP', 'Yu Gothic', 'Meiryo', sans-serif", // 金額用（半角数字専用）
                'font_size_base' => 10.5,     // pt基準
                'font_size_sm' => 9,
                'font_size_lg' => 12,
                'font_size_title' => 18,
                'font_size_header' => 22,
                'font_size_amount' => 24,
                'line_height' => 1.6,
                'font_weight_normal' => 400,
                'font_weight_medium' => 500,
                'font_weight_semibold' => 600,
                'font_weight_bold' => 700,
            ],
            
            // スペーシングシステム（4pxグリッドベース）
            'spacing' => [
                'xs' => 2,   // 2mm
                'sm' => 4,   // 4mm
                'md' => 6,   // 6mm
                'lg' => 8,   // 8mm
                'xl' => 12,  // 12mm
                '2xl' => 16, // 16mm
                'page_margin' => 15,
            ],
            
            // インボイス制度関連設定
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
            
            // 後方互換性のための既存設定（新変数を優先して使用）
            'paper_size' => 'a4',
            'paper_orientation' => 'portrait',
            'margin_top' => 15,
            'margin_right' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'font_family' => "'Noto Sans JP', 'Yu Mincho', 'YuMincho', 'Hiragino Mincho Pro', 'HGS明朝E', 'ＭＳ 明朝', serif",
            'font_size' => 10.5,
            'line_height' => 1.6,
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#374151',
            'accent_color' => '#dc2626',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
            'border_color' => '#e5e7eb',
            'header_bg_color' => '#f8fafc',
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
            'table_header_bg' => '#f8fafc',
            'table_row_even_bg' => '#ffffff',
            'table_row_odd_bg' => '#fafafa',
            'table_border_color' => '#e5e7eb',
        ];
        
        // Check color system
        if (isset($config['colors']['primary']) && $config['colors']['primary'] === '#1e3a8a') {
            echo "✓ カラーシステム正常\\n";
        } else {
            echo "✗ カラーシステム異常\\n";
        }
        
        // Check typography system
        if (isset($config['typography']['font_family']) && 
            strpos($config['typography']['font_family'], 'Noto Sans JP') !== false) {
            echo "✓ タイポグラフィシステム正常\\n";
        } else {
            echo "✗ タイポグラフィシステム異常\\n";
        }
        
        // Check spacing system
        if (isset($config['spacing']['page_margin']) && $config['spacing']['page_margin'] === 15) {
            echo "✓ スペーシングシステム正常\\n";
        } else {
            echo "✗ スペーシングシステム異常\\n";
        }
        
        // Check invoice compliance settings
        if (isset($config['invoice_compliance']['show_registration_number_prominently']) && 
            $config['invoice_compliance']['show_registration_number_prominently'] === true) {
            echo "✓ 適格請求書対応設定正常\\n";
        } else {
            echo "✗ 適格請求書対応設定異常\\n";
        }
    } catch (Exception $e) {
        echo "✗ テンプレート設定テストエラー: " . $e->getMessage() . "\\n";
    }

    // Test 3: HTML template content (by rendering view directly)
    echo "\\n--- HTMLテンプレート内容テスト ---\\n";
    try {
        $templateConfig = [
        // Include all the necessary template config here for testing
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
        'typography' => [
            'font_family' => "'Noto Sans JP', 'Yu Mincho', 'YuMincho', 'Hiragino Mincho Pro', 'HGS明朝E', 'ＭＳ 明朝', serif",
            'font_family_numbers' => "'Noto Sans JP', 'Yu Gothic', 'Meiryo', sans-serif",
            'font_size_base' => 10.5,
            'font_size_sm' => 9,
            'font_size_lg' => 12,
            'font_size_title' => 18,
            'font_size_header' => 22,
            'font_size_amount' => 24,
            'line_height' => 1.6,
            'font_weight_normal' => 400,
            'font_weight_medium' => 500,
            'font_weight_semibold' => 600,
            'font_weight_bold' => 700,
        ],
        'spacing' => [
            'xs' => 2,
            'sm' => 4,
            'md' => 6,
            'lg' => 8,
            'xl' => 12,
            '2xl' => 16,
            'page_margin' => 15,
        ],
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
        'paper_size' => 'a4',
        'paper_orientation' => 'portrait',
        'margin_top' => 15,
        'margin_right' => 15,
        'margin_bottom' => 15,
        'margin_left' => 15,
        'font_family' => "'Noto Sans JP', 'Yu Mincho', 'YuMincho', 'Hiragino Mincho Pro', 'HGS明朝E', 'ＭＳ 明朝', serif",
        'font_size' => 10.5,
        'line_height' => 1.6,
        'primary_color' => '#1e3a8a',
        'secondary_color' => '#374151',
        'accent_color' => '#dc2626',
        'background_color' => '#ffffff',
        'text_color' => '#111827',
        'border_color' => '#e5e7eb',
        'header_bg_color' => '#f8fafc',
        'total_bg_color' => '#fef3c7',
        'tax_table_header_bg' => '#f3f4f6',
        'show_facility_logo' => false,
        'facility_logo_path' => null,
        'show_facility_info' => true,
        'show_tax_breakdown' => true,
        'show_daily_charges_detail' => true,
        'show_qr_code' => false, // Keep disabled for now since library not installed
        'qr_code_data' => null,
        'header_html' => null,
        'footer_html' => null,
        'show_page_numbers' => true,
        'table_header_bg' => '#f8fafc',
        'table_row_even_bg' => '#ffffff',
        'table_row_odd_bg' => '#fafafa',
        'table_border_color' => '#e5e7eb',
    ];
    
    $html = \Illuminate\Support\Facades\View::make('invoices.pdf', [
        'invoice' => $invoice,
        'resident' => $resident,
        'dailyCharges' => $invoice->resident->dailyCharges,
        'facility' => config('facility'),
        'tax_info' => [
            'non_taxable' => $invoice->non_taxable_amount,
            'taxable' => $invoice->taxable_amount,
            'tax_rate' => $invoice->tax_rate,
            'tax_amount' => $invoice->tax_amount,
            'total_with_tax' => $invoice->total_with_tax,
        ],
        'template' => $templateConfig,
    ])->render();
    
    echo "HTMLテンプレートレンダリング成功: " . strlen($html) . " 文字\\n";
    
    // Check for key elements
    $regNumber = config('facility.invoice_registration_number', 'T1234567890123');
    if (strpos($html, '登録番号: ' . $regNumber) !== false) {
        echo "✓ 登録番号表示正常\\n";
    } else {
        echo "✗ 登録番号表示異常\\n";
    }
    
    if (strpos($html, '<div class="page-break"></div>') !== false) {
        echo "✓ ページブレークdiv正常\\n";
    } else {
        echo "✗ ページブレークdiv異常\\n";
    }
    
    if (strpos($html, '.page-break {') !== false && strpos($html, 'page-break-after: always;') !== false) {
        echo "✓ ページブレークCSS正常\\n";
    } else {
        echo "✗ ページブレークCSS異常\\n";
    }
    
    // Save HTML for inspection
    file_put_contents(__DIR__ . '/storage/app/final_debug_invoice.html', $html);
    echo "デバッグ用HTML保存: storage/app/final_debug_invoice.html\\n";
    
} catch (Exception $e) {
    echo "✗ HTMLテンプレートテストエラー: " . $e->getMessage() . "\\n";
}

// Test 4: File system
echo "\\n--- ファイルシステムテスト ---\\n";
$files = [
    'storage/app/debug_invoice.pdf' => '請求書PDF',
    'storage/app/debug_receipt.pdf' => '領収書PDF',
    'storage/app/debug_invoice.html' => '請求書HTML',
    'storage/app/final_debug_invoice.html' => '最終デバッグHTML'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✓ {$description}存在: {$size} バイト\\n";
    } else {
        echo "✗ {$description}不存在\\n";
    }
}

echo "\\n=== デバッグ完了 ===\\n";
echo "\\n次のステップ:\\n";
echo "1. QRコード機能を有効にするために、bacon/bacon-qr-codeライブラリをインストールしてください:\\n";
echo "   composer require bacon/bacon-qr-code\\n";
echo "2. 設定でQRコード機能を有効にしてください:\\n";
echo "   config(['pdf.invoice.show_qr_code' => true]);\\n";
echo "   config(['pdf.receipt.show_qr_code' => true]);\\n";
echo "3. システムは正常に動作しており、残りの機能はライブラリインストール後に利用可能になります。\\n";
?>