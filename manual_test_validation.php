<?php
/**
 * Manual test for InvoicePdfService validation
 */

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\Facility;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;

// Create test data
$facility = Facility::create([
    'name' => 'テスト施設',
    'invoice_registration_number' => 'T1234567890123',
]);

$resident = Resident::create([
    'room_number' => '101',
    'name' => 'PDFテスト入居者',
    'move_in_date' => '2026-01-01',
]);

$invoice = MonthlyInvoice::create([
    'billing_year_month' => '2026-10',
    'resident_id' => $resident->id,
    'facility_id' => $facility->id,
    'rent_subtotal' => 65000,
    'management_fee_subtotal' => 30000,
    'service_subtotal' => 3000,
    'status' => InvoiceStatus::Unbilled,
    'tax_breakdown' => [
        'standard' => [
            'taxable_amount' => 30000,
            'tax_amount' => 3000,
        ],
        'reduced' => [
            'taxable_amount' => 3000,
            'tax_amount' => 240,
        ],
    ],
]);

$service = app(InvoicePdfService::class);

// Test 1: Valid data should work
echo "Test 1: Valid data\n";
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✓ Success: Valid data passed validation\n";
} catch (\Exception $e) {
    echo "✗ Failed: Valid data threw exception: ".$e->getMessage()."\n";
}

// Test 2: Missing registration number
echo "\nTest 2: Missing registration number\n";
$facility->update(['invoice_registration_number' => null]);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for missing registration number\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

// Test 3: Invalid registration number format
echo "\nTest 3: Invalid registration number format\n";
$facility->update(['invoice_registration_number' => 'INVALID']);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for invalid format\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

// Test 4: Missing tax breakdown
echo "\nTest 4: Missing tax breakdown\n";
$facility->update(['invoice_registration_number' => 'T1234567890123']); // Reset to valid
$invoice->update(['tax_breakdown' => null]);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for missing tax breakdown\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

// Test 5: Missing standard tax breakdown
echo "\nTest 5: Missing standard tax breakdown\n";
$invoice->update([
    'tax_breakdown' => [
        'reduced' => [
            'taxable_amount' => 3000,
            'tax_amount' => 240,
        ],
    ],
]);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for missing standard breakdown\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

// Test 6: Missing reduced tax breakdown
echo "\nTest 6: Missing reduced tax breakdown\n";
$invoice->update([
    'tax_breakdown' => [
        'standard' => [
            'taxable_amount' => 30000,
            'tax_amount' => 3000,
        ],
    ],
]);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for missing reduced breakdown\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

// Test 7: Missing taxable amount in standard
echo "\nTest 7: Missing taxable amount in standard\n";
$invoice->update([
    'tax_breakdown' => [
        'standard' => [
            'tax_amount' => 3000,
            // missing taxable_amount
        ],
        'reduced' => [
            'taxable_amount' => 3000,
            'tax_amount' => 240,
        ],
    ],
]);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for missing taxable amount\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

// Test 8: Missing tax amount in standard
echo "\nTest 8: Missing tax amount in standard\n";
$invoice->update([
    'tax_breakdown' => [
        'standard' => [
            'taxable_amount' => 30000,
            // missing tax_amount
        ],
        'reduced' => [
            'taxable_amount' => 3000,
            'tax_amount' => 240,
        ],
    ],
]);
try {
    $pdf = $service->generateInvoicePdf($invoice);
    echo "✗ Failed: Should have thrown exception for missing tax amount\n";
} catch (\Exception $e) {
    echo "✓ Success: Correctly threw exception: ".$e->getMessage()."\n";
}

echo "\nAll manual tests completed.\n";