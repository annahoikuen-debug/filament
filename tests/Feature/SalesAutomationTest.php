<?php

use App\Jobs\SendTrialNurtureEmails;
use App\Models\Booking;
use App\Models\Subscription;
use App\Models\Trial;
use App\Services\LeadScoringService;
use App\Services\MailService;
use App\Services\QuoteService;

$validPayload = [
    'company_name' => 'テスト法人',
    'contact_name' => '山田 太郎',
    'email' => 'trial1@example.com',
    'phone' => '03-1234-5678',
    'facility_type' => 'special_nursing',
    'resident_capacity' => '50_100',
];

/**
 * トライアルの移行トークンを取得（プロビジョニング時に生成される）
 */
function tokenFor(Trial $trial): array
{
    return ['conversion_token' => $trial->trial_config['conversion_token']];
}

/**
 * 本契約移行の標準ペイロード
 */
function convertPayload(Trial $trial, array $overrides = []): array
{
    return array_merge([
        'plan' => 'starter',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'カ）テスト',
        ],
        'contract_accepted' => true,
    ], tokenFor($trial), $overrides);
}

// ==================== リードスコアリング ====================

test('リードスコアがトライアル作成時に自動算出されること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => true,
        'challenges' => ['calc_errors', 'manual_work'],
        'budget' => 'over_50k',
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    // 特養30 + 50_100:20 + 課題2つ:10 + over_50k:20 + seed:10 = 90
    expect($trial->score)->toBe(90)
        ->and($trial->trial_config['lead_tier'])->toBe('hot');
});

test('リードスコアの帯判定が正しいこと', function () {
    $service = new LeadScoringService;

    expect($service->tier(90))->toBe('hot')
        ->and($service->tier(60))->toBe('warm')
        ->and($service->tier(30))->toBe('cold');
});

test('スコアリングの入力欠損時に0点加算でクラッシュしないこと', function () {
    $service = new LeadScoringService;

    expect($service->score([]))->toBe(0);
});

// ==================== 見積書 ====================

test('見積りAPIがプランと月額を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    // 50_100 → スタンダード ¥35,000
    $quoteResponse = $this->getJson("/api/trials/{$trial->id}/quote")->assertStatus(200);

    expect($quoteResponse->json('quote.plan'))->toBe('standard')
        ->and($quoteResponse->json('quote.monthly_price'))->toBe(35000);
});

test('見積りAPIが容量に応じたプランを推奨すること', function () {
    $service = new QuoteService;

    $small = new Trial(['resident_capacity' => 'under_30']);
    $large = new Trial(['resident_capacity' => 'over_200']);

    expect($service->quote($small)['plan'])->toBe('starter')
        ->and($service->quote($small)['monthly_price'])->toBe(15000)
        ->and($service->quote($large)['plan'])->toBe('enterprise')
        ->and($service->quote($large)['monthly_price'])->toBe(0);
});

test('見積書メール送信APIが動作すること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/quote/send", tokenFor($trial))->assertStatus(200);
});

test('見積書メール送信はトークンなしで403を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/quote/send")->assertStatus(403);
    $this->postJson("/api/trials/{$trial->id}/quote/send", ['conversion_token' => 'invalid'])->assertStatus(403);
});

// ==================== 本契約移行 ====================

test('トライアルから本契約に移行できること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $convertResponse = $this->postJson(
        "/api/trials/{$trial->id}/convert",
        convertPayload($trial, ['plan' => 'standard'])
    )->assertStatus(200);

    $trial->refresh();
    $subscription = Subscription::where('trial_id', $trial->id)->first();

    expect($trial->status)->toBe('converted')
        ->and($subscription)->not->toBeNull()
        ->and($subscription->plan)->toBe('standard')
        ->and($subscription->monthly_price)->toBe(35000)
        ->and($subscription->contract_accepted_at)->not->toBeNull()
        ->and($convertResponse->json('subscription_id'))->toBe($subscription->id);
});

test('移行トークンなしの本契約移行は403を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    // トークンなし
    $this->postJson("/api/trials/{$trial->id}/convert", [
        'plan' => 'starter',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => ['name' => '銀行', 'branch_name' => '支店', 'account_type' => '普通', 'account_number' => '1', 'account_holder' => 'カ）X'],
        'contract_accepted' => true,
    ])->assertStatus(403);

    // 不正なトークン
    $this->postJson("/api/trials/{$trial->id}/convert", array_merge(
        convertPayload($trial),
        ['conversion_token' => 'invalid-token']
    ))->assertStatus(403);

    // 移行されていないことを確認
    expect($trial->fresh()->status)->toBe('active')
        ->and(Subscription::where('trial_id', $trial->id)->count())->toBe(0);
});

test('本契約移行は冪等であること（二重移行でサブスクリプションが重複しない）', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $first = $this->postJson("/api/trials/{$trial->id}/convert", convertPayload($trial))->assertStatus(200);
    $second = $this->postJson("/api/trials/{$trial->id}/convert", convertPayload($trial))->assertStatus(200);

    expect(Subscription::where('trial_id', $trial->id)->count())->toBe(1)
        ->and($second->json('subscription_id'))->toBe($first->json('subscription_id'))
        ->and($second->json('message'))->toBe('既に本契約に移行済みです。');
});

test('本契約移行で施設名のトライアルプレフィックスが除去されること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));
    $originalName = $trial->facility->name;
    expect($originalName)->toBe("トライアル テスト法人 ({$trial->id})");

    $this->postJson("/api/trials/{$trial->id}/convert", convertPayload($trial))->assertStatus(200);

    expect($trial->fresh()->facility->name)->toBe('テスト法人');
});

test('エンタープライズ移行には見積金額が必須であること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/convert", convertPayload($trial, [
        'plan' => 'enterprise',
    ]))->assertStatus(422);
});

test('契約同意なしの移行は422を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/convert", convertPayload($trial, [
        'contract_accepted' => false,
    ]))->assertStatus(422);
});

test('無効な請求書登録番号の移行は422を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/convert", convertPayload($trial, [
        'invoice_registration_number' => 'INVALID',
    ]))->assertStatus(422);
});

// ==================== デモ予約 ====================

test('デモ面談の予約を受け付けられること', function () {
    $response = $this->postJson('/api/bookings', [
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'phone' => '03-1234-5678',
        'preferred_date' => now()->addDays(3)->format('Y-m-d'),
        'preferred_time' => '14:00',
        'notes' => '導入検討中',
    ])->assertStatus(201);

    expect($response->json('booking_id'))->toBeGreaterThan(0)
        ->and(Booking::where('email', 'yamada@example.com')->exists())->toBeTrue();
});

test('過去日のデモ予約は422を返すこと', function () {
    $this->postJson('/api/bookings', [
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'preferred_date' => now()->subDay()->format('Y-m-d'),
        'preferred_time' => '14:00',
    ])->assertStatus(422);
});

test('時刻形式が不正なデモ予約は422を返すこと', function () {
    $this->postJson('/api/bookings', [
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'preferred_date' => now()->addDays(2)->format('Y-m-d'),
        'preferred_time' => '午後',
    ])->assertStatus(422);
});

test('同一日時の二重予約は409を返すこと', function () {
    $date = now()->addDays(2)->format('Y-m-d');

    $this->postJson('/api/bookings', [
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'preferred_date' => $date,
        'preferred_time' => '14:00',
    ])->assertStatus(201);

    $this->postJson('/api/bookings', [
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'preferred_date' => $date,
        'preferred_time' => '14:00',
    ])->assertStatus(409);

    expect(Booking::where('email', 'yamada@example.com')->count())->toBe(1);
});

test('予約一覧は認証なしではアクセスできないこと', function () {
    // API の一覧エンドポイントは廃止（POST のみ残るため GET は 405）
    $this->getJson('/api/bookings')->assertStatus(405);

    // Web ルートは未認証で401
    $this->get('/bookings')->assertStatus(401);
});

// ==================== ナーチャリングメール ====================

test('ナーチャリングメールが経過日数に応じて送信されること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'nurture@example.com',
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    // 3日経過したことにする
    $trial->update(['trial_started_at' => now()->subDays(3)]);

    (new SendTrialNurtureEmails)->handle(app(MailService::class));

    expect($trial->fresh()->trial_config['emails_sent'])->toContain('checkin_3d');
});

test('ナーチャリングメールは同一ステージを重複送信しないこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'nurture2@example.com',
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));
    $trial->update(['trial_started_at' => now()->subDays(3)]);

    $job = new SendTrialNurtureEmails;
    $job->handle(app(MailService::class));
    $job->handle(app(MailService::class));

    // emails_sent に checkin_3d が1回だけ記録
    expect(array_count_values($trial->fresh()->trial_config['emails_sent'])['checkin_3d'])->toBe(1);
});

// ==================== メール送信基盤 ====================

test('MailServiceは未設定環境でログにフォールバックすること', function () {
    $service = app(MailService::class);

    // テスト環境の mail.default は log
    expect($service->isMailConfigured())->toBeFalse();
});
