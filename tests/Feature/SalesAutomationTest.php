<?php

use App\Models\Booking;
use App\Models\Subscription;
use App\Models\Trial;
use App\Models\User;
use App\Services\LeadScoringService;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Hash;

$validPayload = [
    'company_name' => 'テスト法人',
    'contact_name' => '山田 太郎',
    'email' => 'trial1@example.com',
    'phone' => '03-1234-5678',
    'facility_type' => 'special_nursing',
    'resident_capacity' => '50_100',
];

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
    $service = new LeadScoringService();

    expect($service->tier(90))->toBe('hot')
        ->and($service->tier(60))->toBe('warm')
        ->and($service->tier(30))->toBe('cold');
});

test('スコアリングの入力欠損時に0点加算でクラッシュしないこと', function () {
    $service = new LeadScoringService();

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
    $service = new QuoteService();

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

    $this->postJson("/api/trials/{$trial->id}/quote/send")->assertStatus(200);
});

// ==================== 本契約移行 ====================

test('トライアルから本契約に移行できること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $convertResponse = $this->postJson("/api/trials/{$trial->id}/convert", [
        'plan' => 'standard',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'カ）テスト',
        ],
        'contract_accepted' => true,
    ])->assertStatus(200);

    $trial->refresh();
    $subscription = Subscription::where('trial_id', $trial->id)->first();

    expect($trial->status)->toBe('converted')
        ->and($subscription)->not->toBeNull()
        ->and($subscription->plan)->toBe('standard')
        ->and($subscription->monthly_price)->toBe(35000)
        ->and($subscription->contract_accepted_at)->not->toBeNull()
        ->and($convertResponse->json('subscription_id'))->toBe($subscription->id);
});

test('本契約移行で施設名のトライアルプレフィックスが除去されること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));
    $originalName = $trial->facility->name;
    expect($originalName)->toBe("トライアル テスト法人 ({$trial->id})");

    $this->postJson("/api/trials/{$trial->id}/convert", [
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
    ])->assertStatus(200);

    expect($trial->fresh()->facility->name)->toBe('テスト法人');
});

test('エンタープライズ移行には見積金額が必須であること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/convert", [
        'plan' => 'enterprise',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'カ）テスト',
        ],
        'contract_accepted' => true,
    ])->assertStatus(422);
});

test('契約同意なしの移行は422を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/convert", [
        'plan' => 'starter',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'カ）テスト',
        ],
    ])->assertStatus(422);
});

test('無効な請求書登録番号の移行は422を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    $this->postJson("/api/trials/{$trial->id}/convert", [
        'plan' => 'starter',
        'invoice_registration_number' => 'INVALID',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'カ）テスト',
        ],
        'contract_accepted' => true,
    ])->assertStatus(422);
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

test('予約一覧APIがpendingの予約を返すこと', function () {
    Booking::create([
        'name' => '一覧テスト',
        'email' => 'list@example.com',
        'preferred_date' => now()->addDays(2),
        'preferred_time' => '10:00',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/bookings')->assertStatus(200);

    expect($response->json('bookings'))->not->toBeEmpty();
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

    (new App\Jobs\SendTrialNurtureEmails())->handle(app(App\Services\MailService::class));

    expect($trial->fresh()->trial_config['emails_sent'])->toContain('checkin_3d');
});

test('ナーチャリングメールは同一ステージを重複送信しないこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'nurture2@example.com',
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));
    $trial->update(['trial_started_at' => now()->subDays(3)]);

    $job = new App\Jobs\SendTrialNurtureEmails();
    $job->handle(app(App\Services\MailService::class));
    $job->handle(app(App\Services\MailService::class));

    // emails_sent に checkin_3d が1回だけ記録
    expect(array_count_values($trial->fresh()->trial_config['emails_sent'])['checkin_3d'])->toBe(1);
});

// ==================== メール送信基盤 ====================

test('MailServiceは未設定環境でログにフォールバックすること', function () {
    $service = app(App\Services\MailService::class);

    // テスト環境の mail.default は log
    expect($service->isMailConfigured())->toBeFalse();
});
