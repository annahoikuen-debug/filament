<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotSynonym;
use App\Models\Facility;
use App\Services\Chatbot\SynonymExpander;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SynonymDbTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_db_synonyms_are_merged_with_config(): void
    {
        ChatbotSynonym::create(['from_word' => '振替日', 'to_word' => '口座振替日', 'is_active' => true]);

        $expander = SynonymExpander::fromDatabase();

        $this->assertSame('口座振替日の請求', $expander->expand('振替日の請求'));
        // config の同義語も引き続き有効
        $this->assertSame('口座振替', $expander->expand('引き落とし'));
    }

    public function test_inactive_synonyms_are_ignored(): void
    {
        ChatbotSynonym::create(['from_word' => '振替日', 'to_word' => '口座振替日', 'is_active' => false]);

        $expander = SynonymExpander::fromDatabase();

        $this->assertSame('振替日', $expander->expand('振替日'));
    }

    public function test_facility_specific_takes_priority_over_global(): void
    {
        $facility = Facility::factory()->create();
        ChatbotSynonym::create(['facility_id' => null, 'from_word' => '部署代', 'to_word' => '全館共通語', 'is_active' => true]);
        ChatbotSynonym::create(['facility_id' => $facility->id, 'from_word' => '部署代', 'to_word' => '施設固有語', 'is_active' => true]);

        $facilityExpander = SynonymExpander::fromDatabase($facility->id);
        $this->assertSame('施設固有語', $facilityExpander->expand('部署代'));

        $globalExpander = SynonymExpander::fromDatabase(null);
        $this->assertSame('全館共通語', $globalExpander->expand('部署代'));
    }

    public function test_config_takes_priority_over_db(): void
    {
        // config に '未入金' => '未払い' が定義済み。DB で逆方向を登録しても config 優先
        ChatbotSynonym::create(['from_word' => '未入金', 'to_word' => '遅延', 'is_active' => true]);

        $expander = SynonymExpander::fromDatabase();

        $this->assertSame('未払い', $expander->expand('未入金'));
    }

    public function test_cache_is_flushed_on_save(): void
    {
        // キャッシュを温める
        SynonymExpander::fromDatabase();
        $this->assertTrue(Cache::has('chatbot_synonyms:global'));

        ChatbotSynonym::create(['from_word' => 'あ', 'to_word' => 'い', 'is_active' => true]);

        // Observer によりキャッシュ無効化されている
        $this->assertFalse(Cache::has('chatbot_synonyms:global'));
    }

    public function test_plain_constructor_still_works_without_db(): void
    {
        // 後方互換: 従来のコンストラクタ注入方式はそのまま動く
        $expander = new SynonymExpander(['レセプト' => '請求書']);

        $this->assertSame('請求書の締め日', $expander->expand('レセプトの締め日'));
    }
}
