<?php

namespace Tests\Feature\Chatbot\Public;

use App\Mail\FormConfirmationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicLeadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_request_returns_lead_capture_with_demo_endpoint(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'デモを体験したい',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('intent', 'lead_capture');
        $this->assertSame('/api/site-forms/demo', $response->json('form.endpoint'));
    }

    public function test_catalog_request_returns_catalog_endpoint(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => '資料をダウンロードしたい',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('intent', 'lead_capture');
        $this->assertSame('/api/site-forms/catalog', $response->json('form.endpoint'));
    }

    public function test_response_contains_action_link_to_normal_form(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'デモを体験したい',
        ]);

        $response->assertStatus(200);
        $actionLinks = $response->json('action_links');
        $this->assertNotEmpty($actionLinks);
        $this->assertStringContainsString('/request/demo.html', $actionLinks[0]['url']);
    }

    public function test_mini_form_data_submits_to_existing_site_forms_api(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/site-forms/demo', [
            'company' => 'テスト株式会社',
            'name' => '山田花子',
            'email' => 'lead@example.com',
            'form_loaded_at' => now()->getTimestamp() - 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('form_submissions', [
            'type' => 'demo',
            'email' => 'lead@example.com',
        ]);
    }

    public function test_lead_submission_sends_confirmation_email(): void
    {
        // MailService がメール設定を検知できるよう smtp 設定を模擬
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.test.local');
        Mail::fake();

        $response = $this->postJson('/api/site-forms/catalog', [
            'company' => 'テスト株式会社',
            'name' => '山田花子',
            'email' => 'lead@example.com',
            'form_loaded_at' => now()->getTimestamp() - 10,
        ]);

        $response->assertStatus(201);

        Mail::assertQueued(FormConfirmationMail::class, function ($mail) {
            return $mail->hasTo('lead@example.com');
        });
    }
}
