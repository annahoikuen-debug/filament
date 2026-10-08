<?php

namespace App\Providers;

use App\Services\Pdf\Contracts\FontRegistryInterface;
use App\Services\Pdf\Contracts\RendererInterface;
use App\Services\Pdf\Fonts\WindowsFontRegistry;
use App\Services\Pdf\InvoicePdfGenerator;
use App\Services\Pdf\DataProviders\InvoiceDataProvider;
use App\Services\Pdf\Renderers\DomPdfRenderer;
use App\Services\Pdf\Renderers\HtmlRenderer;
use App\Services\Pdf\Templates\InvoiceTemplate;
use App\Services\Pdf\Templates\ReceiptTemplate;
use App\Services\Pdf\TemplateSettingsService;
use Illuminate\Support\ServiceProvider;

class PdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FontRegistryInterface::class, WindowsFontRegistry::class);

        $this->app->singleton(RendererInterface::class, function ($app) {
            $fontRegistry = $app->make(FontRegistryInterface::class);
            $config = config('pdf.default', []);
            
            return new DomPdfRenderer($fontRegistry, [
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'fontHeightRatio' => $config['line_height'] ?? 1.6,
                'defaultFont' => 'ipaexg',
            ]);
        });

        $this->app->bind(HtmlRenderer::class);

        $this->app->singleton(InvoiceDataProvider::class);

        $this->app->singleton(InvoiceTemplate::class);
        $this->app->singleton(ReceiptTemplate::class);

        $this->app->singleton(TemplateSettingsService::class);

        // InvoicePdfGeneratorに具体的なテンプレートクラスを注入
        $this->app->singleton(InvoicePdfGenerator::class, function ($app) {
            return new InvoicePdfGenerator(
                $app->make(InvoiceDataProvider::class),
                $app->make(InvoiceTemplate::class),
                $app->make(ReceiptTemplate::class),
                $app->make(RendererInterface::class),
            );
        });
    }

    public function boot(): void
    {
    }
}