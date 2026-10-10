<?php

use App\Models\PdfTemplateSettings;
use App\Services\Pdf\TemplateSettingsService;

function templateData(array $overrides = []): array
{
    return array_merge([
        'key' => 'invoice',
        'locale' => 'ja',
        'theme' => 'standard',
        'name' => 'テストテンプレート',
        'description' => '説明',
        'paper_size' => 'A4',
        'paper_orientation' => 'portrait',
        'font_family' => 'ipaexg',
        'font_size' => 10,
        'is_active' => true,
        'is_default' => false,
    ], $overrides);
}

it('creates template with auto-incremented version', function () {
    $service = new TemplateSettingsService;

    $first = $service->create(templateData());
    $second = $service->create(templateData(['theme' => 'minimal', 'name' => '二番目']));

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2);
});

it('unsets other defaults when creating with is_default', function () {
    $service = new TemplateSettingsService;

    $first = $service->create(templateData(['is_default' => true]));
    $second = $service->create(templateData(['is_default' => true, 'theme' => 'minimal', 'name' => '新デフォルト']));

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('updates template and increments version', function () {
    $service = new TemplateSettingsService;

    $template = $service->create(templateData());
    $updated = $service->update($template, ['name' => '更新後', 'font_size' => 12]);

    expect($updated->name)->toBe('更新後')
        ->and($updated->font_size)->toBe(12)
        ->and($updated->version)->toBe(2);
});

it('update accepts explicit higher version', function () {
    $service = new TemplateSettingsService;

    $template = $service->create(templateData());
    $updated = $service->update($template, ['version' => 5]);

    expect($updated->version)->toBe(5);
});

it('update promotes default and unsets others', function () {
    $service = new TemplateSettingsService;

    $first = $service->create(templateData(['is_default' => true]));
    $second = $service->create(templateData(['theme' => 'minimal', 'name' => '二人目']));

    $service->update($second, ['is_default' => true]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('cannot delete default template', function () {
    $service = new TemplateSettingsService;

    $template = $service->create(templateData(['is_default' => true]));

    $service->delete($template);
})->throws(RuntimeException::class, 'デフォルトテンプレートは削除できません。');

it('deletes non-default template', function () {
    $service = new TemplateSettingsService;

    $template = $service->create(templateData());
    $id = $template->id;

    expect($service->delete($template))->toBeTrue()
        ->and(PdfTemplateSettings::find($id))->toBeNull();
});

it('gets settings merged with config and db template', function () {
    $service = new TemplateSettingsService;

    $service->create(templateData(['name' => 'DB側テンプレ']));

    $settings = $service->getSettings('invoice', 'ja', 'standard');

    expect($settings)->toBeArray();
});

it('gets settings from config fallback when no db template', function () {
    $service = new TemplateSettingsService;

    $settings = $service->getSettings('invoice', 'ja', 'standard');

    expect($settings)->toBeArray();
});

it('gets all templates filtered by key', function () {
    $service = new TemplateSettingsService;

    $service->create(templateData());
    $service->create(templateData(['key' => 'receipt', 'name' => 'レシート']));
    $service->create(templateData(['theme' => 'minimal', 'name' => '請求書2']));

    $all = $service->getAll();
    $invoiceOnly = $service->getAll('invoice');

    expect($all)->toHaveCount(3)
        ->and($invoiceOnly)->toHaveCount(2);
});

it('clears cache for specific key', function () {
    $service = new TemplateSettingsService;

    $service->getSettings('invoice', 'ja', 'standard');
    $service->clearCache('invoice', 'ja', 'standard');

    // 再取得してもエラーにならないこと
    expect($service->getSettings('invoice', 'ja', 'standard'))->toBeArray();
});

it('clears all cache', function () {
    $service = new TemplateSettingsService;

    $service->getSettings('invoice', 'ja', 'standard');
    $service->getSettings('receipt', 'en', 'minimal');
    $service->clearCache();

    expect($service->getSettings('invoice', 'ja', 'standard'))->toBeArray()
        ->and($service->getSettings('receipt', 'en', 'minimal'))->toBeArray();
});

it('seeds defaults', function () {
    $service = new TemplateSettingsService;

    $service->seedDefaults();

    expect(PdfTemplateSettings::count())->toBeGreaterThan(0);
});
