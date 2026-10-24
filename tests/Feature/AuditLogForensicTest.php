<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Audit\AuditLogExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogForensicTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_records_financial_and_auth_operations(): void
    {
        $user = User::factory()->create(['name' => '監査太郎']);

        activity('financial_operation')
            ->causedBy($user)
            ->withProperties(['ip' => '192.168.1.100', 'action' => 'zengin_export'])
            ->log('全銀振替データをダウンロードしました');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financial_operation',
            'description' => '全銀振替データをダウンロードしました',
        ]);
    }

    public function test_audit_log_csv_export_generates_bom_utf8_csv(): void
    {
        $user = User::factory()->create(['name' => '経理花子']);

        activity('financial_operation')
            ->causedBy($user)
            ->withProperties(['ip' => '10.0.0.1'])
            ->log('請求書 #1001 金額手動修正');

        $service = new AuditLogExportService;
        $csv = $service->exportCsv();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('経理花子', $csv);
        $this->assertStringContainsString('請求書 #1001 金額手動修正', $csv);
        $this->assertStringContainsString('financial_operation', $csv);
    }
}
