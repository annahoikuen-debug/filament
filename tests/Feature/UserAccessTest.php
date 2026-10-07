<?php

use App\Models\User;

test('管理者ユーザー（is_admin=true）がFilamentパネルにアクセス可能であること', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

test('一般ユーザー（is_admin=false）がFilamentパネルにアクセス不可であること', function () {
    $user = User::factory()->create(['is_admin' => false]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

test('is_admin=nullのユーザーがFilamentパネルにアクセス不可であること', function () {
    $user = User::factory()->create(['is_admin' => null]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

test('一般ユーザーは管理画面ログイン後にadminページへアクセスできないこと', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    // Filament の認可によりアクセス拒否（403 またはログイン画面リダイレクト）
    expect(in_array($response->status(), [302, 403]))->toBeTrue();
});

test('管理者ユーザーは管理画面へアクセスできること', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin);

    $response = $this->get('/admin');

    $response->assertStatus(200);
});
