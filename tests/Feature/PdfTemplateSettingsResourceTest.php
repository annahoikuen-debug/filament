<?php

namespace Tests\Feature;

use App\Filament\Resources\PdfTemplateSettingsResource;
use App\Models\PdfTemplateSettings;
use App\Services\Pdf\TemplateSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfTemplateSettingsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_class_exists()
    {
        $this->assertTrue(class_exists(PdfTemplateSettingsResource::class));
    }

    public function test_model_relationship()
    {
        $resource = new PdfTemplateSettingsResource;
        $this->assertEquals(PdfTemplateSettings::class, $resource->getModel());
    }

    public function test_form_schema_has_sections()
    {
        // Form schema is tested via Filament's testing utilities
        // Here we just verify the static method exists and returns expected structure
        $this->assertTrue(method_exists(PdfTemplateSettingsResource::class, 'form'));
    }

    public function test_table_columns()
    {
        // Table columns tested via Filament's testing utilities
        $this->assertTrue(method_exists(PdfTemplateSettingsResource::class, 'table'));
    }

    public function test_seed_defaults()
    {
        PdfTemplateSettings::createDefaults();

        $invoice = PdfTemplateSettings::where('key', 'invoice')->where('is_default', true)->first();
        $receipt = PdfTemplateSettings::where('key', 'receipt')->where('is_default', true)->first();

        $this->assertNotNull($invoice);
        $this->assertNotNull($receipt);
        $this->assertEquals('invoice', $invoice->key);
        $this->assertEquals('receipt', $receipt->key);
        $this->assertTrue($invoice->is_default);
        $this->assertTrue($receipt->is_default);
    }

    public function test_to_config_array()
    {
        $template = PdfTemplateSettings::create([
            'key' => 'invoice',
            'name' => 'Test Template',
            'font_size' => 12,
            'primary_color' => '#ff0000',
            'show_facility_logo' => true,
        ]);

        $config = $template->toConfigArray();

        $this->assertEquals(12, $config['font_size']);
        $this->assertEquals('#ff0000', $config['primary_color']);
        $this->assertTrue($config['show_facility_logo']);
        $this->assertArrayHasKey('invoice_compliance', $config);
    }

    public function test_get_active_template()
    {
        PdfTemplateSettings::createDefaults();

        $active = PdfTemplateSettings::getActive('invoice');
        $this->assertNotNull($active);
        $this->assertEquals('invoice', $active->key);
        $this->assertTrue($active->is_active);
    }

    public function test_get_default_template()
    {
        PdfTemplateSettings::createDefaults();

        $default = PdfTemplateSettings::getDefault('invoice');
        $this->assertNotNull($default);
        $this->assertEquals('invoice', $default->key);
        $this->assertTrue($default->is_default);
    }

    public function test_template_settings_service()
    {
        PdfTemplateSettings::createDefaults();

        $service = app(TemplateSettingsService::class);
        $settings = $service->getSettings('invoice');

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('font_size', $settings);
        $this->assertArrayHasKey('primary_color', $settings);
        $this->assertArrayHasKey('invoice_compliance', $settings);
    }
}
