<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Api\ExternalInvoiceController;
use App\Models\ApiToken;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalInvoiceControllerAuthTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private Facility $otherFacility;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create();
        $this->otherFacility = Facility::factory()->create();
        $this->resident = Resident::factory()->create(['facility_id' => $this->facility->id]);
    }

    /**
     * 有効なトークンを発行するヘルパー
     *
     * @return array{0: string, 1: ApiToken}
     */
    private function issueToken(Facility $facility, array $abilities = ['invoices.read', 'invoices.write'], ?\DateTimeInterface $expiresAt = null): array
    {
        return ApiToken::issue($facility->id, 'Test Token', $abilities, $expiresAt);
    }

    private function validPayload(): array
    {
        return [
            'facility_id' => $this->facility->id,
            'billing_year_month' => '2026-10',
            'external_system_name' => 'Test System',
            'invoices' => [
                [
                    'resident_id' => $this->resident->id,
                    'service_type' => 'visiting_care',
                    'external_invoice_number' => 'VC-202610-001',
                    'amount' => 45000,
                    'tax_amount' => 4500,
                    'tax_rate' => 10,
                ],
            ],
        ];
    }

    /** @test */
    public function bearer_tokenなしで_postすると401を返す()
    {
        $response = $this->postJson('/api/external-invoices', $this->validPayload());

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function 無効なトークンで_postすると401を返す()
    {
        $response = $this->postJson('/api/external-invoices', $this->validPayload(), [
            'Authorization' => 'Bearer sk_invalid_token_value',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function 期限切れトークンで_postすると401を返す()
    {
        [, $token] = $this->issueToken($this->facility, expiresAt: now()->subDay());

        $response = $this->postJson('/api/external-invoices', $this->validPayload(), [
            'Authorization' => "Bearer {$token->token}",
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function 権限不足トークンで_postすると401を返す()
    {
        [$plain, $token] = $this->issueToken($this->facility, ['invoices.read']);

        $response = $this->postJson('/api/external-invoices', $this->validPayload(), [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function 他施設の_idで_postすると403を返す()
    {
        [$plain, $token] = $this->issueToken($this->facility);

        $payload = $this->validPayload();
        $payload['facility_id'] = $this->otherFacility->id;

        $response = $this->postJson('/api/external-invoices', $payload, [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function 正しいトークンと施設idで_postするとデータを作成する()
    {
        [$plain, $token] = $this->issueToken($this->facility);

        $response = $this->postJson('/api/external-invoices', $this->validPayload(), [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('service_invoices', [
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'external_invoice_number' => 'VC-202610-001',
        ]);

        // 最終使用時刻が更新されている
        $this->assertNotNull($token->fresh()->last_used_at);
    }

    /** @test */
    public function 正しいトークンで_getすると自施設データのみ返却する()
    {
        $this->createServiceInvoice($this->facility->id, $this->resident->id);

        $otherResident = Resident::factory()->create(['facility_id' => $this->otherFacility->id]);
        $this->createServiceInvoice($this->otherFacility->id, $otherResident->id);

        [$plain, $token] = $this->issueToken($this->facility, ['invoices.read']);

        $response = $this->getJson('/api/external-invoices', [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertStatus(200);

        $facilityIds = collect($response->json('data'))->pluck('facility_id')->unique();
        $this->assertEquals([$this->facility->id], $facilityIds->values()->all());
    }

    /** @test */
    public function bearer_tokenなしで_getすると401を返す()
    {
        $this->getJson('/api/external-invoices')->assertStatus(401);
    }

    /** @test */
    public function store_singleも_bearer_tokenなしでは401を返す()
    {
        $this->postJson('/api/external-invoices/single', [
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
        ])->assertStatus(401);
    }

    /** @test */
    public function store_singleで他施設idを指定すると403を返す()
    {
        [$plain, $token] = $this->issueToken($this->facility);

        $response = $this->postJson('/api/external-invoices/single', [
            'facility_id' => $this->otherFacility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-10',
            'service_type' => 'visiting_care',
            'amount' => 10000,
            'tax_amount' => 1000,
            'tax_rate' => 10,
        ], [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function store_singleで正しいトークンなら作成される()
    {
        [$plain, $token] = $this->issueToken($this->facility);

        $response = $this->postJson('/api/external-invoices/single', [
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-10',
            'service_type' => 'visiting_care',
            'amount' => 10000,
            'tax_amount' => 1000,
            'tax_rate' => 10,
        ], [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function 期限切れトークンで_getすると401を返す()
    {
        [$plain, $token] = $this->issueToken($this->facility, expiresAt: now()->subHour());

        $this->getJson('/api/external-invoices', [
            'Authorization' => "Bearer {$plain}",
        ])->assertStatus(401);
    }

    /** @test */
    public function read権限のみのトークンで_getすると200を返す()
    {
        [$plain, $token] = $this->issueToken($this->facility, ['invoices.read']);

        $this->getJson('/api/external-invoices', [
            'Authorization' => "Bearer {$plain}",
        ])->assertStatus(200);
    }

    /** @test */
    public function read権限のみのトークンで_post_singleすると401を返す()
    {
        [$plain, $token] = $this->issueToken($this->facility, ['invoices.read']);

        $this->postJson('/api/external-invoices/single', [
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-10',
            'service_type' => 'visiting_care',
            'amount' => 10000,
            'tax_amount' => 1000,
            'tax_rate' => 10,
        ], [
            'Authorization' => "Bearer {$plain}",
        ])->assertStatus(401);
    }

    // ---- Unit 相当: authenticateRequest / authorizeFacility のロジック ----

    /** @test */
    public function find_valid_token_は有効トークンでレコードを返す()
    {
        [$plain, $token] = $this->issueToken($this->facility);

        $found = ApiToken::findValidToken($plain, 'invoices.write');

        $this->assertNotNull($found);
        $this->assertEquals($token->id, $found->id);
    }

    /** @test */
    public function find_valid_token_は無効トークンで_nullを返す()
    {
        $this->assertNull(ApiToken::findValidToken('sk_nonexistent', 'invoices.write'));
    }

    /** @test */
    public function find_valid_token_は期限切れトークンで_nullを返す()
    {
        [$plain, $token] = $this->issueToken($this->facility, expiresAt: now()->subDay());

        $this->assertNull(ApiToken::findValidToken($plain, 'invoices.write'));
    }

    /** @test */
    public function find_valid_token_は権限不足トークンで_nullを返す()
    {
        [$plain, $token] = $this->issueToken($this->facility, ['invoices.read']);

        $this->assertNull(ApiToken::findValidToken($plain, 'invoices.write'));
    }

    /**
     * ServiceInvoice を直接作成するヘルパー（ファクトリが存在しないため）
     */
    private function createServiceInvoice(int $facilityId, int $residentId): ServiceInvoice
    {
        return ServiceInvoice::create([
            'facility_id' => $facilityId,
            'resident_id' => $residentId,
            'billing_year_month' => '2026-10',
            'service_type' => 'visiting_care',
            'service_type_label' => '訪問介護',
            'external_system_name' => 'Test System',
            'amount' => 45000,
            'tax_amount' => 4500,
            'tax_rate' => 10,
            'status' => 'confirmed',
        ]);
    }

    /** @test */
    public function can_は保持する権限に対して_trueを返す()
    {
        [, $token] = $this->issueToken($this->facility, ['invoices.read', 'invoices.write']);

        $this->assertTrue($token->can('invoices.read'));
        $this->assertTrue($token->can('invoices.write'));
        $this->assertFalse($token->can('other.ability'));
    }

    /** @test */
    public function authorize_facility_は同一施設で_true_異なる施設で_false()
    {
        [$plain, $token] = $this->issueToken($this->facility);

        $controller = new ExternalInvoiceController;

        $this->assertTrue($this->invokeAuthorizeFacility($controller, $token, $this->facility->id));
        $this->assertFalse($this->invokeAuthorizeFacility($controller, $token, $this->otherFacility->id));
    }

    private function invokeAuthorizeFacility(
        ExternalInvoiceController $controller,
        ApiToken $token,
        int $facilityId
    ): bool {
        $method = new \ReflectionMethod($controller, 'authorizeFacility');
        $method->setAccessible(true);

        return $method->invoke($controller, $token, $facilityId);
    }
}
