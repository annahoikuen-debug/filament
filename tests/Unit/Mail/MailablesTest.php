<?php

use App\Mail\BookingConfirmationMail;
use App\Mail\ContractCompletedMail;
use App\Mail\FormConfirmationMail;
use App\Mail\QuoteMail;
use App\Mail\TrialExpiryWarningMail;
use App\Mail\TrialExpiredMail;
use App\Mail\TrialNurtureMail;
use App\Mail\TrialProvisioningCompleteMail;
use App\Models\Booking;
use App\Models\FormSubmission;
use App\Models\Trial;

it('BookingConfirmationMail builds with subject and view', function () {
    $trial = Trial::factory()->create();
    $booking = Booking::create([
        'trial_id' => $trial->id,
        'name' => 'テスト太郎',
        'email' => 'taro@example.com',
        'phone' => '090-0000-0000',
        'preferred_date' => '2026-04-01',
        'preferred_time' => '10:00',
        'status' => 'pending',
    ]);

    $mail = new BookingConfirmationMail($booking);
    $built = $mail->build();

    expect($built->subject)->toBe('【あんしん】デモ面談の予約を受け付けました')
        ->and($built->view)->toBe('emails.booking_confirmation');
});

it('ContractCompletedMail builds with subject and view', function () {
    $trial = Trial::factory()->create();

    $mail = new ContractCompletedMail($trial, 'standard', 30000);
    $built = $mail->build();

    expect($built->subject)->toBe('【あんしん】本契約が完了しました')
        ->and($built->view)->toBe('emails.contract_completed');
});

it('QuoteMail builds with subject and view', function () {
    $trial = Trial::factory()->create();

    $mail = new QuoteMail($trial, [
        'plan' => 'standard',
        'plan_name' => 'スタンダードプラン',
        'monthly_price' => 30000,
        'note' => '備考',
    ]);
    $built = $mail->build();

    expect($built->subject)->toBe('【あんしん】見積書のご提出')
        ->and($built->view)->toBe('emails.quote');
});

it('FormConfirmationMail builds subject per type', function () {
    $types = [
        'catalog' => '【あんしん】資料請求を受け付けました',
        'demo' => '【あんしん】無料デモのお申し込みを受け付けました',
        'diagnosis' => '【あんしん】診断書フォームのダウンロード受付が完了しました',
        'prospect' => '【あんしん】入居相談・資料請求を受け付けました',
        'inquiry' => '【あんしん】お問い合わせを受け付けました',
        'unknown' => '【あんしん】お問い合わせを受け付けました',
    ];

    foreach ($types as $type => $expected) {
        $submission = FormSubmission::create([
            'type' => $type,
            'company' => 'テスト株式会社',
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'phone' => '090-0000-0000',
            'payload' => [],
        ]);

        $mail = new FormConfirmationMail($submission, 'https://example.com/dl');
        $built = $mail->build();

        expect($built->subject)->toBe($expected)
            ->and($built->view)->toBe('emails.form_confirmation');
    }
});

it('TrialExpiredMail builds with subject and view', function () {
    $trial = Trial::factory()->expired()->create();

    $mail = new TrialExpiredMail($trial);
    $built = $mail->build();

    expect($built->subject)->toBe('【あんしん】無料トライアルの期限が切れました')
        ->and($built->view)->toBe('emails.trial_expired');
});

it('TrialExpiryWarningMail builds subject with days left', function () {
    $trial = Trial::factory()->active()->create();

    $mail = new TrialExpiryWarningMail($trial, 5);
    $built = $mail->build();

    expect($built->subject)->toBe('【あんしん】トライアル終了まであと 5 日です')
        ->and($built->view)->toBe('emails.trial_expiry_warning');
});

it('TrialNurtureMail builds subject per stage', function () {
    $trial = Trial::factory()->active()->create();

    $stages = [
        'checkin_3d' => '【あんしん】トライアルは順調ですか？',
        'case_7d' => '【あんしん】導入施設の声をお届けします',
        'convert_10d' => '【あんしん】本契約への移行をご検討ください',
        'unknown_stage' => '【あんしん】お知らせ',
    ];

    foreach ($stages as $stage => $expected) {
        $mail = new TrialNurtureMail($trial, $stage);
        $built = $mail->build();

        expect($built->subject)->toBe($expected)
            ->and($built->view)->toBe('emails.trial_nurture');
    }
});

it('TrialProvisioningCompleteMail builds with subject and view', function () {
    $trial = Trial::factory()->active()->create();

    $mail = new TrialProvisioningCompleteMail($trial, 'temp-pass-123', 'token-abc');
    $built = $mail->build();

    expect($built->subject)->toBe('【あんしん】無料トライアルの準備が完了しました')
        ->and($built->view)->toBe('emails.trial_provisioning_complete');
});
