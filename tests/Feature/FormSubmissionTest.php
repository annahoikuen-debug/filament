<?php

use App\Mail\FormConfirmationMail;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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

it('rejects honeypot submissions silently without storing', function () {
    $response = $this->postJson('/api/site-forms/catalog', [
        'company' => 'スパム株式会社',
        'name' => 'ボット',
        'email' => 'bot@spam.example.com',
        'website' => 'http://spam.example.com',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $this->assertDatabaseCount('form_submissions', 0);
});

it('rejects too-fast submissions as bots', function () {
    $response = $this->postJson('/api/site-forms/inquiry', [
        'company' => 'スパム株式会社',
        'name' => 'ボット',
        'email' => 'bot@spam.example.com',
        'message' => 'spam',
        'form_loaded_at' => now()->getTimestamp(),
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $this->assertDatabaseCount('form_submissions', 0);
});

it('accepts normal-speed submissions', function () {
    $response = $this->postJson('/api/site-forms/inquiry', [
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'message' => 'よろしくお願いします。',
        'form_loaded_at' => now()->getTimestamp() - 10,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseCount('form_submissions', 1);
});

it('sends a confirmation email on submission', function () {
    Config::set('mail.default', 'smtp');
    Config::set('mail.mailers.smtp.host', 'smtp.test.local');
    Mail::fake();

    $this->postJson('/api/site-forms/catalog', [
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'form_loaded_at' => now()->getTimestamp() - 10,
    ])->assertStatus(201);

    Mail::assertQueued(FormConfirmationMail::class, function (FormConfirmationMail $mail) {
        return $mail->submission->type === 'catalog'
            && $mail->downloadUrl !== null;
    });
});

it('does not attach a download URL for non-downloadable form types', function () {
    Config::set('mail.default', 'smtp');
    Config::set('mail.mailers.smtp.host', 'smtp.test.local');
    Mail::fake();

    $this->postJson('/api/site-forms/inquiry', [
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'message' => 'よろしくお願いします。',
        'form_loaded_at' => now()->getTimestamp() - 10,
    ])->assertStatus(201);

    Mail::assertQueued(FormConfirmationMail::class, function (FormConfirmationMail $mail) {
        return $mail->downloadUrl === null;
    });
});

it('serves document downloads via signed URL', function () {
    $submission = FormSubmission::create([
        'type' => 'catalog',
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
    ]);

    Storage::fake('local');
    // 実ファイルの代わりにストレージへ配置できないため、実パスにダミーを作成して後片付け
    $path = storage_path('app/documents');
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
    $file = $path.DIRECTORY_SEPARATOR.'catalog.pdf';
    file_put_contents($file, 'dummy catalog pdf');

    $url = URL::temporarySignedRoute(
        'forms.download',
        now()->addMinutes(30),
        ['submission' => $submission->id, 'document' => 'catalog'],
    );

    $this->get($url)->assertOk();

    unlink($file);
});

it('rejects unsigned download URLs', function () {
    $submission = FormSubmission::create([
        'type' => 'catalog',
        'company' => '社会福祉法人テスト会',
        'name' => '山田 太郎',
        'email' => 'yamada@example.com',
    ]);

    $this->get("/forms/{$submission->id}/download/catalog")->assertForbidden();
});
