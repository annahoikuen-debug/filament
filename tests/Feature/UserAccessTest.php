<?php

use App\Models\User;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Models\DailyCharge;
use App\Filament\Resources\ResidentResource;
use App\Filament\Resources\MonthlyInvoiceResource;
use App\Filament\Resources\DailyChargeResource;
use App\Enums\ResidentStatus;

beforeEach(function () {
    // Create 2 facilities
    $this->facility1 = Facility::factory()->create(['name' => '施設A']);
    $this->facility2 = Facility::factory()->create(['name' => '施設B']);

    // Create corporate admin (access to all facilities)
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();

    // Create facility admins (each restricted to their own facility)
    $this->facilityAdmin1 = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility1->id]);
    $this->facilityAdmin2 = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility2->id]);

    // Create test data for facility 1
    $this->resident1 = Resident::factory()->create([
        'facility_id' => $this->facility1->id,
        'room_number' => '101',
        'name' => '施設A入居者',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);

    $this->resident2 = Resident::factory()->create([
        'facility_id' => $this->facility1->id,
        'room_number' => '102',
        'name' => '施設A入居者2',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);

    // Create test data for facility 2
    $this->resident3 = Resident::factory()->create([
        'facility_id' => $this->facility2->id,
        'room_number' => '201',
        'name' => '施設B入居者',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);

    // Create monthly invoices
    $this->invoice1 = MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility1->id,
        'resident_id' => $this->resident1->id,
        'billing_year_month' => '2026-01',
        'status' => \App\Enums\InvoiceStatus::Billed,
    ]);

    $this->invoice2 = MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility2->id,
        'resident_id' => $this->resident3->id,
        'billing_year_month' => '2026-01',
        'status' => \App\Enums\InvoiceStatus::Billed,
    ]);

    // Create daily charges
    $this->charge1 = DailyCharge::factory()->create([
        'facility_id' => $this->facility1->id,
        'resident_id' => $this->resident1->id,
        'date' => '2026-01-15',
        'quantity' => 1,
        'unit_price' => 1000,
    ]);

    $this->charge2 = DailyCharge::factory()->create([
        'facility_id' => $this->facility2->id,
        'resident_id' => $this->resident3->id,
        'date' => '2026-01-15',
        'quantity' => 1,
        'unit_price' => 1000,
    ]);
});

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

// ===== Multi-facility access control tests =====

test('法人管理者は全施設の入居者を閲覧できること', function () {
    $this->actingAs($this->corporateAdmin);

    $response = $this->get(ResidentResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee('施設A入居者');
    $response->assertSee('施設A入居者2');
    $response->assertSee('施設B入居者');
});

test('施設管理者は自施設の入居者のみ閲覧できること', function () {
    $this->actingAs($this->facilityAdmin1);

    $response = $this->get(ResidentResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee('施設A入居者');
    $response->assertSee('施設A入居者2');
    $response->assertDontSee('施設B入居者');
});

test('施設管理者は他施設の入居者を閲覧できないこと', function () {
    $this->actingAs($this->facilityAdmin1);

    // ResidentResource doesn't have a 'view' page, use 'edit' instead
    $response = $this->get(ResidentResource::getUrl('edit', ['record' => $this->resident3]));

    // Should not be able to access other facility's resident
    expect(in_array($response->status(), [302, 403, 404]))->toBeTrue();
});

test('法人管理者は全施設の月次請求を閲覧できること', function () {
    $this->actingAs($this->corporateAdmin);

    $response = $this->get(MonthlyInvoiceResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee($this->invoice1->billing_year_month);
    $response->assertSee($this->invoice2->billing_year_month);
});

test('施設管理者は自施設の月次請求のみ閲覧できること', function () {
    $this->actingAs($this->facilityAdmin1);

    $response = $this->get(MonthlyInvoiceResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee($this->invoice1->resident->name); // 施設A入居者
    $response->assertDontSee($this->invoice2->resident->name); // 施設B入居者
});

test('施設管理者は他施設の月次請求を閲覧できないこと', function () {
    $this->actingAs($this->facilityAdmin1);

    // MonthlyInvoiceResource doesn't have a 'view' page, use 'edit' instead
    $response = $this->get(MonthlyInvoiceResource::getUrl('edit', ['record' => $this->invoice2]));

    // Should not be able to access other facility's invoice
    expect(in_array($response->status(), [302, 403, 404]))->toBeTrue();
});

test('法人管理者は全施設の自費記録を閲覧できること', function () {
    $this->actingAs($this->corporateAdmin);

    $response = $this->get(DailyChargeResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee('施設A入居者');
    $response->assertSee('施設B入居者');
});

test('施設管理者は自施設の自費記録のみ閲覧できること', function () {
    $this->actingAs($this->facilityAdmin1);

    $response = $this->get(DailyChargeResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee('施設A入居者');
    $response->assertDontSee('施設B入居者');
});

test('施設管理者は他施設の自費記録を閲覧できないこと', function () {
    $this->actingAs($this->facilityAdmin1);

    // DailyChargeResource doesn't have a 'view' page, use 'edit' instead
    $response = $this->get(DailyChargeResource::getUrl('edit', ['record' => $this->charge2]));

    // Should not be able to access other facility's charge
    expect(in_array($response->status(), [302, 403, 404]))->toBeTrue();
});

test('User::isCorporateAdmin()が正しく判定されること', function () {
    expect($this->corporateAdmin->isCorporateAdmin())->toBeTrue();
    expect($this->facilityAdmin1->isCorporateAdmin())->toBeFalse();
    expect($this->facilityAdmin2->isCorporateAdmin())->toBeFalse();
});

test('User::isFacilityAdmin()が正しく判定されること', function () {
    expect($this->corporateAdmin->isFacilityAdmin())->toBeFalse();
    expect($this->facilityAdmin1->isFacilityAdmin())->toBeTrue();
    expect($this->facilityAdmin2->isFacilityAdmin())->toBeTrue();
});

test('施設管理者のfacility_idが正しく設定されること', function () {
    expect($this->facilityAdmin1->facility_id)->toBe($this->facility1->id);
    expect($this->facilityAdmin2->facility_id)->toBe($this->facility2->id);
    expect($this->corporateAdmin->facility_id)->toBeNull();
});

test('法人管理者と施設管理者の両方がパネルにアクセス可能であること', function () {
    expect($this->corporateAdmin->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
    expect($this->facilityAdmin1->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
    expect($this->facilityAdmin2->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});
