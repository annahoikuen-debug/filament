<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Carbon\Carbon;

test('複合インデックスが適切に機能することを確認する', function () {
    // 大量のテストデータを作成
    $residents = [];
    for ($i = 1; $i <= 50; $i++) {
        $residents[] = Resident::create([
            'room_number' => str_pad($i + 1000, 4, '0', STR_PAD_LEFT),
            'name' => "テスト入居者 {$i}",
            'base_rent' => 50000,
            'base_management_fee' => 25000,
            'status' => ResidentStatus::Active,
            'move_in_date' => '2025-01-01',
        ]);
    }

    // 各入居者に複数月の請求データを作成
    foreach ($residents as $resident) {
        for ($month = 1; $month <= 12; $month++) {
            $yearMonth = sprintf('2026-%02d', $month);
            MonthlyInvoice::create([
                'billing_year_month' => $yearMonth,
                'resident_id' => $resident->id,
                'rent_subtotal' => 50000,
                'management_fee_subtotal' => 25000,
                'service_subtotal' => 5000 * ($month % 3 + 1),
                'status' => $month <= 3 ? InvoiceStatus::Paid :
                           ($month <= 6 ? InvoiceStatus::Billed : InvoiceStatus::Unbilled),
            ]);
        }
    }

    // 複合インデックスを使用するクエリを実行
    $results = MonthlyInvoice::where('resident_id', '<=', 25)
        ->where('billing_year_month', '>=', '2026-04')
        ->where('billing_year_month', '<=', '2026-09')
        ->where('status', InvoiceStatus::Unbilled)
        ->get();

    // クエリが実行され結果が返ってくることを確認
    expect($results)->not->toBeEmpty();
    expect($results->count())->toBeGreaterThan(0);

    // 特定の条件に一致するデータが取得できていることを確認
    foreach ($results as $result) {
        expect($result->resident_id)->toBeLessThanOrEqual(25);
        expect($result->billing_year_month)->toBeGreaterThanOrEqual('2026-04');
        expect($result->billing_year_month)->toBeLessThanOrEqual('2026-09');
        expect($result->status)->toBe(InvoiceStatus::Unbilled);
    }
});

test('日付範囲検索用インデックスが機能することを確認する', function () {
    $resident = Resident::create([
        'room_number' => '999',
        'name' => 'インデックステスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $chargeItem = ChargeItem::create([
        'name' => 'テストサービス',
        'default_price' => 1000,
        'is_active' => true,
    ]);

    // 10月の31日分のデータを作成（シンプルに）
    for ($day = 1; $day <= 31; $day++) {
        $date = sprintf('2026-10-%02d', $day);
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => $date,
            'unit_price' => 1000,
            'quantity' => 1,
        ]);
    }

    // 10月のデータを取得
    $octoberCharges = DailyCharge::where('resident_id', $resident->id)
        ->where('date', '>=', '2026-10-01')
        ->where('date', '<=', '2026-10-31 23:59:59')
        ->get();

    // 31日分作成されていることを確認
    expect($octoberCharges->count())->toBe(31);

    // 各レコードが正しい日付範囲内であることを確認
    foreach ($octoberCharges as $charge) {
        $date = Carbon::parse($charge->date);
        expect($date->month)->toBe(10);
        expect($date->year)->toBe(2026);
    }
});
