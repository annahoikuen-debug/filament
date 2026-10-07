<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\MonthlyInvoiceResource\Pages;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCsvExportService;
use App\Services\InvoicePdfService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MonthlyInvoiceResource extends Resource
{
    protected static ?string $model = MonthlyInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-yen';

    protected static ?string $navigationLabel = '月次請求データ';

    protected static ?string $modelLabel = '請求データ';

    protected static ?string $pluralModelLabel = '月次請求一覧';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('請求基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('billing_year_month')
                            ->label('請求年月')
                            ->placeholder('YYYY-MM (例: 2026-10)')
                            ->required()
                            ->maxLength(7),

                        Forms\Components\Select::make('resident_id')
                            ->label('入居者')
                            ->relationship('resident')
                            ->getOptionLabelFromRecordUsing(fn (Resident $record) => $record->full_title)
                            ->searchable(['room_number', 'name'])
                            ->required()
                            ->modifyQueryUsing(fn (Builder $query) => $query->when(
                                Auth::user()?->isFacilityAdmin() && Auth::user()?->facility_id,
                                fn ($q) => $q->where('facility_id', Auth::user()->facility_id)
                            )),

                        Forms\Components\Select::make('facility_id')
                            ->label('施設')
                            ->relationship('facility', 'name')
                            ->required()
                            ->visible(fn () => Auth::user()?->isCorporateAdmin())
                            ->default(fn () => Auth::user()?->facility_id),

                        Forms\Components\Select::make('status')
                            ->label('請求ステータス')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Unbilled)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('金額明細')
                    ->schema([
                        Forms\Components\TextInput::make('rent_subtotal')
                            ->label('家賃小計')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('management_fee_subtotal')
                            ->label('管理費小計')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('service_subtotal')
                            ->label('自費サービス小計')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('合計請求金額')
                            ->numeric()
                            ->prefix('¥')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Observerにより家賃・管理費・自費の合算値が自動計算されます。'),
                    ])->columns(2),

                Forms\Components\Section::make('入金・領収書管理')
                    ->schema([
                        Forms\Components\DatePicker::make('paid_at')
                            ->label('入金確認日')
                            ->native(false)
                            ->displayFormat('Y/m/d'),

                        Forms\Components\Select::make('payment_method')
                            ->label('入金方法')
                            ->options(PaymentMethod::class)
                            ->native(false),

                        Forms\Components\TextInput::make('receipt_number')
                            ->label('領収書番号')
                            ->maxLength(50)
                            ->placeholder('例: REC-202610-001'),
                    ])->columns(3)->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('billing_year_month')
                    ->label('請求年月')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resident.room_number')
                    ->label('部屋番号')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resident.name')
                    ->label('入居者氏名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rent_subtotal')
                    ->label('家賃')
                    ->money('JPY')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('management_fee_subtotal')
                    ->label('管理費')
                    ->money('JPY')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_subtotal')
                    ->label('自費小計')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('合計請求額')
                    ->money('JPY')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('JPY')->label('請求総計')),

                Tables\Columns\TextColumn::make('status')
                    ->label('ステータス')
                    ->badge(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('入金日')
                    ->date('m/d')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('入金方法')
                    ->badge()
                    ->toggleable(),
            ])
            ->defaultSort('billing_year_month', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('billing_year_month')
                    ->label('請求年月')
                    ->options(fn () => MonthlyInvoice::query()->distinct()->pluck('billing_year_month', 'billing_year_month')),

                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options(InvoiceStatus::class),

                Tables\Filters\SelectFilter::make('facility_id')
                    ->label('施設')
                    ->relationship('resident.facility', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                // 1. 請求書PDFダウンロード
                Tables\Actions\Action::make('downloadPdf')
                    ->label('請求書PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function (MonthlyInvoice $record, InvoicePdfService $service) {
                        $pdf = $service->generateInvoicePdf($record, $record->resident->facility?->toConfigArray());
                        $fileName = sprintf(
                            '請求書_%s_%s号室_%s様.pdf',
                            $record->billing_year_month,
                            $record->resident->room_number,
                            $record->resident->name
                        );

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            $fileName,
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                // 2. 入金消込アクション
                Tables\Actions\Action::make('markPayment')
                    ->label('入金消込')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MonthlyInvoice $record) => $record->status !== InvoiceStatus::Paid)
                    ->form([
                        Forms\Components\DatePicker::make('paid_at')
                            ->label('入金確認日')
                            ->default(now())
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('payment_method')
                            ->label('入金区分')
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::BankTransfer)
                            ->required(),
                    ])
                    ->action(function (MonthlyInvoice $record, array $data) {
                        $method = $data['payment_method'] instanceof PaymentMethod
                            ? $data['payment_method']
                            : PaymentMethod::from($data['payment_method']);

                        $record->markAsPaid(
                            $method,
                            $data['paid_at']
                        );

                        Notification::make()
                            ->title("入金消込が完了しました (領収書番号: {$record->receipt_number})")
                            ->success()
                            ->send();
                    }),

                // 3. 領収書PDFダウンロード (入金済みのレコードのみ表示)
                Tables\Actions\Action::make('downloadReceipt')
                    ->label('領収書PDF')
                    ->icon('heroicon-o-document-check')
                    ->color('warning')
                    ->visible(fn (MonthlyInvoice $record) => $record->status === InvoiceStatus::Paid)
                    ->action(function (MonthlyInvoice $record, InvoicePdfService $service) {
                        $pdf = $service->generateReceiptPdf($record, $record->resident->facility?->toConfigArray());
                        $fileName = sprintf(
                            '領収証_%s_%s号室_%s様.pdf',
                            $record->billing_year_month,
                            $record->resident->room_number,
                            $record->resident->name
                        );

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            $fileName,
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                // 編集アクション: アーカイブ済み（請求済・入金済）は非表示
                Tables\Actions\EditAction::make()
                    ->visible(fn (MonthlyInvoice $record) => $record->status && ! in_array($record->status, [InvoiceStatus::Paid, InvoiceStatus::Billed], true)
                ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAsBilled')
                        ->label('一括「請求済」に変更')
                        ->icon('heroicon-o-envelope')
                        ->action(fn ($records) => $records->each->update(['status' => InvoiceStatus::Billed]))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('bulkMarkAsPaid')
                        ->label('一括「入金済（口座振替）」に変更')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records) {
                            $records->each(function (MonthlyInvoice $inv) {
                                $inv->markAsPaid(PaymentMethod::DirectDebit);
                            });
                        })
                        ->requiresConfirmation(),

                    // CSVエクスポート: 請求・入金一覧
                    Tables\Actions\BulkAction::make('exportMonthlyListCsv')
                        ->label('請求・入金一覧CSV出力')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('year_month')
                                ->label('請求年月')
                                ->options(fn () => MonthlyInvoice::query()->distinct()->pluck('billing_year_month', 'billing_year_month')->toArray())
                                ->required()
                                ->default(now()->format('Y-m'))
                                ->native(false),
                        ])
                        ->action(function (array $data) {
                            $service = app(InvoiceCsvExportService::class);
                            $csv = $service->exportMonthlyListCsv($data['year_month']);
                            $fileName = "請求入金一覧_{$data['year_month']}.csv";

                            return response()->streamDownload(
                                fn () => print($csv),
                                $fileName,
                                ['Content-Type' => 'text/csv; charset=UTF-8']
                            );
                        })
                        ->requiresConfirmation(),

                    // CSVエクスポート: 会計仕訳CSV
                    Tables\Actions\BulkAction::make('exportAccountingJournalCsv')
                        ->label('会計仕訳CSV出力(弥生/freee/MF)')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('year_month')
                                ->label('請求年月')
                                ->options(fn () => MonthlyInvoice::query()->distinct()->pluck('billing_year_month', 'billing_year_month')->toArray())
                                ->required()
                                ->default(now()->format('Y-m'))
                                ->native(false),
                        ])
                        ->action(function (array $data) {
                            $service = app(InvoiceCsvExportService::class);
                            $csv = $service->exportAccountingJournalCsv($data['year_month']);
                            $fileName = "会計仕訳_{$data['year_month']}.csv";

                            return response()->streamDownload(
                                fn () => print($csv),
                                $fileName,
                                ['Content-Type' => 'text/csv; charset=UTF-8']
                            );
                        })
                        ->requiresConfirmation(),

                    // 削除バルクアクション: アーカイブ済みは対象外
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (MonthlyInvoice $record) => $record->status && ! in_array($record->status, [InvoiceStatus::Paid, InvoiceStatus::Billed], true)
                        ),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonthlyInvoices::route('/'),
            'create' => Pages\CreateMonthlyInvoice::route('/create'),
            'edit' => Pages\EditMonthlyInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * 施設によるデータ分離
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->isFacilityAdmin() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        return $query;
    }
}
