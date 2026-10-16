<?php

namespace Tests\Feature\Chatbot;

use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ResidentQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentNicknameLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Facility $facility;

    private ResidentQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create();
        $this->user = User::factory()->corporateAdmin()->create();
        $this->service = new ResidentQueryService;
    }

    public function test_nickname_exact_match_finds_resident(): void
    {
        $resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '山田 花子',
            'name_kana' => 'ヤマダ ハナコ',
            'nickname' => 'はなちゃん',
        ]);

        $candidates = $this->service->findCandidates('はなちゃん', $this->user);

        $this->assertFalse($candidates->isEmpty());
        $this->assertSame($resident->id, $candidates->first()['resident']->id);
    }

    public function test_nickname_is_normalized_on_save(): void
    {
        $resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '佐藤 次郎',
            'nickname' => 'じろうおじさん',
        ]);

        $this->assertNotNull($resident->refresh()->normalized_nickname);
    }

    public function test_nickname_hiragana_input_via_chat_api(): void
    {
        Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '鈴木 三郎',
            'nickname' => 'さぶちゃん',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => 'さぶちゃんの情報を教えてください',
            ]);

        $response->assertStatus(200);
        $this->assertSame('resident_lookup', $response->json('intent'));
        $this->assertStringContainsString('鈴木 三郎', $response->json('reply'));
    }

    public function test_empty_nickname_clears_normalized_value(): void
    {
        $resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '高橋 四郎',
            'nickname' => 'よんちゃん',
        ]);
        $this->assertNotNull($resident->refresh()->normalized_nickname);

        $resident->update(['nickname' => null]);
        $this->assertNull($resident->refresh()->normalized_nickname);
    }
}
