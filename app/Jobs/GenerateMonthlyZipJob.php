<?php

namespace App\Jobs;

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use App\Services\Pdf\InvoicePdfGenerator;
use App\Services\FacilityConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GenerateMonthlyZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 120;

    public function __construct(
        public string $yearMonth,
        public ?int $facilityId = null,
        public string $jobId = '',
    ) {}

    public function handle(InvoicePdfService $pdfService, FacilityConfigService $configService): void
    {
        $generator = app(InvoicePdfGenerator::class);
        $this->jobId = $this->jobId ?: $this->getJobId();
        $progressKey = "zip_progress_{$this->jobId}";

        try {
            $this->updateProgress($progressKey, 0, 'starting', 'ZIP生成を開始します');

            $query = MonthlyInvoice::with('resident')
                ->forYearMonth($this->yearMonth);

            if ($this->facilityId) {
                $query->whereHas('resident', fn ($q) => $q->where('facility_id', $this->facilityId));
            }

            $totalInvoices = $query->count();

            if ($totalInvoices === 0) {
                $this->updateProgress($progressKey, 100, 'completed', '対象データがありません');
                $this->setResult($progressKey, '');

                return;
            }

            $this->updateProgress($progressKey, 5, 'processing', "対象請求書: {$totalInvoices}件");

            $zipPath = $this->buildZipWithStreaming($generator, $progressKey, $totalInvoices);

            $this->updateProgress($progressKey, 100, 'completed', 'ZIP生成完了');
            $this->setResult($progressKey, $zipPath);

            Log::info("Monthly ZIP generated for {$this->yearMonth}: {$zipPath}");
        } catch (\Throwable $e) {
            Log::error("Monthly ZIP generation failed for {$this->yearMonth}: {$e->getMessage()}");
            $this->updateProgress($progressKey, 0, 'failed', 'エラー: '.$e->getMessage());
            $this->setResult($progressKey, null);
            throw $e;
        }
    }

    /**
     * ストリーミング版：Generatorから直接ZIPに書き込み（中間ファイル不要、メモリ効率化）
     */
    private function buildZipWithStreaming(
        InvoicePdfGenerator $generator,
        string $progressKey,
        int $totalInvoices
    ): string {
        $zipPath = storage_path("app/temp/請求書一括_{$this->yearMonth}.zip");
        File::ensureDirectoryExists(dirname($zipPath));

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIPファイルの作成に失敗しました。');
        }

        try {
            // ストリーミング版で直接ZIPに書き込み
            $processed = $generator->generateMonthlyZipStream($this->yearMonth, $zip, $this->facilityId);

            // 進捗更新（完了時のみ）
            $this->updateProgress($progressKey, 95, 'processing', "完了: {$processed}/{$totalInvoices}件");

            $zip->close();

            return $zipPath;
        } catch (\Throwable $e) {
            $zip->close();
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            throw $e;
        }
    }

    /**
     * 従来版（互換性維持用・キャッシュ活用）
     */
    private function buildZipWithChunks(
        \Illuminate\Database\Eloquent\Builder $query,
        InvoicePdfService $pdfService,
        FacilityConfigService $configService,
        string $progressKey,
        int $totalInvoices
    ): string {
        $zipPath = storage_path("app/temp/請求書一括_{$this->yearMonth}.zip");
        File::ensureDirectoryExists(dirname($zipPath));

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIPファイルの作成に失敗しました。');
        }

        try {
            $processed = 0;
            $chunkSize = 10;

            $query->orderBy('id')
                ->chunkById($chunkSize, function ($invoices) use ($zip, $pdfService, $configService, $progressKey, $totalInvoices, &$processed) {
                    foreach ($invoices as $invoice) {
                        $pdfPath = $this->getCachedPdfPath($invoice);

                        if (! File::exists($pdfPath)) {
                            $facilityConfig = $this->facilityId ? $configService->getConfig($this->facilityId) : null;
                            $pdf = $pdfService->generateInvoicePdf($invoice, $facilityConfig);
                            $pdfContent = $pdf->output();

                            File::ensureDirectoryExists(dirname($pdfPath));
                            File::put($pdfPath, $pdfContent);
                        } else {
                            $pdfContent = File::get($pdfPath);
                        }

                        $fileName = sprintf(
                            '【%s号室】%s様_請求書_%s.pdf',
                            $invoice->resident->room_number,
                            $invoice->resident->name,
                            $invoice->billing_year_month
                        );

                        $zip->addFromString($fileName, $pdfContent);
                        $processed++;

                        $progress = min(5 + (int) (($processed / $totalInvoices) * 90), 95);
                        $this->updateProgress($progressKey, $progress, 'processing', "処理中: {$processed}/{$totalInvoices}件");
                    }

                    return true;
                });

            $zip->close();

            return $zipPath;
        } catch (\Throwable $e) {
            $zip->close();
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            throw $e;
        }
    }

    private function getCachedPdfPath(MonthlyInvoice $invoice): string
    {
        return storage_path("app/invoices/{$this->yearMonth}/invoice_{$invoice->id}.pdf");
    }

    private function updateProgress(string $key, int $percent, string $status, string $message): void
    {
        Cache::put($key, [
            'percent' => $percent,
            'status' => $status,
            'message' => $message,
            'updated_at' => now()->toISOString(),
        ], now()->addHours(24));
    }

    private function setResult(string $key, ?string $zipPath): void
    {
        Cache::put("{$key}_result", $zipPath, now()->addHours(24));
    }

    public static function getProgress(string $jobId): ?array
    {
        return Cache::get("zip_progress_{$jobId}");
    }

    public static function getResult(string $jobId): ?string
    {
        return Cache::get("zip_progress_{$jobId}_result");
    }

    public static function clearProgress(string $jobId): void
    {
        Cache::forget("zip_progress_{$jobId}");
        Cache::forget("zip_progress_{$jobId}_result");
    }
}