<?php

use App\Enums\ResidentStatus;
use App\Models\Resident;

test('fillableフィールドのみが一括代入で設定されること', function () {
    $resident = Resident::create([
        'room_number' => '801',
        'name' => 'フィルテスト',
        'name_kana' => 'フィルテスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    expect($resident->exists)->toBeTrue()
        ->and($resident->name)->toBe('フィルテスト')
        ->and($resident->base_rent)->toBe(50000);
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    $resident = Resident::create([
        'id' => 99999,
        'room_number' => '802',
        'name' => 'ID無視テスト',
        'move_in_date' => '2026-01-01',
    ]);

    expect($resident->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $resident = Resident::create([
        'room_number' => '803',
        'name' => '不正フィールドテスト',
        'unknown_field' => 'テスト',
        'move_in_date' => '2026-01-01',
    ]);

    expect($resident->exists)->toBeTrue()
        ->and($resident->getAttribute('unknown_field'))->toBeNull();
});

test('fill可能フィールド一覧が正しいこと', function () {
    $resident = new Resident;

    expect($resident->getFillable())->toBe([
        'facility_id',
        'room_number',
        'name',
        'name_kana',
        'birth_date',
        'base_rent',
        'base_management_fee',
        'status',
        'move_in_date',
        'move_out_date',
    ]);
});
