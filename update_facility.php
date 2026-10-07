<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$f = \App\Models\Facility::first();
if (!$f) {
    $f = \App\Models\Facility::create([
        'name' => 'デフォルト施設',
        'operator' => 'デフォルト運営',
        'postal_code' => '123-4567',
        'address' => '東京都',
        'phone' => '03-1234-5678',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => true,
    ]);
}

echo "Facility ID: {$f->id}\n";

\App\Models\Resident::whereNull('facility_id')->update(['facility_id' => $f->id]);
\App\Models\MonthlyInvoice::whereNull('facility_id')->update(['facility_id' => $f->id]);
\App\Models\DailyCharge::whereNull('facility_id')->update(['facility_id' => $f->id]);

echo "Updated residents: " . \App\Models\Resident::where('facility_id', $f->id)->count() . "\n";
echo "Updated invoices: " . \App\Models\MonthlyInvoice::where('facility_id', $f->id)->count() . "\n";
echo "Updated charges: " . \App\Models\DailyCharge::where('facility_id', $f->id)->count() . "\n";