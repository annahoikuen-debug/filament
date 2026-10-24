<?php

use App\Models\Facility;
use App\Models\User;
use Filament\Panel;
use Illuminate\Support\Carbon;

test('権限関連フィールドはmass assignmentで設定できないこと（セキュリティ）', function () {
    $facility = Facility::factory()->create();

    $user = User::create([
        'name' => 'テストユーザー',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'role' => 'corporate_admin',
        'facility_id' => $facility->id,
    ]);

    expect($user->exists)->toBeTrue()
        ->and($user->name)->toBe('テストユーザー')
        ->and($user->email)->toBe('test@example.com')
        // 権限・施設紐付けは fillable 外のため一括代入されない
        ->and($user->is_admin)->toBeNull()
        ->and($user->role)->toBeNull()
        ->and($user->facility_id)->toBeNull();

    // 内部処理（シーダー等）は forceCreate / unguarded で明示的に設定する
    $admin = User::forceCreate([
        'name' => '管理者',
        'email' => 'admin-force@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'role' => 'corporate_admin',
        'facility_id' => $facility->id,
    ]);

    expect($admin->is_admin)->toBeTrue()
        ->and($admin->role)->toBe('corporate_admin')
        ->and($admin->facility_id)->toBe($facility->id);
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    $facility = Facility::factory()->create();

    $user = User::create([
        'id' => 99999,
        'name' => 'ID無視テスト',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($user->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $facility = Facility::factory()->create();

    $user = User::create([
        'name' => '不正フィールドテスト',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'unknown_field' => 'テスト',
    ]);

    expect($user->exists)->toBeTrue()
        ->and($user->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $facility = Facility::factory()->create();

    $user = User::forceCreate([
        'name' => 'キャストテスト',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'is_admin' => 'true',
        'email_verified_at' => '2026-10-01 10:00:00',
    ]);

    $user->refresh(); // Refresh to ensure casts are applied

    expect($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->toBeInstanceOf(Carbon::class)
        ->and($user->password)->toStartWith('$2y$'); // bcrypt хеш
});

test('施設リレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $user = User::factory()->create(['facility_id' => $facility->id]);

    expect($user->facility->id)->toBe($facility->id);
});

test('isCorporateAdminメソッドが正しく判定されること', function () {
    $corporateAdmin = User::forceCreate([
        'name' => '法人管理者',
        'email' => 'corporate@example.com',
        'password' => bcrypt('password'),
        'role' => 'corporate_admin',
    ]);

    $facilityAdmin = User::forceCreate([
        'name' => '施設管理者',
        'email' => 'facility@example.com',
        'password' => bcrypt('password'),
        'role' => 'facility_admin',
    ]);

    expect($corporateAdmin->isCorporateAdmin())->toBeTrue()
        ->and($facilityAdmin->isCorporateAdmin())->toBeFalse();
});

test('isFacilityAdminメソッドが正しく判定されること', function () {
    $corporateAdmin = User::forceCreate([
        'name' => '法人管理者',
        'email' => 'corporate@example.com',
        'password' => bcrypt('password'),
        'role' => 'corporate_admin',
    ]);

    $facilityAdmin = User::forceCreate([
        'name' => '施設管理者',
        'email' => 'facility@example.com',
        'password' => bcrypt('password'),
        'role' => 'facility_admin',
    ]);

    expect($corporateAdmin->isFacilityAdmin())->toBeFalse()
        ->and($facilityAdmin->isFacilityAdmin())->toBeTrue();
});

test('canAccessPanelメソッドが管理者フラグに基づいて判定されること', function () {
    $admin = User::forceCreate([
        'name' => '管理者',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    $nonAdmin = User::forceCreate([
        'name' => '一般ユーザー',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'is_admin' => false,
    ]);

    $nullAdmin = User::forceCreate([
        'name' => '不明',
        'email' => 'null@example.com',
        'password' => bcrypt('password'),
        'is_admin' => null,
    ]);

    // モックのPanelオブジェクトを作成
    $panel = Mockery::mock(Panel::class);

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($nonAdmin->canAccessPanel($panel))->toBeFalse()
        ->and($nullAdmin->canAccessPanel($panel))->toBeFalse();
});
