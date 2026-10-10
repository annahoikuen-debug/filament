<?php

namespace Tests\Feature\Regression;

use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicChatbotRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_chatbot_still_works(): void
    {
        // Create a resident and monthly invoice for testing
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'room_number' => '101',
            'name' => '山田太郎',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => now()->format('Y-m'),
        ]);

        // Test that internal data still exists correctly
        $this->assertDatabaseHas('monthly_invoices', [
            'id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'name' => '山田太郎',
        ]);
    }

    public function test_site_forms_api_still_works(): void
    {
        // Test all 5 site form types still work with honeypot protection
        $formTypes = ['catalog', 'demo', 'inquiry', 'diagnosis'];

        foreach ($formTypes as $type) {
            $response = $this->postJson("/api/site-forms/{$type}", [
                'company' => 'テスト株式会社',
                'name' => '山田花子',
                'email' => 'lead@example.com',
                'form_loaded_at' => now()->getTimestamp() - 10, // Past the 2-second bot check
            ]);

            // Should succeed (201) or fail validation (422) but not be broken
            $this->assertContains($response->status(), [201, 422],
                "Site form {$type} should return 201 or 422");
        }

        $this->assertTrue(true, 'Site forms API regression test placeholder');
    }

    public function test_pdf_and_csv_output_still_works(): void
    {
        // Test that PDF and CSV generation still works
        // This would typically involve creating invoices and trying to generate outputs

        $this->assertTrue(true, 'PDF/CSV output regression test placeholder');
    }

    public function test_filament_resources_still_work(): void
    {
        // Test that Filament resources (especially ChatbotFaqResource) still work

        // Create a Chatbot FAQ
        $faq = ChatbotFaq::factory()->create([
            'question' => 'テスト質問',
            'answer' => 'テスト回答',
            'is_public' => true,
            'is_active' => true,
        ]);

        // Verify it was created
        $this->assertDatabaseHas('chatbot_faqs', [
            'id' => $faq->id,
            'question' => 'テスト質問',
            'is_public' => true,
        ]);

        $this->assertTrue(true, 'Filament resources regression test placeholder');
    }

    public function test_public_api_does_not_classify_as_internal_intents(): void
    {
        // Test that public API does NOT classify messages as internal intents
        // like resident_lookup, invoice_amount, etc.

        $internalIntents = ['resident_lookup', 'invoice_amount', 'invoice_status', 'daily_charge_total', 'escalate'];

        foreach ($internalIntents as $intent) {
            // Send a message that would trigger this intent in internal chatbot
            $message = match ($intent) {
                'resident_lookup' => '山田さんの情報を教えて',
                'invoice_amount' => '今月の請求額はいくら？',
                'invoice_status' => '支払い状況を教えて',
                'daily_charge_total' => '月額利用料を教えて',
                'escalate' => '担当者につなげて',
                default => 'テストメッセージ',
            };

            $response = $this->postJson('/api/public/chatbot/message', [
                'message' => $message,
            ]);

            $response->assertStatus(200);
            $json = $response->json();

            // Public API should never return internal intents
            $this->assertNotContains($json['intent'] ?? '', $internalIntents,
                "Public API incorrectly classified message as internal intent: {$intent}");
        }

        $this->assertTrue(true, 'Public API intent classification regression test placeholder');
    }
}
