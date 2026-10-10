<?php

namespace Tests\Unit;

use App\Models\ChatEscalation;
use App\Models\ChatLog;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEscalationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillableフィールドで作成できること(): void
    {
        $user = User::factory()->facilityAdmin()->create();
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create(['facility_id' => $facility->id]);

        $escalation = ChatEscalation::create([
            'user_id' => $user->id,
            'session_id' => 'session-escalation-001',
            'summary' => 'オペレーターへのエスカレーション',
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'status' => 'pending',
        ]);

        $this->assertNotNull($escalation->id);
        $this->assertSame('session-escalation-001', $escalation->session_id);
        $this->assertSame('pending', $escalation->status);
    }

    public function test_user_resident_facilityリレーションが正しいこと(): void
    {
        $user = User::factory()->facilityAdmin()->create();
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create(['facility_id' => $facility->id]);

        $escalation = ChatEscalation::create([
            'user_id' => $user->id,
            'session_id' => 'session-escalation-002',
            'summary' => '要確認',
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'status' => 'pending',
        ]);

        $this->assertSame($user->id, $escalation->user->id);
        $this->assertSame($resident->id, $escalation->resident->id);
        $this->assertSame($facility->id, $escalation->facility->id);
    }
}
