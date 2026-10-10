<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\MonthlyInvoiceResource\Pages;
use App\Mail\InvoiceMail;
use App\Models\AccountingExportProfile;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCsvExportService;
use App\Services\InvoicePdfService;
use App\Services\MailService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class MonthlyInvoiceResource extends Resource
{
    protected static ?string $model = MonthlyInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-yen';

    protected static ?string $navigationLabel = '月次請求データ';

    protected static ?string $modelLabel = '請求データ';

    protected static ?string $pluralModelLabel = '月次請求一覧';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationGroup = '請求管理';

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

                        Forms\Components\Select::make('invoice_date_mode')
                            ->label('請求書日付モード')
                            ->options([
                                'auto' => '請求年月に基づく (自動)',
                                'manual' => '任意の日付を指定',
                            ])
                            ->default('auto')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('custom_invoice_date', null)),

                        Forms\Components\DatePicker::make('custom_invoice_date')
                            ->label('請求書日付 (任意)')
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->visible(fn (Forms\Get $get) => $get('invoice_date_mode') === 'manual')
                            ->helperText('請求書日付モードが「任意」の場合のみ入力'),

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

                        Forms\Components\Select::make('receipt_date_mode')
                            ->label('領収書日付モード')
                            ->options([
                                'auto' => '入金日に基づく (自動)',
                                'manual' => '任意の日付を指定',
                            ])
                            ->default('auto')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('custom_receipt_date', null)),

                        Forms\Components\DatePicker::make('custom_receipt_date')
                            ->label('領収書日付 (任意)')
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->visible(fn (Forms\Get $get) => $get('receipt_date_mode') === 'manual')
                            ->helperText('領収書日付モードが「任意」の場合のみ入力'),

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
            ->contentGrid([
                'md' => 1,
                'xl' => 2,
            ])
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
                    ->toggleable()
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('service_subtotal')
                    ->label('自費小計')
                    ->money('JPY')
                    ->sortable()
                    ->toggleable()
                    ->hiddenFrom('md'),

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

                        Forms\Components\Select::make('receipt_date_mode')
                            ->label('領収書日付モード')
                            ->options([
                                'auto' => '入金日に基づく (自動)',
                                'manual' => '任意の日付を指定',
                            ])
                            ->default('auto')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('custom_receipt_date', null)),

                        Forms\Components\DatePicker::make('custom_receipt_date')
                            ->label('領収書日付 (任意)')
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->visible(fn (Forms\Get $get) => $get('receipt_date_mode') === 'manual')
                            ->helperText('領収書日付モードが「任意」の場合のみ入力'),
                    ])
                    ->action(function (MonthlyInvoice $record, array $data) {
                        $method = $data['payment_method'] instanceof PaymentMethod
                            ? $data['payment_method']
                            : PaymentMethod::from($data['payment_method']);

                        $record->markAsPaid(
                            $method,
                            $data['paid_at']
                        );

                        // 領収書日付モードと任意日付を保存
                        $record->update([
                            'receipt_date_mode' => $data['receipt_date_mode'] ?? 'auto',
                            'custom_receipt_date' => $data['custom_receipt_date'] ?? null,
                        ]);

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

                // 4. 請求書プレビュー (HTML表示・新しいタブで開く)
                Tables\Actions\Action::make('previewInvoice')
                    ->label('請求書プレビュー')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->openUrlInNewTab()
                    ->url(fn (MonthlyInvoice $record): string => URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $record->id, 'type' => 'invoice'])),

                // 5. 領収書プレビュー (入金済みのみ・HTML表示・新しいタブで開く)
                Tables\Actions\Action::make('previewReceipt')
                    ->label('領収書プレビュー')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (MonthlyInvoice $record) => $record->status === InvoiceStatus::Paid)
                    ->openUrlInNewTab()
                    ->url(fn (MonthlyInvoice $record): string => URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $record->id, 'type' => 'receipt'])),

                // 6. 請求書メール送信
                Tables\Actions\Action::make('sendInvoiceEmail')
                    ->label('請求書送信')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('送信先メールアドレス')
                            ->email()
                            ->default(fn (MonthlyInvoice $record) => $record->resident->email ?? $record->resident->facility?->email)
                            ->required()
                            ->placeholder('example@domain.com'),
                        Forms\Components\Textarea::make('message')
                            ->label('添え書き（任意）')
                            ->rows(3)
                            ->placeholder('請求書に関するメッセージがあれば入力してください'),
                        Forms\Components\Toggle::make('include_care_services')
                            ->label('介護サービス請求書も同封する')
                            ->default(false)
                            ->helperText('確定済みの介護サービスPDFがあれば添付します'),
                    ])
                    ->action(function (MonthlyInvoice $record, array $data, MailService $mailService) {
                        // PDFプレビューURLを生成（認証不要の公開URL）
                        $pdfUrl = URL::signedRoute('invoices.preview', [
                            'invoice' => $record->id,
                            'type' => 'invoice',
                        ]);

                        $mailable = new InvoiceMail($record, $pdfUrl, $data['message'] ?? null, $data['include_care_services'] ?? false);
                        $sent = $mailService->send($mailable, $data['email']);

                        if ($sent) {
                            Notification::make()
                                ->title("請求書を {$data['email']} へ送信しました".($data['include_care_services'] ? '（介護サービス含む）' : ''))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('メール送信に失敗しました（メール設定を確認してください）')
                                ->danger()
                                ->send();
                        }
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
                            ->form([
                                Forms\Components\Select::make('receipt_date_mode')
                                    ->label('領収書日付モード')
                                    ->options([
                                        'auto' => '入金日に基づく (自動)',
                                        'manual' => '任意の日付を指定',
                                    ])
                                    ->default('auto')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('custom_receipt_date', null)),

                                Forms\Components\DatePicker::make('custom_receipt_date')
                                    ->label('領収書日付 (任意)')
                                    ->native(false)
                                    ->displayFormat('Y/m/d')
                                    ->visible(fn (Forms\Get $get) => $get('receipt_date_mode') === 'manual')
                                    ->helperText('領収書日付モードが「任意」の場合のみ入力'),
                            ])
                            ->action(function ($records, array $data) {
                                $records->each(function (MonthlyInvoice $inv) use ($data) {
                                    $inv->markAsPaid(
                                        PaymentMethod::DirectDebit,
                                        paidAt: now()->toDateString(),
                                        receiptDateMode: $data['receipt_date_mode'] ?? 'auto',
                                        customReceiptDate: $data['custom_receipt_date'] ?? null
                                    );
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
                                    fn () => print ($csv),
                                    $fileName,
                                    ['Content-Type' => 'text/csv; charset=UTF-8']
                                );
                            })
                            ->requiresConfirmation(),

                        // CSVエクスポート: 会計仕訳CSV (プロファイル対応版・介護サービス統合)
                        Tables\Actions\BulkAction::make('exportAccountingJournalCsv')
                            ->label('会計仕訳CSV出力(弥生/freee/MF/勘定奉行)')
                            ->icon('heroicon-o-document-text')
                            ->color('warning')
                            ->form([
                                Forms\Components\Select::make('year_month')
                                    ->label('請求年月')
                                    ->options(fn () => MonthlyInvoice::query()->distinct()->pluck('billing_year_month', 'billing_year_month')->toArray())
                                    ->required()
                                    ->default(now()->format('Y-m'))
                                    ->native(false),

                                Forms\Components\Select::make('software_type')
                                    ->label('会計ソフト')
                                    ->options([
                                        AccountingExportProfile::SOFTWARE_FREEE => 'freee',
                                        AccountingExportProfile::SOFTWARE_MF => 'MFクラウド会計',
                                        AccountingExportProfile::SOFTWARE_YAYOI => '弥生会計',
                                        AccountingExportProfile::SOFTWARE_KANJOBUGYO => '勘定奉行',
                                    ])
                                    ->default(AccountingExportProfile::SOFTWARE_FREEE)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('profile_id', null)),

                                Forms\Components\Select::make('profile_id')
                                    ->label('エクスポートプロファイル')
                                    ->options(function (Forms\Get $get) {
                                        $softwareType = $get('software_type') ?? AccountingExportProfile::SOFTWARE_FREEE;
                                        $facilityId = auth()->user()?->facility_id;
                                        if (! $facilityId) {
                                            return [];
                                        }
                                        $profiles = AccountingExportProfile::where('facility_id', $facilityId)
                                            ->where('software_type', $softwareType)
                                            ->where('is_active', true)
                                            ->get();

                                        return $profiles->pluck('name', 'id')->toArray();
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('デフォルトプロファイルを使用')
                                    ->helperText('未選択時はデフォルトプロファイルが使用されます'),

                                Forms\Components\Toggle::make('include_care_services')
                                    ->label('介護サービス請求を含める')
                                    ->default(true)
                                    ->helperText('確定済みの介護サービス請求も仕訳に含めます'),
                            ])
                            ->action(function (array $data, InvoiceCsvExportService $service) {
                                $csv = $service->exportAccountingJournalCsv(
                                    $data['year_month'],
                                    facilityId: auth()->user()?->facility_id,
                                    softwareType: $data['software_type'],
                                    profileId: $data['profile_id'] ?? null,
                                    includeCareServices: $data['include_care_services'] ?? true
                                );
                                $fileName = "会計仕訳_{$data['year_month']}".($data['include_care_services'] ? '_統合' : '_住居費のみ').'.csv';

                                return response()->streamDownload(
                                    fn () => print ($csv),
                                    $fileName,
                                    ['Content-Type' => 'text/csv; charset=UTF-8']
                                );
                            })
                            ->requiresConfirmation(),

                        // 仕訳プレビューアクション
                        Tables\Actions\BulkAction::make('previewAccountingJournal')
                            ->label('会計仕訳プレビュー')
                            ->icon('heroicon-o-eye')
                            ->color('info')
                            ->form([
                                Forms\Components\Select::make('year_month')
                                    ->label('請求年月')
                                    ->options(fn () => MonthlyInvoice::query()->distinct()->pluck('billing_year_month', 'billing_year_month')->toArray())
                                    ->required()
                                    ->default(now()->format('Y-m'))
                                    ->native(false),

                                Forms\Components\Select::make('software_type')
                                    ->label('会計ソフト')
                                    ->options([
                                        AccountingExportProfile::SOFTWARE_FREEE => 'freee',
                                        AccountingExportProfile::SOFTWARE_MF => 'MFクラウド会計',
                                        AccountingExportProfile::SOFTWARE_YAYOI => '弥生会計',
                                        AccountingExportProfile::SOFTWARE_KANJOBUGYO => '勘定奉行',
                                    ])
                                    ->default(AccountingExportProfile::SOFTWARE_FREEE)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('profile_id', null)),

                                Forms\Components\Select::make('profile_id')
                                    ->label('エクスポートプロファイル')
                                    ->options(function (Forms\Get $get) {
                                        $softwareType = $get('software_type') ?? AccountingExportProfile::SOFTWARE_FREEE;
                                        $facilityId = auth()->user()?->facility_id;
                                        if (! $facilityId) {
                                            return [];
                                        }
                                        $profiles = AccountingExportProfile::where('facility_id', $facilityId)
                                            ->where('software_type', $softwareType)
                                            ->where('is_active', true)
                                            ->get();

                                        return $profiles->pluck('name', 'id')->toArray();
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('デフォルトプロファイルを使用'),

                                Forms\Components\Toggle::make('include_care_services')
                                    ->label('介護サービス請求を含める')
                                    ->default(true)
                                    ->helperText('確定済みの介護サービス請求もプレビューに含めます'),
                            ])
                            ->action(function (array $data, InvoiceCsvExportService $service) {
                                $preview = $service->previewAccountingJournal(
                                    $data['year_month'],
                                    facilityId: auth()->user()?->facility_id,
                                    softwareType: $data['software_type'],
                                    profileId: $data['profile_id'] ?? null,
                                    includeCareServices: $data['include_care_services'] ?? true
                                );

                                // CSV形式でプレビューデータを出力
                                $csv = '';
                                if ($preview['entries']) {
                                    $output = fopen('php://temp', 'r+');
                                    if ($preview['profile']['bom'] ?? true) {
                                        fwrite($output, "\xEF\xBB\xBF");
                                    }
                                    if ($preview['profile']['include_header'] ?? true) {
                                        fputcsv($output, $preview['headers']);
                                    }
                                    foreach ($preview['entries'] as $entry) {
                                        fputcsv($output, array_values($entry));
                                    }
                                    rewind($output);
                                    $csv = stream_get_contents($output);
                                    fclose($output);
                                }

                                $fileName = "会計仕訳プレビュー_{$data['year_month']}".($data['include_care_services'] ? '_統合' : '_住居費のみ').'.csv';

                                return response()->streamDownload(
                                    fn () => print ($csv),
                                    $fileName,
                                    ['Content-Type' => 'text/csv; charset=UTF-8']
                                );
                            })
                            ->requiresConfirmation(),

                        // 一括請求書メール送信
                        Tables\Actions\BulkAction::make('bulkSendInvoiceEmail')
                            ->label('一括請求書送信')
                            ->icon('heroicon-o-paper-airplane')
                            ->color('success')
                            ->form([
                                Forms\Components\TextInput::make('email')
                                    ->label('送信先メールアドレス（共通）')
                                    ->email()
                                    ->required()
                                    ->placeholder('example@domain.com')
                                    ->helperText('個別のメールアドレスが設定されている場合はそちらが優先されます'),
                                Forms\Components\Textarea::make('message')
                                    ->label('添え書き（任意・全件共通）')
                                    ->rows(3)
                                    ->placeholder('全請求書に共通で添えるメッセージ'),
                                Forms\Components\Toggle::make('include_care_services')
                                    ->label('介護サービス請求書も同封する')
                                    ->default(false)
                                    ->helperText('確定済みの介護サービスPDFがあれば添付します'),
                            ])
                            ->action(function (array $data, $records, MailService $mailService) {
                                $sentCount = 0;
                                $failedCount = 0;

                                foreach ($records as $record) {
                                    $email = $record->resident->email ?? $record->resident->facility?->email ?? $data['email'];

                                    if (! $email) {
                                        $failedCount++;

                                        continue;
                                    }

                                    $pdfUrl = URL::signedRoute('invoices.preview', [
                                        'invoice' => $record->id,
                                        'type' => 'invoice',
                                    ]);

                                    $mailable = new InvoiceMail($record, $pdfUrl, $data['message'] ?? null, $data['include_care_services'] ?? false);
                                    $sent = $mailService->send($mailable, $email);

                                    if ($sent) {
                                        $sentCount++;
                                    } else {
                                        $failedCount++;
                                    }
                                }

                                if ($sentCount > 0) {
                                    Notification::make()
                                        ->title("{$sentCount} 件の請求書を送信しました".($data['include_care_services'] ? '（介護サービス含む）' : ''))
                                        ->success()
                                        ->send();
                                }
                                if ($failedCount > 0) {
                                    Notification::make()
                                        ->title("{$failedCount} 件の送信に失敗しました（メール未設定または送信エラー）")
                                        ->warning()
                                        ->send();
                                }
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
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->isFacilityAdmin() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        return $query;
    }
}
