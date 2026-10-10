<?php

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GenerateMonthlyInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facility = Facility::factory()->create(['is_active' => true]);
    }

    public function test_invalid_year_month_format_fails(): void
    {
        $this->artisan('billing:generate-monthly', ['--year-month' => '2026-1'])
            ->expectsOutputToContain('年月形式が不正です: 2026-1')
            ->assertExitCode(1);
    }

    public function test_error_path_returns_failure(): void
    {
        // データベース処理で致命的例外が発生するケース
        \Illuminate\Support\Facades\DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('計算エラー'));

        Log::shouldReceive('error')->atLeast()->once();

        Artisan::call('billing:generate-monthly', ['--year-month' => '2026-03']);
        $output = Artisan::output();

        $this->assertStringContainsString('エラーが発生しました: 計算エラー', $output);
    }

    public function test_per_resident_error_is_counted(): void
    {
        // 入居者単位の例外はエラー件数にカウントされ、コマンド自体は成功する
        Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'move_in_date' => '2026-01-01',
        ]);

        $this->mock(\App\Services\InvoiceCalculationService::class, function ($mock) {
            $mock->shouldReceive('calculate')->andThrow(new \RuntimeException('計算エラー'));
        });

        Log::shouldReceive('error')->atLeast()->once();

        Artisan::call('billing:generate-monthly', ['--year-month' => '2026-03']);
        $output = Artisan::output();

        $this->assertStringContainsString('| エラー   | 1    |', $output);
        $this->assertStringContainsString('エラーが 1 件発生しました。ログを確認してください。', $output);
        $this->assertSame(
            0,
            MonthlyInvoice::where('billing_year_month', '2026-03')->count()
        );
    }

    public function test_inactive_facility_resident_is_skipped(): void
    {
        // 非アクティブ施設の入居者は対象外
        $inactiveFacility = Facility::factory()->create(['is_active' => false]);
        Resident::factory()->create([
            'facility_id' => $inactiveFacility->id,
            'move_in_date' => '2026-01-01',
        ]);

        $exitCode = Artisan::call('billing:generate-monthly', ['--year-month' => '2026-03']);

        $this->assertSame(0, $exitCode);

        $this->assertSame(
            0,
            MonthlyInvoice::where('billing_year_month', '2026-03')->count()
        );
    }

    public function test_resident_not_living_in_month_is_skipped(): void
    {
        // 2026-04 に入居 → 2026-03 は対象外
        Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'move_in_date' => '2026-04-10',
        ]);

        $this->artisan('billing:generate-monthly', ['--year-month' => '2026-03'])
            ->assertSuccessful();

        $this->assertSame(
            0,
            MonthlyInvoice::where('billing_year_month', '2026-03')->count()
        );
    }

    public function test_billed_invoice_skipped_without_force(): void
    {
        $resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'move_in_date' => '2026-01-01',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
        ]);
        MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => '2026-03',
            'status' => InvoiceStatus::Billed,
        ]);

        $this->artisan('billing:generate-monthly', ['--year-month' => '2026-03'])
            ->assertSuccessful();

        // Billed のまま（再計算されない）
        $invoice = $resident->monthlyInvoices()->where('billing_year_month', '2026-03')->first();
        $this->assertSame(InvoiceStatus::Billed, $invoice->status);
    }

    public function test_dry_run_does_not_save(): void
    {
        Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'move_in_date' => '2026-01-01',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
        ]);

        $this->artisan('billing:generate-monthly', ['--year-month' => '2026-03', '--dry-run' => true])
            ->expectsOutputToContain('実行結果 (ドライラン)')
            ->assertSuccessful();

        $this->assertSame(
            0,
            MonthlyInvoice::where('billing_year_month', '2026-03')->count()
        );
    }

    public function test_facility_filter_limits_processing(): void
    {
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        Resident::factory()->create(['facility_id' => $this->facility->id, 'move_in_date' => '2026-01-01']);
        Resident::factory()->create(['facility_id' => $otherFacility->id, 'move_in_date' => '2026-01-01']);

        $this->artisan('billing:generate-monthly', ['--year-month' => '2026-03', '--facility-id' => $this->facility->id])
            ->assertSuccessful();

        $this->assertSame(1, MonthlyInvoice::where('facility_id', $this->facility->id)->where('billing_year_month', '2026-03')->count());
        $this->assertSame(0, MonthlyInvoice::where('facility_id', $otherFacility->id)->where('billing_year_month', '2026-03')->count());
    }
}
