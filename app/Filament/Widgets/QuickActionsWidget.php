<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AccountingExportProfileResource;
use App\Filament\Resources\DailyChargeResource;
use App\Filament\Resources\MonthlyInvoiceResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?string $heading = 'クイックアクション';

    public function view(): string
    {
        $dailyChargeCreateUrl = DailyChargeResource::getUrl('create');
        $monthlyInvoiceCreateUrl = MonthlyInvoiceResource::getUrl('create');
        $accountingExportUrl = AccountingExportProfileResource::getUrl('index');

        return '<div class="space-y-2 p-4">
            <a href="'.e($dailyChargeCreateUrl).'" class="btn btn-primary w-full">日次課金を追加</a>
            <a href="'.e($monthlyInvoiceCreateUrl).'" class="btn btn-success w-full">請求書を作成</a>
            <a href="'.e($accountingExportUrl).'" class="btn btn-info w-full">会計エクスポート</a>
        </div>';
    }
}
