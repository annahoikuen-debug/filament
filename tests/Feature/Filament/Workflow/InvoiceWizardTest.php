<?php

use App\Models\User;
use App\Filament\Resources\MonthlyInvoiceResource;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('MonthlyInvoiceResource index page is accessible', function () {
    $response = $this->get(MonthlyInvoiceResource::getUrl('index'));
    $response->assertSuccessful();
});

test('MonthlyInvoiceResource has proper page configuration', function () {
    $pages = MonthlyInvoiceResource::getPages();
    expect(isset($pages['index']))->toBeTrue()
        ->and(isset($pages['create']))->toBeTrue()
        ->and(isset($pages['edit']))->toBeTrue();
});