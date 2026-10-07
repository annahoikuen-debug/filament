<?php

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$validPayload = [
    'company_name' => 'テスト法人',
    'contact_name' => '山田 太郎',
    'email' => 'trial1@example.com',
    'phone' => '03-1234-5678',
    'facility_type' => 'special_nursing',
    'resident_capacity' => '50_100',
];

test('トライアル申込（サンプルデータあり）が201で環境をプロビジョニングすること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => true,
    ]));

    $response->assertStatus(201)->assertJsonStructure(['success', 'message', 'trial_id']);

    $trial = Trial::find($response->json('trial_id'));
    expect($trial->status)->toBe('active')
        ->and($trial->trial_config['seed_sample_data'])->toBeTrue()
        ->and($trial->facility_id)->not->toBeNull()
        ->and($trial->facility->name)->toBe("トライアル テスト法人 ({$trial->id})")
        ->and(User::where('email', 'trial1@example.com')->exists())->toBeTrue()
        ->and($trial->trial_config['temp_password'])->not->toBeNull()
        ->and(Resident::where('facility_id', $trial->facility_id)->count())->toBe(2)
        ->and(MonthlyInvoice::where('facility_id', $trial->facility_id)->count())->toBeGreaterThan(0);
});

test('トライアル申込（サンプルデータなし）は入居者を作成しないこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'trial2@example.com',
        'seed_sample_data' => false,
    ]));

    $response->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));
    expect(Resident::where('facility_id', $trial->facility_id)->count())->toBe(0);
});

test('無効なfacility_typeは422を返すこと', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'trial3@example.com',
        'facility_type' => 'unknown_type',
    ]));

    $response->assertStatus(422)->assertJsonValidationErrors(['facility_type']);
});

test('二重申込（同一email）は422を返すこと', function () use ($validPayload) {
    $this->postJson('/api/trials', $validPayload)->assertStatus(201);

    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'company_name' => '別法人',
    ]));

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

test('ソフトデリート済みのemailは再申込可能であること', function () use ($validPayload) {
    $first = $this->postJson('/api/trials', $validPayload)->assertStatus(201);
    Trial::find($first->json('trial_id'))->delete();

    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]));

    $response->assertStatus(201);

    $newTrial = Trial::find($response->json('trial_id'));
    expect($newTrial->facility_id)->not->toBe(Trial::withTrashed()->find($first->json('trial_id'))->facility_id);
});

test('既存ユーザーのemailでもプロビジョニングがクラッシュしないこと', function () use ($validPayload) {
    User::create([
        'name' => '既存ユーザー',
        'email' => 'existing@example.com',
        'password' => bcrypt('pass'),
        'is_admin' => false,
    ]);

    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'existing@example.com',
        'contact_name' => '既存ユーザー',
        'seed_sample_data' => false,
    ]));

    $response->assertStatus(201);
    expect(Hash::check('pass', User::where('email', 'existing@example.com')->first()->password))->toBeTrue();
});

test('同一企業の複数トライアルは施設が分離されること', function () use ($validPayload) {
    $first = $this->postJson('/api/trials', array_merge($validPayload, [
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $second = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'trial4@example.com',
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $firstFacility = Trial::find($first->json('trial_id'))->facility_id;
    $secondFacility = Trial::find($second->json('trial_id'))->facility_id;

    expect($firstFacility)->not->toBe($secondFacility);
});

test('daysUntilExpiryは期限切れで0にクランプされること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'trial5@example.com',
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));

    // 有効期限内は14日
    expect($trial->daysUntilExpiry())->toBe(14)
        ->and($trial->isExpired())->toBeFalse();

    // 期限切れは0
    $trial->update(['trial_ends_at' => now()->subDay()]);
    expect($trial->daysUntilExpiry())->toBe(0)
        ->and($trial->isExpired())->toBeTrue();
});

test('CheckAndNotifyTrialExpiriesが期限切れトライアルをexpiredにすること', function () use ($validPayload) {
    $response = $this->postJson('/api/trials', array_merge($validPayload, [
        'email' => 'trial6@example.com',
        'seed_sample_data' => false,
    ]))->assertStatus(201);

    $trial = Trial::find($response->json('trial_id'));
    $trial->update(['trial_ends_at' => now()->subHour()]);

    (new App\Jobs\CheckAndNotifyTrialExpiries())->handle();

    expect($trial->fresh()->status)->toBe('expired');
});
