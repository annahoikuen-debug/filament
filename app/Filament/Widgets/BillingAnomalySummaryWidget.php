<?php

namespace App\Filament\Widgets;

use App\Services\BillingAnomalyDetector;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class BillingAnomalySummaryWidget extends Widget
{
    protected static ?string $heading = '請求データ整合性・異常値検知';

    protected static string $view = 'filament.widgets.billing-anomaly-summary-widget';

    public string $yearMonth;
    public int $errorCount = 0;
    public int $warningCount = 0;
    public array $topIssues = [];

    public function mount(): void
    {
        $this->yearMonth = now()->format('Y-m');
        $this->loadAnomalyData();
    }

    public function loadAnomalyData(): void
    {
        $cacheKey = "anomaly_summary_{$this->yearMonth}";
        $data = Cache::remember($cacheKey, 1800, function () {
            $detector = app(BillingAnomalyDetector::class);
            return $detector->detect($this->yearMonth);
        });

        $this->errorCount = $data['error_count'] ?? 0;
        $this->warningCount = $data['warning_count'] ?? 0;
        $allIssues = array_merge($data['errors'] ?? [], $data['warnings'] ?? []);
        $this->topIssues = array_slice($allIssues, 0, 5);
    }
}
