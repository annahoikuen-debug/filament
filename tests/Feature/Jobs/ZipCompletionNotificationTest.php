<?php

use App\Enums\InvoiceStatus;
use App\Jobs\GenerateMonthlyZipJob;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\FacilityConfigService;
use App\Services\InvoicePdfService;

test('GenerateMonthlyZipJob完了時にDB通知が作成されダウンロードURLが含まれること', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $facility = Facility::factory()->create();
    $resident = Resident::create([
        'facility_id' => $facility->id,
        'room_number' => '101',
        'name' => 'ZIP通知テスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'facility_id' => $facility->id,
        'resident_id' => $resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $jobId = 'test_zip_job_123';
    $job = new GenerateMonthlyZipJob('2026-10', $facility->id, $jobId, $user->id);

    $pdfService = app(InvoicePdfService::class);
    $configService = app(FacilityConfigService::class);

    $job->handle($pdfService, $configService);

    // ユーザーにDB通知が保存されたか確認
    $notifications = $user->notifications;
    expect($notifications->count())->toBeGreaterThanOrEqual(1);

    $latestNotification = $notifications->first();
    expect($latestNotification->data['title'])->toContain('2026-10分の請求書ZIPが完成しました');

    // actions 配列にダウンロードリンクが含まれること
    $actions = $latestNotification->data['actions'] ?? [];
    $hasDownloadUrl = false;
    foreach ($actions as $action) {
        if (isset($action['url']) && str_contains($action['url'], $jobId)) {
            $hasDownloadUrl = true;
            break;
        }
    }
    expect($hasDownloadUrl)->toBeTrue();
});
