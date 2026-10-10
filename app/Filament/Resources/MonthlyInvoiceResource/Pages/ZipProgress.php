<?php

namespace App\Filament\Resources\MonthlyInvoiceResource\Pages;

use App\Filament\Resources\MonthlyInvoiceResource;
use App\Services\InvoicePdfService;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Http\RedirectResponse;

class ZipProgress extends Page
{
    protected static string $resource = MonthlyInvoiceResource::class;

    protected static string $view = 'filament.resources.monthly-invoice-resource.pages.zip-progress';

    protected static ?string $title = 'ZIP生成進捗';

    protected static ?string $slug = 'zip-progress/{jobId}';

    public string $jobId = '';

    public array $progress = [
        'percent' => 0,
        'status' => 'starting',
        'message' => '処理を開始しています...',
        'updated_at' => '',
    ];

    public ?string $zipPath = null;

    public function mount(string $jobId): void
    {
        $this->jobId = $jobId;
        $this->loadProgress();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('一覧に戻る')
                ->url(static::getResource()::getUrl('index'))
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function loadProgress(): void
    {
        $pdfService = app(InvoicePdfService::class);
        $progress = $pdfService->getZipProgress($this->jobId);
        $result = $pdfService->getZipResult($this->jobId);

        if ($progress) {
            $this->progress = $progress;
        }

        if ($result) {
            $this->zipPath = $result;
        }
    }

    public function downloadZip(): RedirectResponse
    {
        return redirect()->route('invoices.zip-progress', ['jobId' => $this->jobId]);
    }

    public function isCompleted(): bool
    {
        return $this->progress['status'] === 'completed' && $this->zipPath;
    }

    public function isFailed(): bool
    {
        return $this->progress['status'] === 'failed';
    }

    public function getPollingInterval(): int
    {
        return 2000;
    }
}
