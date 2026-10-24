<?php

namespace App\Services\Audit;

use Spatie\Activitylog\Models\Activity;

class AuditLogExportService
{
    /**
     * 指定期間のアクティビティログをマスキング済みCSVとして出力
     */
    public function exportCsv(?string $startDate = null, ?string $endDate = null, ?string $logName = null): string
    {
        $query = Activity::with(['causer']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($logName) {
            $query->where('log_name', $logName);
        }

        $logs = $query->orderByDesc('created_at')->get();

        $rows = [];
        $rows[] = ['ログID', '日時', '操作者', '種別', '説明', '対象モデル', '対象ID', 'IPアドレス'];

        foreach ($logs as $log) {
            $causerName = $log->causer?->name ?? 'システム自動';
            $ip = $log->properties['ip'] ?? '-';

            $rows[] = [
                $log->id,
                $log->created_at->format('Y-m-d H:i:s'),
                $causerName,
                $log->log_name,
                $log->description,
                $log->subject_type,
                $log->subject_id,
                $ip,
            ];
        }

        $fp = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return "\xEF\xBB\xBF".$csv; // UTF-8 BOM
    }
}
