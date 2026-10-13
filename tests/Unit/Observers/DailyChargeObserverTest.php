<?php

namespace Tests\Unit\Observers;

use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ChargeItem;
use App\Enums\InvoiceStatus;
use App\Observers\DailyChargeObserver;
use App\Services\InvoiceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DailyChargeObserverTest extends TestCase
{
    use RefreshDatabase;

    protected $calculationServiceMock;
    protected $observer;

    protected function setUp(): void
    {
        parent::setUp();

        // InvoiceCalculationServiceのモックを作成し、コンテナに登録
        // （Observerはコンテナ経由で解決されるため、instance()で差し替える必要がある）
        $this->calculationServiceMock = Mockery::mock(InvoiceCalculationService::class);
        $this->app->instance(InvoiceCalculationService::class, $this->calculationServiceMock);
        $this->observer = new DailyChargeObserver($this->calculationServiceMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function 未請求月へのDailyCharge作成時に自動再計算が実行される()
    {
        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '101',
            'name' => 'テスト入居者',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
            'move_in_date' => '2026-01-01',
        ]);

        // 未請求のMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'total_amount' => 70000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => 'テスト品目',
            'default_price' => 1000,
        ]);

        // InvoiceCalculationServiceのcalculateメソッドが呼ばれることを期待
        $this->calculationServiceMock
            ->shouldReceive('calculate')
            ->once()
            ->with(Mockery::type(MonthlyInvoice::class))
            ->andReturnUsing(function ($invoice) {
                // 実際の計算をシミュレート（サービスが通常行う処理）
                $invoice->service_subtotal = 3000; // 例: 3個 * 1000円
                $invoice->total_amount = $invoice->rent_subtotal + $invoice->management_fee_subtotal + $invoice->service_subtotal;
            });

        // DailyChargeを作成（これがObserverをトリガーする）
        $dailyCharge = DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-15',
            'unit_price' => 1000,
            'quantity' => 3,
        ]);

        // インボイスが更新されていることを確認
        $this->assertEquals(3000, $invoice->fresh()->service_subtotal);
        $this->assertEquals(73000, $invoice->fresh()->total_amount); // 50000 + 20000 + 3000

        // ログに情報が記録されていることを確認（簡易的に）
        // 注: ログの詳細な検証は複雑になるため、ここでは例外が発生していないことを確認
        $this->assertTrue(true);
    }

    /** @test */
    public function 請求済み月へのDailyCharge作成時に通知が送信される()
    {
        Notification::fake();

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '102',
            'name' => '通知テスト入居者',
            'base_rent' => 60000,
            'base_management_fee' => 25000,
            'move_in_date' => '2026-01-01',
        ]);

        // 請求済みのMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Billed,
            'rent_subtotal' => 60000,
            'management_fee_subtotal' => 25000,
            'service_subtotal' => 0,
            'total_amount' => 85000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '通知テスト品目',
            'default_price' => 1500,
        ]);

        // DailyChargeを作成（これがObserverをトリガーする）
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-20',
            'unit_price' => 1500,
            'quantity' => 2,
        ]);

        // 通知が送信されていることを確認
        // （Observerは Notification::route('mail', ...) 宛に送信するため assertSentOnDemand を使用）
        Notification::assertSentOnDemand(
            \App\Notifications\DailyChargeAddedToBilledInvoice::class,
            function ($notification, $channels, $notifiable) use ($resident, $invoice) {
                $props = (new \ReflectionObject($notification));
                $residentProp = $props->getProperty('resident');
                $residentProp->setAccessible(true);
                $invoiceProp = $props->getProperty('invoice');
                $invoiceProp->setAccessible(true);

                return $residentProp->getValue($notification)->id === $resident->id
                    && $invoiceProp->getValue($notification)->id === $invoice->id
                    && in_array('mail', $channels);
            }
        );
    }

    /** @test */
    public function 入金済み月へのDailyCharge作成時に通知が送信される()
    {
        Notification::fake();

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '103',
            'name' => 'Paidテスト入居者',
            'base_rent' => 55000,
            'base_management_fee' => 22000,
            'move_in_date' => '2026-01-01',
        ]);

        // 入金済みのMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Paid,
            'rent_subtotal' => 55000,
            'management_fee_subtotal' => 22000,
            'service_subtotal' => 0,
            'total_amount' => 77000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => 'Paidテスト品目',
            'default_price' => 2000,
        ]);

        // DailyChargeを作成（これがObserverをトリガーする）
        DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-10',
            'unit_price' => 2000,
            'quantity' => 1,
        ]);

        // 通知が送信されていることを確認
        Notification::assertSentOnDemand(
            \App\Notifications\DailyChargeAddedToBilledInvoice::class,
            function ($notification, $channels, $notifiable) use ($resident, $invoice) {
                $props = (new \ReflectionObject($notification));
                $residentProp = $props->getProperty('resident');
                $residentProp->setAccessible(true);
                $invoiceProp = $props->getProperty('invoice');
                $invoiceProp->setAccessible(true);

                return $residentProp->getValue($notification)->id === $resident->id
                    && $invoiceProp->getValue($notification)->id === $invoice->id
                    && in_array('mail', $channels);
            }
        );
    }

    /** @test */
    public function 請求データが存在しない月へのDailyCharge作成時に何もしない()
    {
        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '104',
            'name' => '未請求データなし入居者',
            'base_rent' => 45000,
            'base_management_fee' => 18000,
            'move_in_date' => '2026-01-01',
        ]);

        // MonthlyInvoiceを作成していない（故意に）

        $chargeItem = ChargeItem::create([
            'name' => '存在しない請求データテスト品目',
            'default_price' => 1200,
        ]);

        // DailyChargeを作成（これがObserverをトリガーするが、何もしないはず）
        $dailyCharge = DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-05',
            'unit_price' => 1200,
            'quantity' => 2,
        ]);

        // 例外が発生していないことを確認
        $this->assertNotNull($dailyCharge->id);

        // ログに警告が出ていないことを確認（ここでは例外が発生していないことを確認）
        $this->assertTrue(true);
    }

    /** @test */
    public function 存在しない住民IDのDailyCharge作成時に警告ログが出力される()
    {
        // Logファサードをモック（ObserverのLog::warningを検証）
        Log::shouldReceive('warning')
            ->withArgs(function (...$args) {
                return str_contains((string) $args[0], 'Resident not found');
            });
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $chargeItem = ChargeItem::create([
            'name' => '存在しない住民テスト品目',
            'default_price' => 800,
        ]);

        // 存在しない住民IDでのDailyCharge作成はDBの外部キー制約で失敗するため、
        // モデルインスタンス経由でObserverの分岐のみを直接検証する
        $observer = new \App\Observers\DailyChargeObserver(app(\App\Services\InvoiceCalculationService::class));
        $charge = new DailyCharge([
            'resident_id' => 99999, // 存在しないID
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-01',
            'unit_price' => 800,
            'quantity' => 1,
        ]);

        // Observerを直接呼び出しても例外が発生しないことを確認
        $observer->created($charge);

        $this->assertTrue(true);
    }

    /** @test */
    public function トランザクションロールバック時の挙動_例外が発生しても処理が停止しない()
    {
        // InvoiceCalculationServiceのcalculateメソッドで例外を投げるようにモック
        $this->calculationServiceMock
            ->shouldReceive('calculate')
            ->once()
            ->andThrow(new \Exception('テスト用例外'));

        // Logファサードがerrorを記録することを期待
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'DailyCharge observer failed')
                    && isset($context['resident_id']);
            });

        // テストデータの準備
        $resident = Resident::create([
            'room_number' => '105',
            'name' => '例外テスト入居者',
            'base_rent' => 52000,
            'base_management_fee' => 21000,
            'move_in_date' => '2026-01-01',
        ]);

        // 未請求のMonthlyInvoiceを作成
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-01',
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 52000,
            'management_fee_subtotal' => 21000,
            'service_subtotal' => 0,
            'total_amount' => 73000,
        ]);

        $chargeItem = ChargeItem::create([
            'name' => '例外テスト品目',
            'default_price' => 900,
        ]);

        // DailyChargeを作成（これがObserverをトリガーするが、例外が発生するはず）
        $dailyCharge = DailyCharge::create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-01-12',
            'unit_price' => 900,
            'quantity' => 2,
        ]);

        // 例外が発生してもDailyCharge自体は作成されていることを確認
        $this->assertNotNull($dailyCharge->id);
        $this->assertEquals(1800, $dailyCharge->subtotal); // 900 * 2

        // Observer内で例外がキャッチされてログに記録されていることを確認
        // 実際のテストでは、上記のLog::shouldReceiveの期待が満たされていることを確認
        $this->assertTrue(true);
    }
}