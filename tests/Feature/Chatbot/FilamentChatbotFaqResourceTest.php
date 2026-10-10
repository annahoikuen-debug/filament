<?php

use App\Filament\Resources\ChatbotFaqResource;
use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->facilityAdmin = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility->id]);
});

test('ChatbotFaqResource一覧画面が正常に表示されること', function () {
    $this->actingAs($this->corporateAdmin);

    $this->get(ChatbotFaqResource::getUrl('index'))
        ->assertSuccessful();
});

test('corporate_admin でFAQ CRUDが可能であること', function () {
    $this->actingAs($this->corporateAdmin);

    $faq = ChatbotFaq::create([
        'question' => '利用料の支払い方法',
        'keywords' => ['支払い'],
        'answer' => '毎月25日に口座振込',
        'category' => '支払い',
    ]);

    expect($faq->exists)->toBeTrue();

    $faq->update(['answer' => '毎月25日に口座振込（更新）']);
    expect($faq->fresh()->answer)->toContain('更新');

    $faq->delete();
    expect(ChatbotFaq::count())->toBe(0);
});

test('facility_admin は自施設FAQと共通FAQのみ一覧に表示されること', function () {
    ChatbotFaq::create([
        'question' => '共通FAQ',
        'keywords' => ['共通'],
        'answer' => '共通回答',
        'facility_id' => null,
    ]);
    ChatbotFaq::create([
        'question' => '施設A FAQ',
        'keywords' => ['A'],
        'answer' => '施設A回答',
        'facility_id' => $this->facility->id,
    ]);
    $otherFacility = Facility::factory()->create();
    ChatbotFaq::create([
        'question' => '施設B FAQ',
        'keywords' => ['B'],
        'answer' => '施設B回答',
        'facility_id' => $otherFacility->id,
    ]);

    $query = ChatbotFaqResource::getEloquentQuery();
    // getEloquentQuery は Auth::user() を参照するため、ユーザー切替後に再取得
    $this->actingAs($this->facilityAdmin);
    $query = ChatbotFaqResource::getEloquentQuery();
    $results = $query->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('question'))->toContain('共通FAQ')
        ->and($results->pluck('question'))->toContain('施設A FAQ')
        ->and($results->pluck('question'))->not->toContain('施設B FAQ');
});

test('FAQの変更が監査ログに記録されること', function () {
    $this->actingAs($this->corporateAdmin);

    $faq = ChatbotFaq::create([
        'question' => '利用料の支払い方法',
        'keywords' => ['支払い'],
        'answer' => '毎月25日に口座振込',
        'category' => '支払い',
    ]);

    $faq->update(['answer' => '毎月25日に口座振込（更新）']);

    $log = Activity::where('log_name', 'chatbot_faq')
        ->where('subject_id', $faq->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull();
});
