<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityResource\Pages;
use App\Models\Facility;
use App\Services\Accounting\AccountingSyncService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = '施設設定';

    protected static ?string $modelLabel = '施設情報';

    protected static ?string $pluralModelLabel = '施設設定';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'システム設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('基本情報')
                    ->schema([
                        TextInput::make('name')
                            ->label('施設名')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('operator')
                            ->label('運営事業者')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('postal_code')
                            ->label('郵便番号')
                            ->required()
                            ->maxLength(7)
                            ->placeholder('123-4567')
                            ->helperText('ハイフンありで入力（例: 123-4567）'),

                        TextInput::make('address')
                            ->label('住所')
                            ->required()
                            ->maxLength(200),

                        TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('03-1234-5678'),

                        TextInput::make('fax')
                            ->label('FAX番号')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('03-1234-5679'),

                        TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->maxLength(100)
                            ->placeholder('info@example.jp'),
                    ])
                    ->columns(2),

                Section::make('インボイス制度対応')
                    ->schema([
                        TextInput::make('invoice_registration_number')
                            ->label('適格請求書発行事業者登録番号')
                            ->required()
                            ->maxLength(14)
                            ->placeholder('T1234567890123')
                            ->helperText('Tで始まる13桁の数字（計14桁）')
                            ->rules([
                                'required',
                                'regex:/^T\d{13}$/',
                            ]),
                    ]),

                Section::make('振込先銀行口座')
                    ->schema([
                        KeyValue::make('bank')
                            ->label('銀行口座情報')
                            ->keyLabel('項目')
                            ->valueLabel('値')
                            ->addActionLabel('項目を追加')
                            ->default([
                                'name' => '',
                                'branch_name' => '',
                                'account_type' => '普通',
                                'account_number' => '',
                                'account_holder' => '',
                            ])
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('請求・支払いサイクル')
                    ->schema([
                        KeyValue::make('billing')
                            ->label('請求サイクル設定')
                            ->keyLabel('項目')
                            ->valueLabel('値')
                            ->default([
                                'direct_debit_day' => '27',
                                'bank_transfer_due_days' => '30',
                            ])
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('会計ソフト連携 (freee / MFクラウド会計)')
                    ->description('仕訳データを会計ソフトへ自動連携するためのAPI認証情報')
                    ->schema([
                        Select::make('accounting_software')
                            ->label('連携対象会計ソフト')
                            ->options([
                                Facility::SOFTWARE_FREEE => 'freee',
                                Facility::SOFTWARE_MF => 'MFクラウド会計',
                            ])
                            ->native(false)
                            ->placeholder('選択してください')
                            ->helperText('選択した会計ソフトの認証情報を入力してください')
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('accounting_api_settings.sync_enabled', true)),

                        Toggle::make('accounting_api_settings.sync_enabled')
                            ->label('自動同期を有効にする')
                            ->default(false)
                            ->helperText('請求確定・入金時に自動的に仕訳を同期します')
                            ->visible(fn (callable $get) => $get('accounting_software')),

                        Toggle::make('accounting_api_settings.auto_sync_on_export')
                            ->label('CSVエクスポート時に同期')
                            ->default(false)
                            ->helperText('会計エクスポート実行時に同時にAPI同期も行います')
                            ->visible(fn (callable $get) => $get('accounting_software')),

                        // freee 設定
                        Section::make('freee API設定')
                            ->description('freee連携用の認証情報（OAuth2.0）')
                            ->visible(fn (callable $get) => $get('accounting_software') === Facility::SOFTWARE_FREEE)
                            ->schema([
                                TextInput::make('freee_client_id')
                                    ->label('Client ID')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('freee 開発者画面で取得'),

                                TextInput::make('freee_client_secret')
                                    ->label('Client Secret')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('freee 開発者画面で取得'),

                                TextInput::make('freee_access_token')
                                    ->label('Access Token')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('OAuth認証後に取得したアクセストークン'),

                                TextInput::make('freee_refresh_token')
                                    ->label('Refresh Token')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('トークンリフレッシュ用'),

                                TextInput::make('freee_company_id')
                                    ->label('事業所ID')
                                    ->required()
                                    ->numeric()
                                    ->helperText('freee の事業所ID（数値）'),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        // MFクラウド会計 設定
                        Section::make('MFクラウド会計 API設定')
                            ->description('MFクラウド会計連携用の認証情報（OAuth2.0）')
                            ->visible(fn (callable $get) => $get('accounting_software') === Facility::SOFTWARE_MF)
                            ->schema([
                                TextInput::make('mf_client_id')
                                    ->label('Client ID')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('MF 開発者画面で取得'),

                                TextInput::make('mf_client_secret')
                                    ->label('Client Secret')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('MF 開発者画面で取得'),

                                TextInput::make('mf_access_token')
                                    ->label('Access Token')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('OAuth認証後に取得したアクセストークン'),

                                TextInput::make('mf_refresh_token')
                                    ->label('Refresh Token')
                                    ->required()
                                    ->password()
                                    ->autocomplete('off')
                                    ->helperText('トークンリフレッシュ用'),

                                TextInput::make('mf_office_id')
                                    ->label('事業所ID')
                                    ->required()
                                    ->numeric()
                                    ->helperText('MF の事業所ID（数値）'),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        // 接続テストボタン
                        Section::make('接続テスト')
                            ->visible(fn (callable $get) => $get('accounting_software'))
                            ->schema([
                                Action::make('test_connection')
                                    ->label('接続テスト実行')
                                    ->icon('heroicon-o-wifi')
                                    ->color('info')
                                    ->action(function (array $data, Facility $record, callable $get) {
                                        $software = $get('accounting_software');
                                        if (! $software) {
                                            Notification::make()
                                                ->title('会計ソフトを選択してください')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        // 一時的にデータを保存してテスト
                                        $record->fill($data)->save();

                                        try {
                                            $service = app(AccountingSyncService::class);
                                            $providers = $service->getAvailableProviders($record->id);
                                            $provider = collect($providers)->firstWhere('key', $software);

                                            if ($provider && $provider['configured'] && $provider['token_valid']) {
                                                Notification::make()
                                                    ->title('接続テスト成功')
                                                    ->body("{$provider['name']} への接続に成功しました。")
                                                    ->success()
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->title('接続テスト失敗')
                                                    ->body('認証情報が不完全またはトークンが無効です。')
                                                    ->danger()
                                                    ->send();
                                            }
                                        } catch (\Throwable $e) {
                                            Notification::make()
                                                ->title('接続テストエラー')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                            ])
                            ->columns(1),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),

                Section::make('ステータス・備考')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('有効（この施設を使用する）')
                            ->default(true)
                            ->helperText('無効にするとこの施設設定は使用されません'),

                        Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('seal_path')
                            ->label('印鑑画像')
                            ->image()
                            ->directory('seals')
                            ->visibility('public')
                            ->maxSize(1024) // 1MB
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('施設名')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('operator')
                    ->label('運営事業者')
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice_registration_number')
                    ->label('登録番号')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }

    /**
     * 全施設を表示（有効/無効に関わらず）
     * 実際の施設選択はFacility::current()または明示的なfacility_id指定で行われる
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
