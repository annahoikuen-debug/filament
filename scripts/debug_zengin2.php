<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Illuminate\Database\Eloquent\Model::unguard();

$f = App\Models\Facility::factory()->create();
$r = App\Models\Resident::create(['facility_id' => $f->id, 'room_number' => '101', 'name' => '山田太郎', 'name_kana' => 'ヤマダタロウ', 'base_rent' => 100000, 'base_management_fee' => 20000, 'status' => 'active']);
$a = App\Models\ResidentBankAccount::create(['resident_id' => $r->id, 'bank_code' => '0001', 'branch_code' => '001', 'account_type' => 1, 'account_number' => '7654321', 'account_holder_kana' => 'ヤマダタロウ', 'is_active' => true]);
$inv = App\Models\MonthlyInvoice::create(['resident_id' => $r->id, 'facility_id' => $f->id, 'billing_year_month' => '2026-10', 'rent_subtotal' => 100000, 'management_fee_subtotal' => 20000, 'service_subtotal' => 0, 'status' => 'unbilled', 'payment_method' => 'direct_debit', 'tax_rate' => 10]);

$s = new App\Services\Banking\ZenginFormatService();
$c = $s->generate([
    'delegate_code' => '1234567890', 'delegate_name_kana' => 'テストシセツ', 'transfer_date' => '20261027',
    'bank_code' => '0001', 'bank_name_kana' => 'テストギンコウ', 'branch_code' => '001', 'branch_name_kana' => 'ホンテン',
    'account_type' => '1', 'account_number' => '1234567',
], [['invoice' => $inv, 'account' => $a]]);

$ls = explode("\r\n", mb_convert_encoding($c, 'UTF-8', 'CP932'));
echo 'AMOUNT ['.substr($ls[1], 84, 10).']'.PHP_EOL;
echo 'HOLDER ['.rtrim(substr($ls[1], 47, 30)).']'.PHP_EOL;
echo 'TAXTOTAL: '.$inv->total_with_tax.PHP_EOL;

// 結果ファイルパースのデバッグ
$amount = (string) $inv->total_with_tax;
$rid = (string) $r->id;
$line = '2'.str_repeat('0', 21).str_pad($amount, 10, '0', STR_PAD_LEFT).str_pad($rid, 10, '0', STR_PAD_LEFT).'0'.str_repeat(' ', 55);
$content = mb_convert_encoding(implode("\r\n", [$line])."\r\n", 'CP932', 'UTF-8');
$parser = new App\Services\Banking\ZenginResultParser;
var_dump($parser->parse($content));
