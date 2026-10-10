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
   // daysLeft=0 は通知対象日のため警告メール＋期限切れメールの2回送信
   $mailService->shouldReceive('send')->twice();

   (new SendTrialExpiryNotification($trial))->handle($mailService);

   expect($trial->fresh()->status)->toBe('expired');
});

test('期限切れだが既にexpiredのトライアルは期限切れメールを再送信しないこと', function () {
   $trial = Trial::factory()->create([
       'status' => 'expired',
       'email' => 'trial@example.com',
       'trial_ends_at' => now()->subDay(),
   ]);

   $mailService = Mockery::mock(MailService::class);
   // daysLeft=0 の警告は送信されるが、期限切れメールは送信されない
   $mailService->shouldReceive('send')->once();

   (new SendTrialExpiryNotification($trial))->handle($mailService);

   expect($trial->fresh()->status)->toBe('expired');
});
