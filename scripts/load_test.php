<?php

ini_set('memory_limit', '512M');

require_once __DIR__ . '/../vendor/autoload.php';

// Clean up existing test data before boot
$dbPath = __DIR__ . '/../database/database.sqlite';
if (file_exists($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->exec('DELETE FROM daily_charges');
    $pdo->exec('DELETE FROM monthly_invoices');
    $pdo->exec('DELETE FROM residents');
    $pdo->exec('DELETE FROM charge_items');
    $pdo->exec('DELETE FROM facilities');
    $pdo->exec("DELETE FROM users WHERE email LIKE 'loadtest%@example.jp'");
}

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Facility;
use App\Models\Resident;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceCsvExportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "=== Load Test for Facility Billing System ===\n\n";

// Configuration
$facilityCount = 10;
$residentsPerFacility = 100;
$chargeItemsCount = 20;
$monthsOfDailyCharges = 6;
$monthsOfInvoices = 6;

echo "Configuration:\n";
echo "  Facilities: {$facilityCount}\n";
echo "  Residents per facility: {$residentsPerFacility}\n";
echo "  Total residents: " . ($facilityCount * $residentsPerFacility) . "\n";
echo "  Charge items: {$chargeItemsCount}\n";
echo "  Months of daily charges: {$monthsOfDailyCharges}\n";
echo "  Months of invoices: {$monthsOfInvoices}\n\n";

// Ensure clean state
Facility::truncate();
User::where('email', 'like', 'loadtest%@example.jp')->delete();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
} catch (\Exception $e) {
    // SQLite doesn't support this, ignore
}

// Create facilities
echo "Creating {$facilityCount} facilities...\n";
$startTime = microtime(true);
$facilities = [];
for ($i = 1; $i <= $facilityCount; $i++) {
    $facilities[] = Facility::create([
        'name' => "雋闕ｷ繝・せ繝域命險ｭ {$i}",
        'operator' => "雋闕ｷ繝・せ繝磯°蝟ｶ豕穂ｺｺ {$i}",
        'postal_code' => sprintf('%03d-%04d', rand(100, 999), rand(1000, 9999)),
        'address' => "譚ｱ莠ｬ驛ｽ繝・せ繝亥玄雋闕ｷ逕ｺ {$i}-{$i}-{$i}",
        'phone' => '03-' . rand(1000, 9999) . '-' . rand(1000, 9999),
        'fax' => '03-' . rand(1000, 9999) . '-' . rand(1000, 9999),
        'email' => "loadtest{$i}@example.jp",
        'invoice_registration_number' => 'T' . str_pad($i, 13, '0', STR_PAD_LEFT),
        'bank' => [
            'name' => '繝・せ繝磯橿陦・',
            'branch_name' => "繝・せ繝域髪蠎・{$i}",
            'account_type' => '譎ｮ騾・',
            'account_number' => str_pad($i, 7, '0', STR_PAD_LEFT),
            'account_holder' => "繝・せ繝域命險ｭ{$i}",
        ],
        'billing' => [
            'direct_debit_day' => rand(25, 27),
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
    ]);
}
$elapsed = microtime(true) - $startTime;
echo "  Done in {$elapsed}s\n";

// Create facility admin users
echo "Creating facility admin users...\n";
foreach ($facilities as $index => $facility) {
    User::create([
        'name' => "{$facility->name} 邂｡逅・・",
        'email' => "loadtest_facility{$index}@example.jp",
        'password' => Hash::make('password'),
        'is_admin' => true,
        'role' => 'facility_admin',
        'facility_id' => $facility->id,
    ]);
}

// Create charge items
echo "Creating {$chargeItemsCount} charge items...\n";
$startTime = microtime(true);
$chargeItems = [];
$itemNames = [
    '邏吶♀繧縺､ (繝代Φ繝・ち繧､繝・', '蟆ｿ縺ｨ繧翫ヱ繝・ラ', '逅・ｾ主ｮｹ莉｣ (繧ｫ繝・ヨ)', '逅・ｾ主ｮｹ莉｣ (繧ｫ繝ｩ繝ｼ繝ｻ繝代・繝・',
    '蜿苓ｨｺ莉倥″豺ｻ縺・ｲｻ (30蛻・', '蛟句挨豢玲ｿｯ莉｣陦・(1蝗・', '譌･逕ｨ蜩√・蝸懷･ｽ蜩∫ｫ区崛驥・',
    '繝ｪ繝上ン繝ｪ逕ｨ蜩・, '莉玖ｭｷ逕ｨ繝吶ャ繝峨Ξ繝ｳ繧ｿ繝ｫ', '霆頑､・ｭ舌Ξ繝ｳ繧ｿ繝ｫ',
    '隕句ｮ医ｊ繧ｻ繝ｳ繧ｵ繝ｼ繝ｬ繝ｳ繧ｿ繝ｫ', '蜈･豬ｴ莉句勧逕ｨ蜩・, '鬟滉ｺ倶ｻ句勧逕ｨ蜩・, '蜿｣閻斐こ繧｢逕ｨ蜩・,
    '隍･逖｡繧ｱ繧｢逕ｨ蜩・, '謗呈ｳ・こ繧｢逕ｨ蜩・, '遘ｻ蜍穂ｻ句勧逕ｨ蜩・, '繝ｬ繧ｯ繝ｪ繧ｨ繝ｼ繧ｷ繝ｧ繝ｳ逕ｨ蜩・,
    '邱頑･蜻ｼ縺ｳ蜃ｺ縺励す繧ｹ繝・Β', '蛛･蠎ｷ邂｡逅・ｩ溷勣繝ｬ繝ｳ繧ｿ繝ｫ',
];
for ($i = 0; $i < $chargeItemsCount; $i++) {
    $chargeItems[] = ChargeItem::create([
        'name' => $itemNames[$i % count($itemNames)] . " 繝舌Μ繧ｨ繝ｼ繧ｷ繝ｧ繝ｳ" . ($i + 1),
        'default_price' => rand(50, 5000),
    ]);
}
$elapsed = microtime(true) - $startTime;
echo "  Done in {$elapsed}s\n";

// Create residents
echo "Creating residents ({$facilityCount} facilities x {$residentsPerFacility} = " . ($facilityCount * $residentsPerFacility) . " residents)...\n";
$startTime = microtime(true);
$allResidents = [];
$baseDate = '2024-01-01';
$batchSize = 100;

for ($f = 0; $f < $facilityCount; $f++) {
    $facility = $facilities[$f];
    $residentsToCreate = [];
    
    for ($r = 1; $r <= $residentsPerFacility; $r++) {
        $roomNumber = sprintf('%d%03d', $f + 1, $r);
        $residentsToCreate[] = [
            'facility_id' => $facility->id,
            'room_number' => $roomNumber,
            'name' => "雋闕ｷ繝・せ繝亥・螻・・{$roomNumber}",
            'name_kana' => "繝輔き繝・せ繝医ル繝･繧ｦ繧ｭ繝ｧ繧ｷ繝｣ {$roomNumber}",
            'base_rent' => rand(50000, 80000),
            'base_management_fee' => rand(20000, 40000),
            'status' => \App\Enums\ResidentStatus::Active,
            'move_in_date' => $baseDate,
            'move_out_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    
    // Batch insert
    foreach (array_chunk($residentsToCreate, $batchSize) as $chunk) {
        Resident::insert($chunk);
    }
    
    // Fetch created residents for this facility
    $allResidents = array_merge($allResidents, Resident::where('facility_id', $facility->id)->get()->toArray());
}
$elapsed = microtime(true) - $startTime;
echo "  Done in {$elapsed}s (" . number_format(($facilityCount * $residentsPerFacility) / $elapsed, 1) . " residents/sec)\n";

// Create daily charges
echo "Creating daily charges for {$monthsOfDailyCharges} months...\n";
$startTime = microtime(true);
$totalDailyCharges = 0;

for ($monthOffset = 0; $monthOffset < $monthsOfDailyCharges; $monthOffset++) {
    $targetMonth = Carbon::now()->subMonths($monthOffset);
    $daysInMonth = $targetMonth->daysInMonth;
    $monthStart = $targetMonth->copy()->startOfMonth();
    
    $dailyChargesToCreate = [];
    
    foreach ($allResidents as $index => $resident) {
        // Each resident gets 3-5 charge items per month
        $itemsPerResident = rand(3, 5);
        $selectedItems = array_rand($chargeItems, $itemsPerResident);
        if (!is_array($selectedItems)) {
            $selectedItems = [$selectedItems];
        }
        
        foreach ($selectedItems as $itemIndex) {
            $chargeItem = $chargeItems[$itemIndex];
            $recordCount = rand(1, 3);
            
            for ($rec = 0; $rec < $recordCount; $rec++) {
                $dailyChargesToCreate[] = [
                    'resident_id' => $resident['id'],
                    'charge_item_id' => $chargeItem->id,
                    'facility_id' => $resident['facility_id'],
                    'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
                    'unit_price' => $chargeItem->default_price,
                    'quantity' => rand(1, 10),
                    'note' => '雋闕ｷ繝・せ繝医ョ繝ｼ繧ｿ',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $totalDailyCharges++;
        }
    }
    
    // Batch insert daily charges
    foreach (array_chunk($dailyChargesToCreate, 500) as $chunk) {
        DailyCharge::insert($chunk);
    }
    
    if (($monthOffset + 1) % 2 === 0 || $monthOffset === $monthsOfDailyCharges - 1) {
        echo "  Month " . ($monthOffset + 1) . "/{$monthsOfDailyCharges} done\n";
    }
}

$elapsed = microtime(true) - $startTime;
echo "  Total daily charges created: " . number_format($totalDailyCharges) . "\n";
echo "  Done in {$elapsed}s (" . number_format($totalDailyCharges / $elapsed, 1) . " records/sec)\n";

// Test 1: Invoice generation performance
echo "\n=== Test 1: Invoice Generation Performance ===\n";
$service = new InvoiceCalculationService();
$startTime = microtime(true);

for ($monthOffset = 0; $monthOffset < $monthsOfInvoices; $monthOffset++) {
    $targetMonth = Carbon::now()->subMonths($monthOffset)->format('Y-m');
    
    foreach ($facilities as $facility) {
        $monthStartTime = microtime(true);
        $service->generateForMonth($targetMonth, false, $facility->id);
        $monthElapsed = microtime(true) - $monthStartTime;
        echo "  {$facility->name} - {$targetMonth}: {$monthElapsed}s\n";
    }
}

$totalInvoiceTime = microtime(true) - $startTime;
$totalInvoices = MonthlyInvoice::count();
echo "  Total invoices generated: " . number_format($totalInvoices) . "\n";
echo "  Total time: {$totalInvoiceTime}s\n";
echo "  Average per facility/month: " . number_format($totalInvoiceTime / ($facilityCount * $monthsOfInvoices), 3) . "s\n";
echo "  Invoices per second: " . number_format($totalInvoices / $totalInvoiceTime, 1) . "\n";

// Test 2: Force update (regeneration) performance
echo "\n=== Test 2: Force Update (Regeneration) Performance ===\n";
$startTime = microtime(true);

$targetMonth = Carbon::now()->format('Y-m');
foreach ($facilities as $facility) {
    $monthStartTime = microtime(true);
    $service->generateForMonth($targetMonth, true, $facility->id);
    $monthElapsed = microtime(true) - $monthStartTime;
    echo "  {$facility->name} - {$targetMonth} (force): {$monthElapsed}s\n";
}

$forceUpdateTime = microtime(true) - $startTime;
echo "  Total force update time: {$forceUpdateTime}s\n";

// Test 3: PDF Generation Performance
echo "\n=== Test 3: PDF Generation Performance ===\n";
$pdfService = new InvoicePdfService();
$invoices = MonthlyInvoice::where('billing_year_month', $targetMonth)->take(100)->get();

echo "Generating PDFs for 100 invoices...\n";
$startTime = microtime(true);
$successCount = 0;

foreach ($invoices as $invoice) {
    try {
        $pdfService->generateInvoicePdf($invoice);
        $successCount++;
    } catch (\Exception $e) {
        echo "  Error for invoice {$invoice->id}: " . $e->getMessage() . "\n";
    }
}

$pdfTime = microtime(true) - $startTime;
echo "  Successful: {$successCount}/100\n";
echo "  Total time: {$pdfTime}s\n";
echo "  Average per PDF: " . number_format($pdfTime / $successCount * 1000, 1) . "ms\n";

// Test 4: ZIP Archive Generation
echo "\n=== Test 4: ZIP Archive Generation ===\n";
$startTime = microtime(true);
try {
    $zipPath = $pdfService->generateMonthlyZip($targetMonth, $facilities[0]->id);
    $zipTime = microtime(true) - $startTime;
    $zipSize = file_exists($zipPath) ? filesize($zipPath) : 0;
    echo "  ZIP generated: {$zipPath}\n";
    echo "  Size: " . number_format($zipSize / 1024 / 1024, 2) . " MB\n";
    echo "  Time: {$zipTime}s\n";
    @unlink($zipPath);
} catch (\Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

// Test 5: CSV Export Performance
echo "\n=== Test 5: CSV Export Performance ===\n";
$csvService = new InvoiceCsvExportService();
$startTime = microtime(true);
$csv = $csvService->exportMonthlyListCsv($targetMonth, $facilities[0]->id);
$csvTime = microtime(true) - $startTime;
echo "  Monthly List CSV: " . strlen($csv) . " bytes in {$csvTime}s\n";

$startTime = microtime(true);
$csv = $csvService->exportAccountingJournalCsv($targetMonth, $facilities[0]->id, 'freee');
$csvTime = microtime(true) - $startTime;
echo "  Accounting Journal CSV (freee): " . strlen($csv) . " bytes in {$csvTime}s\n";

// Test 6: Database Query Performance
echo "\n=== Test 6: Database Query Performance ===\n";

// Query 1: Residents with invoices
$startTime = microtime(true);
$count = Resident::whereHas('monthlyInvoices', function ($q) use ($targetMonth) {
    $q->where('billing_year_month', $targetMonth);
})->count();
echo "  Residents with invoices query: " . number_format((microtime(true) - $startTime) * 1000, 2) . "ms (count: {$count})\n";

// Query 2: Daily charges aggregation
$startTime = microtime(true);
$sum = DailyCharge::where('billing_year_month', $targetMonth)->sum(DB::raw('unit_price * quantity'));
echo "  Daily charges sum query: " . number_format((microtime(true) - $startTime) * 1000, 2) . "ms (sum: " . number_format($sum) . ")\n";

// Query 3: Monthly invoices with resident
$startTime = microtime(true);
$invoices = MonthlyInvoice::with('resident')->where('billing_year_month', $targetMonth)->get();
echo "  Monthly invoices with resident: " . number_format((microtime(true) - $startTime) * 1000, 2) . "ms (count: {$invoices->count()})\n";

// Test 7: Concurrent Simulation
echo "\n=== Test 7: Concurrent Access Simulation ===\n";
$startTime = microtime(true);
$concurrentFacilities = array_slice($facilities, 0, 5);
$concurrentMonth = Carbon::now()->subMonth()->format('Y-m');

foreach ($concurrentFacilities as $facility) {
    $service->generateForMonth($concurrentMonth, true, $facility->id);
}
$concurrentTime = microtime(true) - $startTime;
echo "  5 facilities concurrent simulation: {$concurrentTime}s\n";

// Summary
echo "\n=== LOAD TEST SUMMARY ===\n";
echo "Facilities: {$facilityCount}\n";
echo "Total Residents: " . number_format($facilityCount * $residentsPerFacility) . "\n";
echo "Total Daily Charges: " . number_format(DailyCharge::count()) . "\n";
echo "Total Monthly Invoices: " . number_format(MonthlyInvoice::count()) . "\n";
echo "\nPerformance Metrics:\n";
echo "  Invoice Generation (per facility/month): " . number_format($totalInvoiceTime / ($facilityCount * $monthsOfInvoices), 3) . "s\n";
echo "  Force Update (per facility): " . number_format($forceUpdateTime / $facilityCount, 3) . "s\n";
echo "  PDF Generation (per invoice): " . number_format($pdfTime / $successCount * 1000, 1) . "ms\n";
echo "  ZIP Generation (1 facility): " . number_format($zipTime, 3) . "s\n";
echo "  CSV Export: " . number_format($csvTime * 1000, 1) . "ms\n";
echo "  Concurrent (5 facilities): {$concurrentTime}s\n";

// Memory usage
echo "\nMemory Usage:\n";
echo "  Peak: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "  Current: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";

echo "\n=== Load Test Complete ===\n";

