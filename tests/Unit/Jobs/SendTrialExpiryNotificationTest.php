<?php

use App\Jobs\SendTrialExpiryNotification;
use App\Models\Trial;
use App\Services\MailService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['mail.default' => 'log']);
});

test('通知対象日(7/3/1/0日前)に警告メールが送信されること', function () {
    Notification::fake();

    $trial = Trial::factory()->create([
        'status' => 'active',
        'email' => 'trial@example.com',
        'trial_ends_at' => now()->addDays(3),
    ]);

    $mailService = Mockery::mock(MailService::class);
    $mailService->shouldReceive('send')->once()->withArgs(function ($mailable, $email) {
        return $email === 'trial@example.com';
    });

    (new SendTrialExpiryNotification($trial))->handle($mailService);
});

test('通知対象外の日にはメールが送信されないこと', function () {
    $trial = Trial::factory()->create([
        'status' => 'active',
        'email' => 'trial@example.com',
        'trial_ends_at' => now()->addDays(5),
    ]);

    $mailService = Mockery::mock(MailService::class);
    $mailService->shouldNotReceive('send');

    (new SendTrialExpiryNotification($trial))->handle($mailService);

    expect(true)->toBeTrue();
});

test('期限切れのアクティブトライアルはexpiredに更新され期限切れメールが送信されること', function () {
    $trial = Trial::factory()->create([
        'status' => 'active',
        'email' => 'trial@example.com',
        'trial_ends_at' => now()->subDay(),
    ]);

    $mailService = Mockery::mock(MailService::class);
    // 期限切れ済みなので警告(daily 0)は送信されず、期限切れメールのみ
    $mailService->shouldReceive('send')->once();

    (new SendTrialExpiryNotification($trial))->handle($mailService);

    expect($trial->fresh()->status)->toBe('expired');
});

test('期限切れだが既にexpiredのトライアルは再送信されないこと', function () {
    $trial = Trial::factory()->create([
        'status' => 'expired',
        'email' => 'trial@example.com',
        'trial_ends_at' => now()->subDay(),
    ]);

    $mailService = Mockery::mock(MailService::class);
    $mailService->shouldNotReceive('send');

    (new SendTrialExpiryNotification($trial))->handle($mailService);

    expect($trial->fresh()->status)->toBe('expired');
});
