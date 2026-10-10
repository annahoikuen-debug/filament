<?php

namespace App\Filament\Resources\MonthlyInvoiceResource\Pages;

use App\Filament\Resources\MonthlyInvoiceResource;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceCsvExportService;
use App\Services\InvoicePdfService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyInvoices extends ListRecords
{
    protected static string $resource = MonthlyInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. 月次請求データの一括生成
            Actions\Action::make('generateMonthlyInvoices')
                ->label('〇月の請求データを一括生成する')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->modalHeading('月次請求データの一括生成・再計算')
                ->modalDescription('指定年月の入居中Resident（25名）の固定費（家賃・管理費）と日々の自費記録を合算し、請求データを一括作成します。')
                ->modalSubmitActionLabel('一括生成を実行する')
                ->form([
                    Forms\Components\Select::make('year_month')
                        ->label('対象年月')
                        ->options($this->getYearMonthOptions())
                        ->default(Carbon::now()->format('Y-m'))
                        ->required(),

                    Forms\Components\Toggle::make('force_update')
                        ->label('確定済み（請求済・入金済）のデータも上書き再計算する')
                        ->default(false)
                        ->helperText('通常は未請求または新規データのみ再計算されます。金額に修正があった場合のみONにしてください。'),
                ])
                ->action(function (array $data, InvoiceCalculationService $service) {
                    $yearMonth = $data['year_month'];
                    $forceUpdate = (bool) ($data['force_update'] ?? false);

                    $stats = $service->generateForMonth($yearMonth, $forceUpdate);

                    $message = "【{$yearMonth}分】\n"
                        ."新規作成: {$stats['created']}件\n"
                        ."再計算更新: {$stats['updated']}件\n"
                        ."スキップ(確定済): {$stats['skipped']}件";

                    Notification::make()
                        ->title('請求データの一括生成が完了しました')
                        ->body($message)
                        ->success()
                        ->persistent()
                        ->send();
                }),

            // 2. 月次一括PDF生成（全25名分ZIP） - 非同期版
            Actions\Action::make('downloadMonthlyZip')
                ->label('一括PDF(ZIP)出力')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('info')
                ->modalHeading('全入居者の請求書PDF一括ZIPダウンロード')
                ->modalDescription('指定年月の全入居者（25名分）の請求書PDFを1つのZIPファイルにまとめてダウンロードします。大量データの場合は非同期処理で実行されます。')
                ->form([
                    Forms\Components\Select::make('year_month')
                        ->label('対象年月')
                        ->options($this->getYearMonthOptions())
                        ->default(Carbon::now()->format('Y-m'))
                        ->required(),
                ])
                ->action(function (array $data, InvoicePdfService $service) {
                    $yearMonth = $data['year_month'];
                    $jobId = $service->generateMonthlyZipAsync($yearMonth);

                    return redirect()->route('filament.admin.resources.monthly-invoices.zip-progress', ['jobId' => $jobId]);
                }),

            // 3. 会計連携用CSVエクスポート
            Actions\Action::make('exportCsv')
                ->label('会計CSV出力')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->modalHeading('会計ソフト連携・請求CSVエクスポート')
                ->form([
                    Forms\Components\Select::make('year_month')
                        ->label('対象年月')
                        ->options($this->getYearMonthOptions())
                        ->default(Carbon::now()->format('Y-m'))
                        ->required(),

                    Forms\Components\Radio::make('export_type')
                        ->label('出力種別')
                        ->options([
                            'accounting' => '会計仕訳連携用CSV（売掛金・家賃収入・管理費・自費）',
                            'list' => '請求・入金一覧CSV（Excel用台帳）',
                        ])
                        ->default('accounting')
                        ->required(),
                ])
                ->action(function (array $data, InvoiceCsvExportService $service) {
                    $yearMonth = $data['year_month'];
                    $type = $data['export_type'];

                    if ($type === 'accounting') {
                        $csv = $service->exportAccountingJournalCsv($yearMonth);
                        $fileName = "会計仕訳_{$yearMonth}.csv";
                    } else {
                        $csv = $service->exportMonthlyListCsv($yearMonth);
                        $fileName = "請求一覧台帳_{$yearMonth}.csv";
                    }

                    return response()->streamDownload(
                        fn () => print ($csv),
                        $fileName,
                        ['Content-Type' => 'text/csv; charset=UTF-8']
                    );
                }),

            Actions\CreateAction::make()
                ->label('手動作成')
                ->outlined(),
        ];
    }

    private function getYearMonthOptions(): array
    {
        $options = [];
        for ($i = -1; $i < 6; $i++) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->format('Y-m');
            $options[$key] = $date->format('Y年m月分').($i === 0 ? ' (当月)' : '');
        }

        return $options;
    }
}
