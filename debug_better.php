<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\Storage;

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

echo "Testing with resident: {$resident->name} (Room {$resident->room_number})\\n";
echo "Invoice for: {$invoice->billing_year_month}\\n";
echo "Amount: ¥" . number_format($invoice->rent_subtotal + $invoice->management_fee_subtotal + $invoice->service_subtotal) . "\\n";

$pdfService = new InvoicePdfService();

// Test 1: Basic invoice generation
echo "\\n=== Testing Invoice Generation ===\\n";
try {
    $pdf = $pdfService->generateInvoicePdf($invoice);
    $size = strlen($pdf->output());
    echo "✓ Invoice PDF generated: {$size} bytes\\n";
    
    // Save to file for inspection
    Storage::put('app/debug_invoice.pdf', $pdf->output());
    echo "  Saved to: storage/app/debug_invoice.pdf\\n";
} catch (Exception $e) {
    echo "✗ Error generating invoice PDF: " . $e->getMessage() . "\\n";
}

// Test 2: Receipt generation (after payment)
echo "\\n=== Testing Receipt Generation ===\\n";
$invoiceCopy = clone $invoice;
$invoiceCopy->markAsPaid(PaymentMethod::BankTransfer);
try {
    $pdf = $pdfService->generateReceiptPdf($invoiceCopy);
    $size = strlen($pdf->output());
    echo "✓ Receipt PDF generated: {$size} bytes\\n";
    
    // Save to file for inspection
    Storage::put('app/debug_receipt.pdf', $pdf->output());
    echo "  Saved to: storage/app/debug_receipt.pdf\\n";
} catch (Exception $e) {
    echo "✗ Error generating receipt PDF: " . $e->getMessage() . "\\n";
}

// Test 3: Check if QR code functionality is in place by enabling it temporarily
echo "\\n=== Testing QR Code Functionality ==\\n";
try {
    // Enable QR code temporarily
    config(['pdf.invoice.show_qr_code' => true]);
    $pdf = $pdfService->generateInvoicePdf($invoice);
    $content = $pdf->output();
    
    if (strpos($content, 'data:image/svg+xml;base64,') !== false) {
        echo "✓ QR code data found in invoice PDF\\n";
    } else {
        echo "✗ QR code data NOT found in invoice PDF\\n";
    }
    
    // Reset config
    config(['pdf.invoice.show_qr_code' => false]);
} catch (Exception $e) {
    echo "✗ Error testing QR code: " . $e->getMessage() . "\\n";
}

// Test 4: Check registration number display
echo "\\n=== Testing Registration Number Display ===\\n";
try {
    $pdf = $pdfService->generateInvoicePdf($invoice);
    $content = $pdf->output();
    
    $regNumber = config('facility.invoice_registration_number', 'T1234567890123');
    if (strpos($content, '登録番号: ' . $regNumber) !== false) {
        echo "✓ Registration number found in invoice PDF\\n";
    } else {
        echo "✗ Registration number NOT found in invoice PDF\\n";
    }
} catch (Exception $e) {
    echo "✗ Error testing registration number: " . $e->getMessage() . "\\n";
}

// Test 5: Check page break functionality by looking for the page-break div
echo "\\n=== Testing Page Break Functionality ===\\n";
try {
    $pdf = $pdfService->generateInvoicePdf($invoice);
    $content = $pdf->output();
    
    // Look for the page-break div we added
    if (strpos($content, '<div class="page-break"></div>') !== false) {
        echo "✓ Page break div found in invoice PDF\\n";
    } else {
        echo "✗ Page break div NOT found in invoice PDF\\n";
    }
    
    // Also check for the CSS definition
    if (strpos($content, '.page-break {') !== false && strpos($content, 'page-break-after: always;') !== false) {
        echo "✓ Page break CSS found in invoice PDF\\n";
    } else {
        echo "✗ Page break CSS NOT found in invoice PDF\\n";
    }
} catch (Exception $e) {
    echo "✗ Error testing page break: " . $e->getMessage() . "\\n";
}

echo "\\n=== Debug Complete ===\\n";
echo "Note: For PDF files, searching for HTML/CSS in the binary output won't work.\\n";
echo "The PDFs have been generated and saved to storage/app/ for inspection.\\n";
?>