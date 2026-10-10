<?php

use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ResidentQueryService;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->facilityOther = Facility::factory()->create();

    $this->user = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facility->id,
    ]);

    $this->resident = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'name' => '佐藤 花子',
        'name_kana' => 'サトウ ハナコ',
        'room_number' => '501',
    ]);

    $this->queryService = app(ResidentQueryService::class);
});

test('Resident モデル保存時に normalized_name と normalized_kana が自動設定されること', function () {
    expect($this->resident->normalized_name)->not()->toBeNull()
        ->and($this->resident->normalized_name)->toBe('佐藤花子')
        ->and($this->resident->normalized_kana)->toBe('サトウハナコ');
});

test('ひらがな・空白ありの入力でも正規化インデックス経由で高スコアでヒットすること', function () {
    $candidates = $this->queryService->findCandidates('さとうはなこ', $this->user);

    expect($candidates)->not()->toBeEmpty()
        ->and($candidates->first()['resident']->id)->toBe($this->resident->id)
        ->and($candidates->first()['score'])->toBeGreaterThanOrEqual(0.9);
});

test('カナ前方一致（さとう）でも正規化クエリでヒットすること', function () {
    $candidates = $this->queryService->findCandidates('さとう', $this->user);

    expect($candidates)->not()->toBeEmpty()
        ->and($candidates->first()['resident']->id)->toBe($this->resident->id);
});

test('誤字（佐藤華子）でもフォールバック曖昧一致でヒットすること', function () {
    $candidates = $this->queryService->findCandidates('佐藤華子', $this->user);

    expect($candidates)->not()->toBeEmpty()
        ->and($candidates->first()['resident']->id)->toBe($this->resident->id)
        ->and($candidates->first()['score'])->toBeGreaterThanOrEqual(0.7);
});

test('他施設の入居者は正規化検索でも絶対にヒットしないこと', function () {
    $residentOther = Resident::factory()->create([
        'facility_id' => $this->facilityOther->id,
        'name' => '佐藤 太郎',
        'name_kana' => 'サトウ タロウ',
        'room_number' => '502',
    ]);

    $candidates = $this->queryService->findCandidates('さとうたろう', $this->user);
    expect($candidates)->toBeEmpty();
});
