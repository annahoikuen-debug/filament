<?php

namespace App\Filament\Resources;

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Filament\Resources\ServiceInvoiceResource\Pages;
use App\Models\ServiceInvoice;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ServiceInvoiceResource extends Resource
{
    protected static ?string $model = ServiceInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '介護サービス請求';

    protected static ?string $modelLabel = '介護サービス請求';

    protected static ?string $pluralModelLabel = '介護サービス請求一覧';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationGroup = '請求管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('基本情報')
                    ->schema([
                        Select::make('facility_id')
                            ->label('施設')
                            ->relationship('facility', 'name')
                            ->required()
                            ->visible(fn () => Auth::user()?->isCorporateAdmin())
                            ->default(fn () => Auth::user()?->facility_id)
                            ->disabled(fn () => ! Auth::user()?->isCorporateAdmin()),

                        Select::make('resident_id')
                            ->label('入居者')
                            ->relationship('resident', 'full_title')
                            ->searchable(['room_number', 'name'])
                            ->required()
                            ->modifyQueryUsing(fn (Builder $query) => $query->when(
                                Auth::user()?->isFacilityAdmin() && Auth::user()?->facility_id,
                                fn ($q) => $q->where('facility_id', Auth::user()->facility_id)
                            )),

                        Select::make('billing_year_month')
                            ->label('請求年月')
                            ->placeholder('YYYY-MM (例: 2026-10)')
                            ->required()
                            ->maxLength(7)
                            ->default(now()->format('Y-m')),

                        Select::make('service_type')
                            ->label('サービス種別')
                            ->options(ServiceType::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $enum = ServiceType::tryFrom($state);
                                    if ($enum) {
                                        $set('service_type_label', $enum->getLabel());
                                    }
                                }
                            }),

                        TextInput::make('service_type_label')
                            ->label('サービス種別(表示用)')
                            ->required()
                            ->maxLength(50)
                            ->helperText('サービス種別選択時に自動入力されます'),
                    ])->columns(3),

                Section::make('外部システム情報')
                    ->schema([
                        TextInput::make('external_system_name')
                            ->label('外部システム名')
                            ->placeholder('例: ケアプランデータ連携システム, 請求ソフト名')
                            ->maxLength(100),

                        TextInput::make('external_invoice_number')
                            ->label('外部請求番号')
                            ->placeholder('例: VC-202610-001')
                            ->maxLength(50),
                    ])->columns(2)->collapsed(),

                Section::make('金額情報')
                    ->schema([
                        TextInput::make('amount')
                            ->label('請求金額 (税抜)')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->required()
                            ->step(1),

                        TextInput::make('tax_amount')
                            ->label('消費税額')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->required()
                            ->step(1),

                        TextInput::make('tax_rate')
                            ->label('税率 (%)')
                            ->numeric()
                            ->step(0.01)
                            ->default(10.00)
                            ->required()
                            ->suffix('%'),
                    ])->columns(3),

                Section::make('PDFファイル')
                    ->schema([
                        FileUpload::make('pdf_path')
                            ->label('請求書PDF')
                            ->directory('service-invoices')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10MB
                            ->preserveFilenames()
                            ->getUploadedFileNameForStorageUsing(function ($file): string {
                                return $file->getClientOriginalName();
                            })
                            ->saveUploadedFileUsing(function ($file, $path) use ($form) {
                                // 元ファイル名を保存
                                $form->model?->update(['pdf_original_name' => $file->getClientOriginalName()]);
                                $file->storeAs('service-invoices', $path);

                                return $path;
                            })
                            ->deleteUploadedFileUsing(function ($path) {
                                Storage::disk('public')->delete($path);
                            })
                            ->openable()
                            ->downloadable()
                            ->previewable(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('ステータス・備考')
                    ->schema([
                        Select::make('status')
                            ->label('ステータス')
                            ->options(ServiceInvoiceStatus::class)
                            ->default(ServiceInvoiceStatus::Draft)
                            ->required()
                            ->native(false),

                        Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('billing_year_month')
                    ->label('請求年月')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('resident.room_number')
                    ->label('部屋番号')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('resident.name')
                    ->label('入居者氏名')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service_type')
                    ->label('サービス種別')
                    ->formatStateUsing(fn ($state) => ServiceType::tryFrom($state)?->getLabel() ?? $state)
                    ->badge()
                    ->color(fn ($state) => match (ServiceType::tryFrom($state)) {
                        ServiceType::VisitingCare => 'primary',
                        ServiceType::DayCare => 'success',
                        ServiceType::CarePlanning => 'info',
                        ServiceType::HomeNursing => 'warning',
                        ServiceType::ShortStay => 'danger',
                        ServiceType::WelfareEquipment => 'gray',
                        ServiceType::HomeModification => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('external_system_name')
                    ->label('外部システム')
                    ->limit(30)
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('external_invoice_number')
                    ->label('外部請求番号')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('請求金額')
                    ->money('JPY')
                    ->sortable(),

                TextColumn::make('tax_amount')
                    ->label('消費税')
                    ->money('JPY')
                    ->toggleable(),

                TextColumn::make('total_with_tax')
                    ->label('税込合計')
                    ->money('JPY')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('JPY')->label('合計')),

                TextColumn::make('status')
                    ->label('ステータス')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'confirmed',
                        'success' => 'sent',
                    ])
                    ->sortable(),

                TextColumn::make('pdf_path')
                    ->label('PDF')
                    ->formatStateUsing(fn ($state) => $state ? 'あり' : 'なし')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->toggleable(),

                TextColumn::make('sent_at')
                    ->label('送信日時')
                    ->dateTime('Y/m/d H:i')
                    ->toggleable(),

                TextColumn::make('sent_via')
                    ->label('送信経路')
                    ->badge()
                    ->toggleable(),
            ])
            ->defaultSort('billing_year_month', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('billing_year_month')
                    ->label('請求年月')
                    ->options(fn () => ServiceInvoice::query()->distinct()->pluck('billing_year_month', 'billing_year_month')),

                Tables\Filters\SelectFilter::make('service_type')
                    ->label('サービス種別')
                    ->options(ServiceType::class)
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options(ServiceInvoiceStatus::class)
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('facility_id')
                    ->label('施設')
                    ->relationship('facility', 'name')
                    ->multiple()
                    ->preload()
                    ->visible(fn () => Auth::user()?->isCorporateAdmin()),

                Tables\Filters\Filter::make('has_pdf')
                    ->label('PDFあり')
                    ->query(fn (Builder $query) => $query->whereNotNull('pdf_path')),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadPdf')
                    ->label('PDFダウンロード')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn (ServiceInvoice $record) => $record->hasPdf())
                    ->action(function (ServiceInvoice $record) {
                        return response()->download(
                            $record->pdf_full_path,
                            $record->pdf_original_name ?? "介護サービス請求書_{$record->billing_year_month}_{$record->resident->room_number}号室_{$record->resident->name}.pdf"
                        );
                    }),

                Tables\Actions\Action::make('markConfirmed')
                    ->label('確定')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (ServiceInvoice $record) => $record->status === ServiceInvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (ServiceInvoice $record) {
                        $record->markAsConfirmed();
                        Notification::make()->title('確定しました')->success()->send();
                    }),

                Tables\Actions\Action::make('markSent')
                    ->label('送信済みにする')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ServiceInvoice $record) => $record->status !== ServiceInvoiceStatus::Sent)
                    ->form([
                        Select::make('sent_via')
                            ->label('送信経路')
                            ->options([
                                'email' => 'メール',
                                'zip' => 'ZIP配布',
                                'portal' => 'ポータル',
                                'manual' => '手渡し・その他',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (ServiceInvoice $record, array $data) {
                        $record->markAsSent($data['sent_via']);
                        Notification::make()->title('送信済みにしました')->success()->send();
                    }),

                Tables\Actions\Action::make('markDraft')
                    ->label('下書きに戻す')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ServiceInvoice $record) => $record->status !== ServiceInvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (ServiceInvoice $record) {
                        $record->markAsDraft();
                        Notification::make()->title('下書きに戻しました')->success()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (ServiceInvoice $record) => $record->status === ServiceInvoiceStatus::Draft),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markConfirmed')
                        ->label('一括確定')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->action(fn ($records) => $records->each->markAsConfirmed())
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('markSent')
                        ->label('一括送信済み')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->form([
                            Select::make('sent_via')
                                ->label('送信経路')
                                ->options([
                                    'email' => 'メール',
                                    'zip' => 'ZIP配布',
                                    'portal' => 'ポータル',
                                    'manual' => '手渡し・その他',
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(fn ($r) => $r->markAsSent($data['sent_via']));
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceInvoices::route('/'),
            'create' => Pages\CreateServiceInvoice::route('/create'),
            'view' => Pages\ViewServiceInvoice::route('/{record}'),
            'edit' => Pages\EditServiceInvoice::route('/{record}/edit'),
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
