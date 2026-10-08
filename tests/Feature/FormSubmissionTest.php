<?php

use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a catalog form submission', function () {
    $response = $this->postJson('/api/site-forms/catalog', [
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'special_nursing',
        'challenges' => ['calc_errors', 'manual_work'],
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('form_submissions', [
        'type' => 'catalog',
        'company' => '社会福祉法人テスト会',
        'email' => 'yamada@example.com',
    ]);
});

it('stores an inquiry form submission with payload', function () {
    $response = $this->postJson('/api/site-forms/inquiry', [
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'message' => '見積りをお願いします。',
        'inquiry_type' => 'estimate',
        'budget' => '10k_30k',
    ]);

    $response->assertStatus(201);

    $submission = FormSubmission::where('type', 'inquiry')->first();
    expect($submission->payload['message'])->toBe('見積りをお願いします。')
        ->and($submission->payload['inquiry_type'])->toBe('estimate');
});

it('rejects an unknown form type', function () {
    $this->postJson('/api/site-forms/unknown', ['name' => 'x'])
        ->assertStatus(404);
});

it('validates required fields per form type', function () {
    $this->postJson('/api/site-forms/catalog', ['name' => '山田'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company', 'email']);
});

it('validates email format', function () {
    $this->postJson('/api/site-forms/diagnosis', [
        'name' => '山田',
        'email' => 'not-an-email',
        'document_type' => 'care_certification',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
