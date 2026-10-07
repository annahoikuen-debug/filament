<?php

use App\Models\Resident;

test('入居月の日割り計算が正しく行われること', function () {
    $resident = Resident::create([
        'room_number' => '301',
        'name' => '月中入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-10-15', // 10月15日入居
        'move_out_date' => null,
    ]);

    // 10月は31日、15日〜31日で17日在籍
    // 家賃: 60000 * 17/31 = 32903.2258... (四捨五入で 32903)
    // 管理費: 30000 * 17/31 = 16451.6129... (四捨五入で 16452)
    expect($resident->getLivingDaysInMonth(2026, 10))->toBe(17);
    expect($resident->getProratedAmount(2026, 10, 60000))->toBe(32903);
    expect($resident->getProratedAmount(2026, 10, 30000))->toBe(16452);
});

test('退去月の日割り計算が正しく行われること', function () {
    $resident = Resident::create([
        'room_number' => '302',
        'name' => '月中退去者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-10-15', // 10月15日退去
    ]);

    // 10月は31日、1日〜15日で15日在籍
    expect($resident->getLivingDaysInMonth(2026, 10))->toBe(15);
    expect($resident->getProratedAmount(2026, 10, 60000))->toBe(29032);
    expect($resident->getProratedAmount(2026, 10, 30000))->toBe(14516);
});

test('在籍期間外の月は0日となること', function () {
    $resident = Resident::create([
        'room_number' => '303',
        'name' => '前月退去者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-09-30', // 9月退去
    ]);

    expect($resident->getLivingDaysInMonth(2026, 10))->toBe(0);
    expect($resident->getProratedAmount(2026, 10, 60000))->toBe(0);
    expect($resident->getProratedAmount(2026, 10, 30000))->toBe(0);
});

test('月中入居かつ月中退去の場合の計算が正しいこと', function () {
    $resident = Resident::create([
        'room_number' => '304',
        'name' => '月中入退者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-10-10',
        'move_out_date' => '2026-10-20',
    ]);

    // 10月10日〜20日で11日在籍
    expect($resident->getLivingDaysInMonth(2026, 10))->toBe(11);
    expect($resident->getProratedAmount(2026, 10, 60000))->toBe(21290);
    expect($resident->getProratedAmount(2026, 10, 30000))->toBe(10645);
});
