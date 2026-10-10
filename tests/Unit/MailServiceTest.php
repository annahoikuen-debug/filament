<?php

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\MailService;
use Illuminate\Support\Facades\Mail;

function invoiceMailFacility(): array
{
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 10000,
        'service_subtotal' => 20000,
        'total_amount' => 80000,
        'status' => InvoiceStatus::Billed,
        'version' => 0,
    ]);

    return [$facility, $resident, $invoice];
}

it('returns false when mailer is log', function () {
    Config::set('mail.default', 'log');

    $service = new MailService;

    expect($service->isMailConfigured())->toBeFalse();
});

it('returns false when mailer is null', function () {
    Config::set('mail.default', 'null');

    $service = new MailService;

    expect($service->isMailConfigured())->toBeFalse();
});

it('returns false when smtp has empty host', function () {
    Config::set('mail.default', 'smtp');
    Config::set('mail.mailers.smtp.host', '');

    $service = new MailService;

    expect($service->isMailConfigured())->toBeFalse();
});

it('returns true when smtp is configured', function () {
    Config::set('mail.default', 'smtp');
    Config::set('mail.mailers.smtp.host', 'smtp.example.com');

    $service = new MailService;

    expect($service->isMailConfigured())->toBeTrue();
});

it('returns true for other mailers like sendmail', function () {
    Config::set('mail.default', 'sendmail');

    $service = new MailService;

    expect($service->isMailConfigured())->toBeTrue();
});

it('send returns false when mail is not configured', function () {
    Config::set('mail.default', 'log');
    [, , $invoice] = invoiceMailFacility();

    $mailable = new InvoiceMail($invoice, 'https://example.com/invoice.pdf');

    $service = new MailService;

    expect($service->send($mailable, 'to@example.com'))->toBeFalse();
});

it('send queues mailable when configured', function () {
    Config::set('mail.default', 'smtp');
    Config::set('mail.mailers.smtp.host', 'smtp.example.com');
    Mail::fake();

    [, , $invoice] = invoiceMailFacility();

    $mailable = new InvoiceMail($invoice, 'https://example.com/invoice.pdf');

    $service = new MailService;

    expect($service->send($mailable, 'to@example.com'))->toBeTrue();

    Mail::assertQueued(InvoiceMail::class, 1);
});

it('InvoiceMail builds envelope and content', function () {
    [, $resident, $invoice] = invoiceMailFacility();

    $mailable = new InvoiceMail($invoice, 'https://example.com/invoice.pdf', 'よろしくお願いします。');

    $envelope = $mailable->envelope();
    $content = $mailable->content();

    expect($envelope->subject)->toBe("【請求書】2026-03月分 {$resident->name}様")
        ->and($content->view)->toBe('emails.invoice');
});

it('InvoiceMail includes care services suffix', function () {
    [, , $invoice] = invoiceMailFacility();

    $mailable = new InvoiceMail($invoice, 'https://example.com/invoice.pdf', null, true);

    expect($mailable->envelope()->subject)->toContain('介護サービス含む');
});

it('send returns false and logs error when queueing throws', function () {
   Config::set('mail.default', 'smtp');
   Config::set('mail.mailers.smtp.host', 'smtp.example.com');
   Mail::fake();

   // Mailファサードを部分モックし、queue() が例外を投げるようにする
   Mail::shouldReceive('to')->andThrow(new \RuntimeException('queue failure'));

   [, , $invoice] = invoiceMailFacility();

   $mailable = new InvoiceMail($invoice, 'https://example.com/invoice.pdf');

   $service = new MailService;

   expect($service->send($mailable, 'to@example.com'))->toBeFalse();
});
