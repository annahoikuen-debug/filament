<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Trial;

test('fillableフィールドのみが一括代入で設定されること', function () {
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
        'trial_config' => [],
    ]);

    $booking = Booking::create([
        'trial_id' => $trial->id,
        'name' => 'テスト予約',
        'email' => 'test@example.com',
        'phone' => '090-1234-5678',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
        'notes' => 'テストメモ',
        'status' => 'pending',
    ]);

    expect($booking->exists)->toBeTrue()
        ->and($booking->name)->toBe('テスト予約')
        ->and($booking->email)->toBe('test@example.com')
        ->and($booking->phone)->toBe('090-1234-5678')
        ->and($booking->preferred_date)->toEqual(\Illuminate\Support\Carbon::parse('2026-10-15'))
        ->and($booking->preferred_time)->toBe('14:00')
        ->and($booking->notes)->toBe('テストメモ')
        ->and($booking->status)->toBe('pending');
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    // 必要な依存関係を作成
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial2@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => [],
    ]);

    $booking = Booking::create([
        'id' => 99999,
        'trial_id' => $trial->id,
        'name' => 'ID無視テスト',
        'email' => 'test@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
    ]);

    expect($booking->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    // 必要な依存関係を作成
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial3@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => [],
    ]);

    $booking = Booking::create([
        'trial_id' => $trial->id,
        'name' => '不正フィールドテスト',
        'email' => 'test@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
        'unknown_field' => 'テスト',
    ]);

    expect($booking->exists)->toBeTrue()
        ->and($booking->getAttribute('unknown_field'))->toBeNull();
});

test('isPendingメソッドが正しく判定されること', function () {
    // 必要な依存関係を作成
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial4@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => [],
    ]);

    $pendingBooking = Booking::create([
        'trial_id' => $trial->id,
        'name' => '保留中',
        'email' => 'test@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
        'status' => 'pending',
    ]);

    $confirmedBooking = Booking::create([
        'trial_id' => $trial->id,
        'name' => '確定済み',
        'email' => 'test2@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
        'status' => 'confirmed',
    ]);

    $cancelledBooking = Booking::create([
        'trial_id' => $trial->id,
        'name' => 'キャンセル',
        'email' => 'test3@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
        'status' => 'cancelled',
    ]);

    expect($pendingBooking->isPending())->toBeTrue()
        ->and($confirmedBooking->isPending())->toBeFalse()
        ->and($cancelledBooking->isPending())->toBeFalse();
});

test('trialリレーションが正しく動作すること', function () {
    // 必要な依存関係を作成
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial5@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => [],
    ]);

    $booking = Booking::create([
        'trial_id' => $trial->id,
        'name' => 'テスト予約',
        'email' => 'test@example.com',
        'phone' => '090-1234-5678',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
    ]);

    expect($booking->trial->id)->toBe($trial->id);
});

test('確定日時が設定されているか確認すること', function () {
    // 必要な依存関係を作成
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial6@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => [],
    ]);

    $bookingWithConfirmedAt = Booking::create([
        'trial_id' => $trial->id,
        'name' => '確定日時あり',
        'email' => 'test@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
        'confirmed_at' => '2026-10-01 10:00:00',
    ]);

    $bookingWithoutConfirmedAt = Booking::create([
        'trial_id' => $trial->id,
        'name' => '確定日時なし',
        'email' => 'test2@example.com',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
    ]);

    expect($bookingWithConfirmedAt->confirmed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($bookingWithoutConfirmedAt->confirmed_at)->toBeNull();
});

test('ソフトデリートが動作すること', function () {
    // 必要な依存関係を作成
    $facility = \App\Models\Facility::factory()->create();
    $trial = \App\Models\Trial::create([
        'company_name' => 'テスト会社',
        'contact_name' => 'テスト太郎',
        'email' => 'trial7@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'urban',
        'resident_capacity' => '50',
        'status' => 'active',
        'trial_started_at' => '2026-10-01 00:00:00',
        'trial_ends_at' => '2026-12-31 23:59:59',
        'facility_id' => $facility->id,
        'trial_config' => [],
    ]);

    $booking = Booking::create([
        'trial_id' => $trial->id,
        'name' => 'テスト予約',
        'email' => 'test@example.com',
        'phone' => '090-1234-5678',
        'preferred_date' => '2026-10-15',
        'preferred_time' => '14:00',
    ]);

    $booking->delete();
    expect($booking->trashed())->toBeTrue();
});