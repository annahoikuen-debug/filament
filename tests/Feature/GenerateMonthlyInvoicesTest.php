<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateMonthlyInvoices;
use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateMonthlyInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function 新規請求データが作成されること()
    {
        // 施設作成
        $facility = Facility::factory()->create(['is_active' => true]);
        
        // 入居者作成（前月に在籍）
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
            'base_rent' => 80000,
            'base_management_fee' => 15000,
        ]);

        $yearMonth = '2026-09';

        // コマンド実行
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // 請求データが作成されていること
        $invoice = MonthlyInvoice::where('resident_id', $resident->id)
            ->where('billing_year_month', $yearMonth)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals($resident->id, $invoice->resident_id);
        $this->assertEquals($facility->id, $invoice->facility_id);
        $this->assertEquals($yearMonth, $invoice->billing_year_month);
        $this->assertEquals(InvoiceStatus::Unbilled, $invoice->status);
        $this->assertEquals(95000, $invoice->total_amount); // 80000 + 15000
    }

    /** @test */
    public function 既存請求データがUnbilledの場合は更新されること()
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
            'base_rent' => 80000,
            'base_management_fee' => 15000,
        ]);

        $yearMonth = '2026-09';

        // 既存請求データ作成（Unbilled）
        $existingInvoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => $yearMonth,
            'status' => InvoiceStatus::Unbilled,
            'rent_subtotal' => 70000,
            'management_fee_subtotal' => 10000,
            'service_subtotal' => 0,
            'total_amount' => 80000,
        ]);

        // 入居者の家賃を変更
        $resident->update(['base_rent' => 90000]);

        // コマンド実行
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // 更新されていること
        $existingInvoice->refresh();
        $this->assertEquals(105000, $existingInvoice->total_amount); // 90000 + 15000
    }

    /** @test */
    public function 確定済み請求はforceなしでスキップされること()
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
            'base_rent' => 80000,
            'base_management_fee' => 15000,
        ]);

        $yearMonth = '2026-09';

        // 確定済み請求データ作成
        $existingInvoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => $yearMonth,
            'status' => InvoiceStatus::Billed,
            'rent_subtotal' => 70000,
            'management_fee_subtotal' => 10000,
            'service_subtotal' => 0,
            'total_amount' => 80000,
        ]);

        // 入居者の家賃を変更
        $resident->update(['base_rent' => 90000]);

        // コマンド実行（forceなし）
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // 更新されていないこと
        $existingInvoice->refresh();
        $this->assertEquals(80000, $existingInvoice->total_amount);
    }

    /** @test */
    public function forceオプションで確定済みも更新されること()
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
            'base_rent' => 80000,
            'base_management_fee' => 15000,
        ]);

        $yearMonth = '2026-09';

        // 確定済み請求データ作成
        $existingInvoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => $yearMonth,
            'status' => InvoiceStatus::Billed,
            'rent_subtotal' => 70000,
            'management_fee_subtotal' => 10000,
            'service_subtotal' => 0,
            'total_amount' => 80000,
        ]);

        // 入居者の家賃を変更
        $resident->update(['base_rent' => 90000]);

        // コマンド実行（forceあり）
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--force' => true,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // 更新されていること
        $existingInvoice->refresh();
        $this->assertEquals(105000, $existingInvoice->total_amount);
    }

    /** @test */
    public function 施設指定で該当施設のみ処理されること()
    {
        $facility1 = Facility::factory()->create(['is_active' => true, 'name' => '施設A']);
        $facility2 = Facility::factory()->create(['is_active' => true, 'name' => '施設B']);

        $resident1 = Resident::factory()->create([
            'facility_id' => $facility1->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
        ]);
        $resident2 = Resident::factory()->create([
            'facility_id' => $facility2->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
        ]);

        $yearMonth = '2026-09';

        // 施設1のみ指定して実行
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--facility-id' => $facility1->id,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // 施設1の入居者のみ請求作成
        $invoice1 = MonthlyInvoice::where('resident_id', $resident1->id)->first();
        $invoice2 = MonthlyInvoice::where('resident_id', $resident2->id)->first();

        $this->assertNotNull($invoice1);
        $this->assertNull($invoice2);
    }

    /** @test */
    public function 在籍期間外の入居者はスキップされること()
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        
        // 9月以降に入居（9月請求対象外）
        $resident1 = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-10-01'),
        ]);
        
        // 8月に退去（9月請求対象外）
        $resident2 = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-07-01'),
            'move_out_date' => Carbon::parse('2026-08-31'),
        ]);

        $yearMonth = '2026-09';

        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // どちらも請求作成されない
        $this->assertEquals(0, MonthlyInvoice::where('billing_year_month', $yearMonth)->count());
    }

    /** @test */
    public function dry_runオプションではデータが保存されないこと()
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
            'base_rent' => 80000,
        ]);

        $yearMonth = '2026-09';

        // dry-runで実行
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // データは作成されない
        $this->assertEquals(0, MonthlyInvoice::where('billing_year_month', $yearMonth)->count());
    }

    /** @test */
    public function 不正な年月形式でエラーになること()
    {
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => '2026/09', // スラッシュ区切り
            '--dry-run' => true,
        ]);

        $this->assertEquals(1, $exitCode);
    }

    /** @test */
    public function 自動判定で前月が対象になること()
    {
        // 現在日時を固定
        Carbon::setTestNow('2026-10-15');

        $facility = Facility::factory()->create(['is_active' => true]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
        ]);

        // 年月指定なしで実行（前月=2026-09が対象）
        $exitCode = Artisan::call('billing:generate-monthly', [
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // 2026-09の請求が作成対象になることを確認（dry-runなので実際には作成されない）
        $output = Artisan::output();
        $this->assertStringContainsString('2026-09', $output);

        Carbon::setTestNow();
    }

    /** @test */
    public function アクティビティログが記録されること()
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'move_in_date' => Carbon::parse('2026-08-15'),
        ]);

        $yearMonth = '2026-09';

        $exitCode = Artisan::call('billing:generate-monthly', [
            '--year-month' => $yearMonth,
            '--dry-run' => false,
        ]);

        $this->assertEquals(0, $exitCode);

        // activity_logに記録されていること
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'monthly_invoice',
            'description' => '月次請求生成コマンド実行: 2026-09',
        ]);
    }
}