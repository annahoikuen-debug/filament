<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use App\Services\FacilityConfigService;
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
}

echo "Testing QR code generation...\n";

$pdfService = app(InvoicePdfService::class);
$configService = app(FacilityConfigService::class);

// Test the QR code generation methods directly
echo "\n=== Testing QR Code Generation Methods ===\n";
try {
    // Test generatePaymentQrCode
    $testData = "TEST DATA";
    $qrCode = $pdfService->generatePaymentQrCode($testData);
    if (!empty($qrCode) && strpos($qrCode, 'data:image/svg+xml;base64,') === 0) {
        echo "✓ generatePaymentQrCode works correctly\n";
    } else {
        echo "✗ generatePaymentQrCode failed: " . var_export($qrCode, true) . "\n";
    }
    
    // Test getBankTransferQrCodeData
    $facilityConfig = $configService->getConfig();
    $bankData = $pdfService->getBankTransferQrCodeData($invoice, $facilityConfig);
    if (!empty($bankData)) {
        echo "✓ getBankTransferQrCodeData works correctly\n";
        echo "  Data length: " . strlen($bankData) . "\n";
    } else {
        echo "✗ getBankTransferQrCodeData failed: " . var_export($bankData, true) . "\n";
    }
    
    // Test getReceiptVerificationQrCodeData
    // First mark as paid
    $invoiceCopy = clone $invoice;
    $invoiceCopy->markAsPaid(PaymentMethod::BankTransfer);
    $receiptData = $pdfService->getReceiptVerificationQrCodeData($invoiceCopy);
    if (!empty($receiptData)) {
        echo "✓ getReceiptVerificationQrCodeData works correctly\n";
        echo "  Data length: " . strlen($receiptData) . "\n";
    } else {
        echo "✗ getReceiptVerificationQrCodeData failed: " . var_export($receiptData, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error testing QR code methods: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Test QR code integration in PDF generation
echo "\n=== Testing QR Code Integration in PDF ===\n";
try {
    // Enable QR code
    config(['pdf.invoice.show_qr_code' => true]);
    
    $facilityConfig = $configService->getConfig();
    $pdf = $pdfService->generateInvoicePdf($invoice, $facilityConfig);
    $content = $pdf->output();
    
    if (strpos($content, 'data:image/svg+xml;base64,') !== false) {
        echo "✓ QR code data found in generated PDF\n";
    } else {
        echo "✗ QR code data NOT found in generated PDF\n";
    }
    
    // Reset config
    config(['pdf.invoice.show_qr_code' => false]);
    
} catch (Exception $e) {
    echo "✗ Error testing QR code in PDF: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== QR Code Debug Complete ===\n";
?>