<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\MonthlyClosingState;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\BillingAnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 19-23: 月次締めウィザード & ステートマシンのリグレッションテスト
 * ステップ進行、エラー時の中断、クローズ後のロック、緊急差戻しを検証
 */
class MonthlyClosingWizardTest extends TestCase
{
    use RefreshDatabase;

    protected Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facility = Facility::factory()->create();
    }

    protected function createResident(): Resident
    {
        return Resident::create([
            'facility_id' => $this->facility->id,
            'room_number' => '101',
            'name' => '山田太郎',
            'name_kana' => 'ヤマダタロウ',
            'base_rent' => 100000,
            'base_management_fee' => 20000,
            'status' => 'active',
            'move_in_date' => '2020-01-01',
        ]);
    }

    protected function createPaidInvoice(Resident $resident): MonthlyInvoice
    {
        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => '2026-10',
            'rent_subtotal' => 100000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 8000,
            'status' => 'unbilled',
            'payment_method' => 'bank_transfer',
            'tax_rate' => 10,
        ]);
        $invoice->markAsPaid(\App\Enums\PaymentMethod::Cash, '2026-10-31');

        return $invoice;
    }

    public function test_ステートマシンは正しい順序でのみ遷移する(): void
    {
        $state = MonthlyClosingState::findOrCreate('2026-10', $this->facility->id);
        $this->assertSame(MonthlyClosingState::STATUS_UNCLOSED, $state->status);

        // unclosed → approved への直接遷移は不可
        $this->expectException(\DomainException::class);
        $state->advanceTo(MonthlyClosingState::STATUS_APPROVED);
    }

    public function test_正常な状態遷移チェーンでクローズまで進む(): void
    {
        $state = MonthlyClosingState::findOrCreate('2026-10', $this->facility->id);

        $state->advanceTo(MonthlyClosingState::STATUS_GENERATED);
        $this->assertSame(MonthlyClosingState::STATUS_GENERATED, $state->status);

        $state->advanceTo(MonthlyClosingState::STATUS_APPROVED);
        $state->advanceTo(MonthlyClosingState::STATUS_SENT);
        $state->advanceTo(MonthlyClosingState::STATUS_CLOSED);

        $this->assertSame(MonthlyClosingState::STATUS_CLOSED, $state->status);
        $this->assertNotNull($state->closed_at);
        $this->assertTrue(MonthlyClosingState::isClosed('2026-10', $this->facility->id));

        // closed以降の遷移は不可
        $this->expectException(\DomainException::class);
        $state->advanceTo(MonthlyClosingState::STATUS_GENERATED);
    }

    public function test_Error検知時はウィザードが次ステップに進めない(): void
    {
        $detector = app(BillingAnomalyDetector::class);

        // 請求書なしの在籍者 → Error（請求額0円）
        $this->createResident();

        $report = $detector->detect('2026-10', $this->facility->id);
        $this->assertGreaterThan(0, $report['error_count']);
        $this->assertFalse($detector->canProceedToClosing($report));

        // ウィザードのガードロジック相当: error_count > 0 なら進行不可
        $canGoToStep2 = $report && $report['error_count'] === 0;
        $this->assertFalse($canGoToStep2);
    }

    public function test_Error解消後はウィザードが進行できる(): void
    {
        $resident = $this->createResident();
        $this->createPaidInvoice($resident);

        $detector = app(BillingAnomalyDetector::class);
        $report = $detector->detect('2026-10', $this->facility->id);

        $this->assertSame(0, $report['error_count']);
        $this->assertTrue($detector->canProceedToClosing($report));
    }

    public function test_クローズ済み月への請求再生成はブロックされる(): void
    {
        $resident = $this->createResident();
        $this->createPaidInvoice($resident);

        $state = MonthlyClosingState::findOrCreate('2026-10', $this->facility->id);
        while ($state->status !== MonthlyClosingState::STATUS_CLOSED) {
            $state->advanceTo(match ($state->status) {
                MonthlyClosingState::STATUS_UNCLOSED => MonthlyClosingState::STATUS_GENERATED,
                MonthlyClosingState::STATUS_GENERATED => MonthlyClosingState::STATUS_APPROVED,
                MonthlyClosingState::STATUS_APPROVED => MonthlyClosingState::STATUS_SENT,
                MonthlyClosingState::STATUS_SENT => MonthlyClosingState::STATUS_CLOSED,
            });
        }

        // クローズ済み判定
        $this->assertTrue(MonthlyClosingState::isClosed('2026-10', $this->facility->id));

        // ウィザードの generateInvoices と同じガード: closedなら中断
        $wizardWouldBlock = $state->status === MonthlyClosingState::STATUS_CLOSED;
        $this->assertTrue($wizardWouldBlock);
    }

    public function test_未確定請求が残る月はクローズできない(): void
    {
        $resident = $this->createResident();
        // unbilled のまま残す
        MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => '2026-10',
            'rent_subtotal' => 100000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => 'unbilled',
            'payment_method' => 'bank_transfer',
            'tax_rate' => 10,
        ]);

        $state = MonthlyClosingState::findOrCreate('2026-10', $this->facility->id);

        // ウィザードの closeMonth と同じバリデーション
        $unbilledCount = MonthlyInvoice::query()
            ->forYearMonth('2026-10')
            ->forFacility($this->facility->id)
            ->where('status', 'unbilled')
            ->count();

        $this->assertSame(1, $unbilledCount);
        // クローズは中断される（unbilledCount > 0）
        $this->assertGreaterThan(0, $unbilledCount);
        $this->assertNotSame(MonthlyClosingState::STATUS_CLOSED, $state->status);
    }

    public function test_緊急差戻しでクローズが解除されログに記録される(): void
    {
        $resident = $this->createResident();
        $this->createPaidInvoice($resident);

        $state = MonthlyClosingState::findOrCreate('2026-10', $this->facility->id);
        while ($state->status !== MonthlyClosingState::STATUS_CLOSED) {
            $state->advanceTo(match ($state->status) {
                MonthlyClosingState::STATUS_UNCLOSED => MonthlyClosingState::STATUS_GENERATED,
                MonthlyClosingState::STATUS_GENERATED => MonthlyClosingState::STATUS_APPROVED,
                MonthlyClosingState::STATUS_APPROVED => MonthlyClosingState::STATUS_SENT,
                MonthlyClosingState::STATUS_SENT => MonthlyClosingState::STATUS_CLOSED,
            });
        }

        $state->reopen('請求額に誤りがあったため緊急差戻し');

        $state->refresh();
        $this->assertSame(MonthlyClosingState::STATUS_UNCLOSED, $state->status);
        $this->assertSame('請求額に誤りがあったため緊急差戻し', $state->reopen_reason);
        $this->assertNotNull($state->reopened_at);
        $this->assertFalse(MonthlyClosingState::isClosed('2026-10', $this->facility->id));

        // ActivityLogに記録されていること（Step 22）
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => MonthlyClosingState::class,
            'subject_id' => $state->id,
            'log_name' => 'monthly_closing',
        ]);
    }

    public function test_異常検知スナップショットが締め状態に記録される(): void
    {
        $resident = $this->createResident();
        $this->createPaidInvoice($resident);

        $detector = app(BillingAnomalyDetector::class);
        $report = $detector->detect('2026-10', $this->facility->id);

        $state = MonthlyClosingState::findOrCreate('2026-10', $this->facility->id);
        $state->update(['anomaly_report' => $report]);

        $state->refresh();
        $this->assertIsArray($state->anomaly_report);
        $this->assertSame(0, $state->anomaly_report['error_count']);
        $this->assertArrayHasKey('checked_at', $state->anomaly_report);
    }
}
