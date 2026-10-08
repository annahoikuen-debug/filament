<?php

use App\Models\Trial;
use App\Models\Facility;
use Carbon\Carbon;

test('fillableフィールドのみが一括代入で設定されること', function () {
    $facility = Facility::factory()->create();

    $trial = Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'test@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => 50,
        'score' => 85,
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => ['key' => 'value'],
    ]);

    expect($trial->exists)->toBeTrue()
        ->and($trial->company_name)->toBe('テスト会社')
        ->and($trial->contact_name)->toBe('テスト太郎')
        ->and($trial->email)->toBe('test@example.com')
        ->and($trial->status)->toBe('active')
        ->and($trial->score)->toBe(85);
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    $facility = Facility::factory()->create();

    $trial = Trial::create([
        'id' => 99999,
        'company_name' => 'ID無視テスト',
        'email' => 'test@example.com',
    ]);

    expect($trial->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $facility = Facility::factory()->create();

    $trial = Trial::create([
        'company_name' => '不正フィールドテスト',
        'email' => 'test@example.com',
        'unknown_field' => 'テスト',
    ]);

    expect($trial->exists)->toBeTrue()
        ->and($trial->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $facility = Facility::factory()->create();

    $trial = Trial::create([
        'company_name' => 'キャストテスト',
        'email' => 'test@example.com',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'trial_config' => ['test' => 'data'],
        'score' => '85',
    ]);

    expect($trial->trial_started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($trial->trial_ends_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($trial->trial_config)->toBeArray()
        ->and($trial->score)->toBe(85);
});

test('施設リレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $trial = Trial::factory()->create(['facility_id' => $facility->id]);

    expect($trial->facility->id)->toBe($facility->id);
});

test('isActiveメソッドが正しく判定されること', function () {
    $activeTrial = Trial::create([
        'company_name' => 'アクティブ',
        'email' => 'active@example.com',
        'status' => 'active',
    ]);

    $expiredTrial = Trial::create([
        'company_name' => '期限切れ',
        'email' => 'expired@example.com',
        'status' => 'expired',
    ]);

    $cancelledTrial = Trial::create([
        'company_name' => 'キャンセル',
        'email' => 'cancelled@example.com',
        'status' => 'cancelled',
    ]);

    expect($activeTrial->isActive())->toBeTrue()
        ->and($expiredTrial->isActive())->toBeFalse()
        ->and($cancelledTrial->isActive())->toBeFalse();
});

test('isExpiredメソッドが正しく判定されること', function () {
    // 明示的にexpiredステータス
    $expiredStatus = Trial::create([
        'company_name' => '明示的に期限切れ',
        'email' => 'expired1@example.com',
        'status' => 'expired',
    ]);
    
    // 終了日が過去
    $pastDate = Trial::create([
        'company_name' => '終了日が過去',
        'email' => 'expired2@example.com',
        'trial_ends_at' => Carbon::yesterday(),
        'status' => 'active', // ステータスはactiveだが終了日が過去
    ]);
    
    // 有効なトライアル
    $validTrial = Trial::create([
        'company_name' => '有効',
        'email' => 'valid@example.com',
        'trial_ends_at' => Carbon::tomorrow(),
        'status' => 'active',
    ]);
    
    // 終了日が未設定
    $noEndDate = Trial::create([
        'company_name' => '終了日未設定',
        'email' => 'noend@example.com',
        'trial_ends_at' => null,
        'status' => 'active',
    ]);

    expect($expiredStatus->isExpired())->toBeTrue()
        ->and($pastDate->isExpired())->toBeTrue()
        ->and($validTrial->isExpired())->toBeFalse()
        ->and($noEndDate->isExpired())->toBeFalse();
});

test('daysUntilExpiryメソッドが正しく日数を返すこと', function () {
    // 終了日が過去の場合は0
    $pastTrial = Trial::create([
        'company_name' => '過去終了',
        'email' => 'past@example.com',
        'trial_ends_at' => Carbon::yesterday(),
    ]);
    
    // 終了日が未設定の場合は0
    $noEndTrial = Trial::create([
        'company_name' => '終了日未設定',
        'email' => 'noend@example.com',
        'trial_ends_at' => null,
    ]);
    
    // 今日が終了日の場合は0
    $todayEndTrial = Trial::create([
        'company_name' => '今日終了',
        'email' => 'todayend@example.com',
        'trial_ends_at' => Carbon::today(),
    ]);
    
    // 明日が終了日の場合は1
    $tomorrowEndTrial = Trial::create([
        'company_name' => '明日終了',
        'email' => 'tomorrowend@example.com',
        'trial_ends_at' => Carbon::tomorrow(),
    ]);
    
    // 10日後が終了日の場合は10
    $tenDaysEndTrial = Trial::create([
        'company_name' => '10日後終了',
        'email' => 'tendays@example.com',
        'trial_ends_at' => Carbon::today()->addDays(10),
    ]);

    expect($pastTrial->daysUntilExpiry())->toBe(0)
        ->and($noEndTrial->daysUntilExpiry())->toBe(0)
        ->and($todayEndTrial->daysUntilExpiry())->toBe(0)
        ->and($tomorrowEndTrial->daysUntilExpiry())->toBe(1)
        ->and($tenDaysEndTrial->daysUntilExpiry())->toBe(10);
    
    // 日付単位での残り日数（時刻の影響を排除）
    $specificTimeTrial = Trial::create([
        'company_name' => '特定時刻',
        'email' => 'specific@example.com',
        'trial_ends_at' => Carbon::today()->addDay()->setTime(23, 59, 59),
    ]);
    
    // 現在時刻が午前の場合でも、日付単位では1日残っている
    expect($specificTimeTrial->daysUntilExpiry())->toBe(1);
});

test('SoftDeletesが動作すること', function () {
    $trial = Trial::create([
        'company_name' => 'テスト',
        'email' => 'test@example.com',
    ]);

    $trial->delete();
    expect($trial->trashed())->toBeTrue();
});