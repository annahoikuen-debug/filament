<?php

use App\Enums\ResidentStatus;
use App\Filament\Resources\ResidentResource;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('ResidentResource一覧画面がエラーなく正常に表示されること', function () {
    $this->get(ResidentResource::getUrl('index'))
        ->assertSuccessful();
});

test('退去日 < 入居日 の場合にバリデーションエラーとなること', function () {
    $validator = Validator::make(
        [
            'move_in_date' => '2026-10-10',
            'move_out_date' => '2026-10-01',
        ],
        [
            'move_out_date' => 'after_or_equal:move_in_date',
        ]
    );

    expect($validator->fails())->toBeTrue();
});

test('退去日 = 入居日 の場合に有効であること', function () {
    $validator = Validator::make(
        [
            'move_in_date' => '2026-10-10',
            'move_out_date' => '2026-10-10',
        ],
        [
            'move_out_date' => 'after_or_equal:move_in_date',
        ]
    );

    expect($validator->passes())->toBeTrue();
});

test('退去日が null の場合に有効であること', function () {
    $validator = Validator::make(
        [
            'move_in_date' => '2026-10-10',
            'move_out_date' => null,
        ],
        [
            'move_out_date' => 'nullable|after_or_equal:move_in_date',
        ]
    );

    expect($validator->passes())->toBeTrue();
});

test('Resident作成が正常に動作すること', function () {
    $resident = Resident::create([
        'room_number' => '601',
        'name' => 'リソーステスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    expect($resident->exists)->toBeTrue()
        ->and($resident->room_number)->toBe('601');
});
