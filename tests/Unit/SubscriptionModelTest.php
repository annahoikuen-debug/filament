<?php

use App\Models\Subscription;
use App\Models\Trial;
use App\Models\Facility;

test('fillableフィールドのみが一括代入で設定されること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-01-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
    ]);
    $facility = \App\Models\Facility::factory()->create();

    $subscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
        'started_at' => '2026-01-01 00:00:00',
        'ends_at' => '2026-12-31 23:59:59',
        'contract_accepted_at' => '2026-01-01 10:00:00',
        'contract_accepted_ip' => '192.168.1.1',
        'contract_accepted_user_agent' => 'Mozilla/5.0',
    ]);

    expect($subscription->exists)->toBeTrue()
        ->and($subscription->plan)->toBe('standard')
        ->and($subscription->status)->toBe('active')
        ->and($subscription->monthly_price)->toBe(50000);
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-01-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
    ]);
    $facility = \App\Models\Facility::factory()->create();

    $subscription = Subscription::create([
        'id' => 99999,
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
    ]);

    expect($subscription->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-01-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
    ]);
    $facility = \App\Models\Facility::factory()->create();

    $subscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
        'unknown_field' => 'テスト',
    ]);

    expect($subscription->exists)->toBeTrue()
        ->and($subscription->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-01-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
    ]);
    $facility = \App\Models\Facility::factory()->create();

    $subscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => '50000',
        'started_at' => '2026-01-01 00:00:00',
        'ends_at' => '2026-12-31 23:59:59',
        'contract_accepted_at' => '2026-01-01 10:00:00',
    ]);

    expect($subscription->monthly_price)->toBe(50000)
        ->and($subscription->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($subscription->ends_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($subscription->contract_accepted_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('trialリレーションが正しく動作すること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
    ]);
    $subscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
        'started_at' => '2026-01-01 00:00:00',
        'ends_at' => '2026-12-31 23:59:59',
        'contract_accepted_at' => '2026-01-01 10:00:00',
        'contract_accepted_ip' => '192.168.1.1',
        'contract_accepted_user_agent' => 'Mozilla/5.0',
    ]);

    expect($subscription->trial->id)->toBe($trial->id);
});

test('facilityリレーションが正しく動作すること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
    ]);
    $subscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
        'started_at' => '2026-01-01 00:00:00',
        'ends_at' => '2026-12-31 23:59:59',
        'contract_accepted_at' => '2026-01-01 10:00:00',
        'contract_accepted_ip' => '192.168.1.1',
        'contract_accepted_user_agent' => 'Mozilla/5.0',
    ]);

    expect($subscription->facility->id)->toBe($facility->id);
});

test('isActiveメソッドが正しく判定されること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-01-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
    ]);
    $facility = \App\Models\Facility::factory()->create();

    $activeSubscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
    ]);

    $inactiveSubscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'cancelled',
        'monthly_price' => 50000,
    ]);

    expect($activeSubscription->isActive())->toBeTrue()
        ->and($inactiveSubscription->isActive())->toBeFalse();
});

test('SoftDeletesが動作すること', function () {
    // 必要な依存関係を作成（ファクトリがないので手動で作成）
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-01-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
    ]);
    $facility = \App\Models\Facility::factory()->create();

    $subscription = Subscription::create([
        'trial_id' => $trial->id,
        'facility_id' => $facility->id,
        'plan' => 'standard',
        'status' => 'active',
        'monthly_price' => 50000,
    ]);

    $subscription->delete();
    expect($subscription->trashed())->toBeTrue();
});