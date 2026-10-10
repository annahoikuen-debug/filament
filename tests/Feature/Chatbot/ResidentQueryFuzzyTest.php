<?php

use App\Enums\ResidentStatus;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use App\Services\Chatbot\ResidentQueryService;

beforeEach(function () {
    $this->facilityA = Facility::factory()->create(['name' => '施設A']);
    $this->facilityB = Facility::factory()->create(['name' => '施設B']);

    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->facilityAdminA = User::factory()->facilityAdmin()->create(['facility_id' => $this->facilityA->id]);

    $this->service = app(ChatbotService::class);
    $this->queryService = app(ResidentQueryService::class);

    // 施設Aの入居者
    $this->residentA1 = Resident::factory()->create([
        'facility_id' => $this->facilityA->id,
        'room_number' => '101',
        'name' => '山田太郎',
        'name_kana' => 'ヤマダタロウ',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);

    // 施設Bの入居者（同名誤字に似た名前）
    $this->residentB = Resident::factory()->create([
        'facility_id' => $this->facilityB->id,
        'room_number' => '201',
        'name' => '山田太朗',
        'name_kana' => 'ヤマダタロウ',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 55000,
        'base_management_fee' => 20000,
    ]);
});

test('誤字（山田太朗）で類似の入居者（山田太郎）が見つかること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太朗の情報を教えてください'),
        $this->facilityAdminA
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('山田太郎')
        ->and($response->reply)->toContain('101号室');
});

test('ひらがなカナ（やまだたろう）で入居者が見つかること', function () {
    $response = $this->service->handle(
        new ChatRequest('やまだたろうの情報を教えてください'),
        $this->facilityAdminA
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('山田太郎')
        ->and($response->reply)->toContain('101号室');
});

test('複数候補が存在する場合は確認応答になること', function () {
    // 施設Aに類似の名前を追加
    Resident::factory()->create([
        'facility_id' => $this->facilityA->id,
        'room_number' => '102',
        'name' => '山田次郎',
        'name_kana' => 'ヤマダジロウ',
        'status' => ResidentStatus::Active,
    ]);

    // 「山田三郎」と質問すると、山田太郎・山田次郎の2件が類似度0.75で引っかかる
    $response = $this->service->handle(
        new ChatRequest('山田三郎の情報を教えてください'),
        $this->facilityAdminA
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('どちらの入居者様ですか')
        ->and($response->reply)->toContain('山田太郎')
        ->and($response->reply)->toContain('山田次郎');
});

test('曖昧検索でも施設スコープが維持され、他施設の入居者は見つからないこと', function () {
    // facilityAdminA から施設Bの residentB を質問
    $response = $this->service->handle(
        new ChatRequest('201号室の情報を教えてください'),
        $this->facilityAdminA
    );

    expect($response->reply)->toContain('見つかりませんでした');
});

test('しきい値未満の全く異なる名前は「見つかりません」になること', function () {
    $response = $this->service->handle(
        new ChatRequest('佐々木小次郎の情報を教えてください'),
        $this->facilityAdminA
    );

    expect($response->reply)->toContain('見つかりませんでした');
});

test('既存の完全一致検索（山田太郎）がスコア1.0で即座に動作すること', function () {
    $candidates = $this->queryService->findCandidates('山田太郎', $this->facilityAdminA);

    expect($candidates)->not()->toBeEmpty()
        ->and($candidates->first()['score'])->toBe(1.0)
        ->and($candidates->first()['resident']->id)->toBe($this->residentA1->id);
});
