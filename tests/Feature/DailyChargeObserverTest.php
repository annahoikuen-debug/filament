<?php

namespace Tests\Feature;

use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ChargeItem;
use App\Enums\InvoiceStatus;
use App\Notifications\DailyChargeAddedToBilledInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DailyChargeObserverTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 未請求月へのDailyCharge追加でインボイスが自動再計算される()
    {
        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '301',
            'name' => '自動再計算テスト入居者',
            'base_rent' => 60000,
            'base_management_fee' => 25000,
            'move_in_date' => '2026-01-01',
        ]);

        // 未請求のMonthlyInvoiceを作成（初期状態）
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 60000,
            'management_fee_subtotal' => 25000,
            'service_subtotal' => 0,
            'total_amount' => 85000, // 初期値
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '自動再計算テスト品目',
            'default_price' => 1200,
        ]);

        // 初期状態を確認
        $this->assertEquals(0, $invoice->fresh()->service_subtotal);
        $this->assertEquals(85000, $invoice->fresh()->total_amount);

        // DailyChargeを追加
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-10',
            'unit_price' => 1200,
            'quantity' => 4,
        ]);

        // Observerによって自動再計算が実行され、サービス小計が更新されていることを確認
        $updatedInvoice = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(4800, $updatedInvoice->service_subtotal); // 1200 * 4
        $this->assertEquals(89800, $updatedInvoice->total_amount); // 60000 + 25000 + 4800
    }

    /** @test */
    public function 未請求月へのDailyCharge更新でインボイスが自動再計算される()
    {
        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '302',
            'name' => '更新テスト入居者',
            'base_rent' => 55000,
            'base_management_fee' => 22000,
            'move_in_date' => '2026-01-01',
        ]);

        // 未請求のMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 55000,
            'management_fee_subtotal' => 22000,
            'service_subtotal' => 0,
            'total_amount' => 77000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '更新テスト品目',
            'default_price' => 1500,
        ]);

        // 初期のDailyChargeを作成
        $dailyCharge = DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-05',
            'unit_price' => 1500,
            'quantity' => 1,
        ]);

        // Observerによって自動再計算が実行される
        $updatedInvoiceAfterCreate = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(1500, $updatedInvoiceAfterCreate->service_subtotal);
        $this->assertEquals(78500, $updatedInvoiceAfterCreate->total_amount);

        // DailyChargeを更新（数量を変更）
        $dailyCharge->update([
            'quantity' => 3,
        ]);

        // Observerによって再度自動再計算が実行される
        $updatedInvoiceAfterUpdate = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(4500, $updatedInvoiceAfterUpdate->service_subtotal); // 1500 * 3
        $this->assertEquals(81500, $updatedInvoiceAfterUpdate->total_amount); // 55000 + 22000 + 4500
    }

    /** @test */
    public function 未請求月へのDailyCharge削除でインボイスが自動再計算される()
    {
        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '303',
            'name' => '削除テスト入居者',
            'base_rent' => 48000,
            'base_management_fee' => 19000,
            'move_in_date' => '2026-01-01',
        ]);

        // 未請求のMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 48000,
            'management_fee_subtotal' => 19000,
            'service_subtotal' => 2000, // 初期値
            'total_amount' => 69000, // 48000 + 19000 + 2000
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '削除テスト品目',
            'default_price' => 1000,
        ]);

        // 複数のDailyChargeを作成
        $dailyCharge1 = DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-03',
            'unit_price' => 1000,
            'quantity' => 2, // 2000円
        ]);

        $dailyCharge2 = DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-07',
            'unit_price' => 1000,
            'quantity' => 1, // 1000円
        ]);

        // 両方のDailyCharge作成後にObserverが2回実行される
        $updatedInvoiceAfterCreates = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(3000, $updatedInvoiceAfterCreates->service_subtotal); // 2000 + 1000
        $this->assertEquals(70000, $updatedInvoiceAfterCreates->total_amount); // 48000 + 19000 + 3000

        // 一方のDailyChargeを削除
        $dailyCharge1->delete();

        // Observerによって再度自動再計算が実行される
        $updatedInvoiceAfterDelete = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(1000, $updatedInvoiceAfterDelete->service_subtotal); // 残りは1000のみ
        $this->assertEquals(68000, $updatedInvoiceAfterDelete->total_amount); // 48000 + 19000 + 1000
    }

    /** @test */
    public function 請求済み月へのDailyCharge追加で管理者に通知が送信される()
    {
        Notification::fake();

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '304',
            'name' => '通知テスト入居者（請求済み）',
            'base_rent' => 62000,
            'base_management_fee' => 26000,
            'move_in_date' => '2026-01-01',
        ]);

        // 請求済みのMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Billed,
            'rent_subtotal' => 62000,
            'management_fee_subtotal' => 26000,
            'service_subtotal' => 0,
            'total_amount' => 88000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '通知テスト品目',
            'default_price' => 1800,
        ]);

        // DailyChargeを追加
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-15',
            'unit_price' => 1800,
            'quantity' => 2,
        ]);

        // 通知が送信されていることを確認
        // （Observerは Notification::route('mail', ...) 宛に送信するため assertSentOnDemand を使用）
        Notification::assertSentOnDemand(
            DailyChargeAddedToBilledInvoice::class,
            function ($notification, $channels, $notifiable) use ($resident, $invoice) {
                return $notification->toArray(new \stdClass())['resident_id'] === $resident->id
                    && $notification->toArray(new \stdClass())['invoice_id'] === $invoice->id
                    && $notification->toArray(new \stdClass())['amount'] === 3600 // 1800 * 2
                    && in_array('mail', $channels);
            }
        );
    }

    /** @test */
    public function 入金済み月へのDailyCharge追加で管理者に通知が送信される()
    {
        Notification::fake();

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '305',
            'name' => '通知テスト入居者（入金済み）',
            'base_rent' => 58000,
            'base_management_fee' => 24000,
            'move_in_date' => '2026-01-01',
        ]);

        // 入金済みのMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Paid,
            'rent_subtotal' => 58000,
            'management_fee_subtotal' => 24000,
            'service_subtotal' => 0,
            'total_amount' => 82000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '入金済み通知テスト品目',
            'default_price' => 2200,
        ]);

        // DailyChargeを追加
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-20',
            'unit_price' => 2200,
            'quantity' => 1,
        ]);

        // 通知が送信されていることを確認
        Notification::assertSentOnDemand(
            DailyChargeAddedToBilledInvoice::class,
            function ($notification, $channels, $notifiable) use ($resident, $invoice) {
                return $notification->toArray(new \stdClass())['resident_id'] === $resident->id
                    && $notification->toArray(new \stdClass())['invoice_id'] === $invoice->id
                    && $notification->toArray(new \stdClass())['amount'] === 2200 // 2200 * 1
                    && in_array('mail', $channels);
            }
        );
    }

    /** @test */
    public function 請求データが存在しない月へのDailyCharge追加で通知が送信されない()
    {
        Notification::fake();

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '306',
            'name' => '未請求データなし入居者',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
            'move_in_date' => '2026-01-01',
        ]);

        // MonthlyInvoiceを作成していない

        $chargeItem = ChargeItem::create([
            'name' => '未請求データなしテスト品目',
            'default_price' => 1300,
        ]);

        // DailyChargeを追加
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-12',
            'unit_price' => 1300,
            'quantity' => 1,
        ]);

        // 通知が送信されていないことを確認
        Notification::assertNothingSent();
    }

    /** @test */
    public function 複数のDailyCharge操作が正しく処理される()
    {
        Notification::fake();

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '307',
            'name' => '複数操作テスト入居者',
            'base_rent' => 53000,
            'base_management_fee' => 21000,
            'move_in_date' => '2026-01-01',
        ]);

        // 未請求のMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 53000,
            'management_fee_subtotal' => 21000,
            'service_subtotal' => 0,
            'total_amount' => 74000,
        ]);

        $chargeItemA = ChargeItem::create([
            'name' => '品目A',
            'default_price' => 800,
        ]);

        $chargeItemB = ChargeItem::create([
            'name' => '品目B',
            'default_price' => 1200,
        ]);

        // 1. 未請求月へのDailyChargeA作成 → 自動再計算
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItemA->id,
            'date' => '2026-01-05',
            'unit_price' => 800,
            'quantity' => 2,
        ]);

        $invoiceAfterFirst = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(1600, $invoiceAfterFirst->service_subtotal); // 800 * 2
        $this->assertEquals(75600, $invoiceAfterFirst->total_amount); // 53000 + 21000 + 1600

        // 2. 未請求月へのDailyChargeB作成 → 自動再計算
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItemB->id,
            'date' => '2026-01-10',
            'unit_price' => 1200,
            'quantity' => 3,
        ]);

        $invoiceAfterSecond = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(5200, $invoiceAfterSecond->service_subtotal); // 1600 + (1200 * 3)
        $this->assertEquals(79200, $invoiceAfterSecond->total_amount); // 53000 + 21000 + 5200

        // 3. 請求済みにステータスを変更
        $invoice->update([
            'status' => InvoiceStatus::Billed,
        ]);

        // 4. 請求済み月へのDailyChargeA追加 → 通知送信
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItemA->id,
            'date' => '2026-01-18',
            'unit_price' => 800,
            'quantity' => 1,
        ]);

        // 通知が送信されていることを確認
        Notification::assertSentOnDemand(
            DailyChargeAddedToBilledInvoice::class,
            function ($notification, $channels, $notifiable) use ($resident, $invoice) {
                return $notification->toArray(new \stdClass())['resident_id'] === $resident->id
                    && $notification->toArray(new \stdClass())['invoice_id'] === $invoice->id
                    && $notification->toArray(new \stdClass())['charge_item_name'] === '品目A'
                    && $notification->toArray(new \stdClass())['amount'] === 800 // 800 * 1
                    && in_array('mail', $channels);
            }
        );

        // 最終的なインボイス状態を確認（サービス小計は変更されていないはず）
        $finalInvoice = MonthlyInvoice::find($invoice->id);
        $this->assertEquals(5200, $finalInvoice->service_subtotal); // 請求済みなので変更されていない
        $this->assertEquals(79200, $finalInvoice->total_amount);
    }
}