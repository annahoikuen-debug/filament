<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingExportProfileResource\Pages;
use App\Models\AccountingExportProfile;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountingExportProfileResource extends Resource
{
    protected static ?string $model = AccountingExportProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '会計エクスポート設定';

    protected static ?string $modelLabel = 'エクスポートプロファイル';

    protected static ?string $pluralModelLabel = '会計エクスポート設定';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationGroup = '会計連携設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('基本設定')
                    ->schema([
                        Select::make('facility_id')
                            ->label('施設')
                            ->relationship('facility', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->facility_id),

                        TextInput::make('name')
                            ->label('プロファイル名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: freee標準, MFクラウド会計, 弥生会計'),

                        Select::make('software_type')
                            ->label('会計ソフト')
                            ->options([
                                AccountingExportProfile::SOFTWARE_FREEE => 'freee',
                                AccountingExportProfile::SOFTWARE_MF => 'MFクラウド会計',
                                AccountingExportProfile::SOFTWARE_YAYOI => '弥生会計',
                                AccountingExportProfile::SOFTWARE_KANJOBUGYO => '勘定奉行',
                                AccountingExportProfile::SOFTWARE_CUSTOM => 'カスタム',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('選択に応じてデフォルトマッピングが自動適用されます'),
                    ])
                    ->columns(3),

                Section::make('CSV出力設定')
                    ->schema([
                        Select::make('encoding')
                            ->label('文字エンコーディング')
                            ->options([
                                'UTF-8' => 'UTF-8 (BOM付き)',
                                'UTF-8-BOM' => 'UTF-8 (BOMなし)',
                                'SJIS' => 'Shift_JIS (CP932)',
                                'CP932' => 'CP932',
                            ])
                            ->default('UTF-8')
                            ->required()
                            ->native(false),

                        Select::make('date_format')
                            ->label('日付フォーマット')
                            ->options([
                                'Y/m/d' => 'YYYY/MM/DD (2026/10/08)',
                                'Y-m-d' => 'YYYY-MM-DD (2026-10-08)',
                                'Ymd' => 'YYYYMMDD (20261008)',
                                'd/m/Y' => 'DD/MM/YYYY (08/10/2026)',
                            ])
                            ->default('Y/m/d')
                            ->required()
                            ->native(false),

                        Select::make('line_ending')
                            ->label('改行コード')
                            ->options([
                                'CRLF' => 'CRLF (Windows標準)',
                                'LF' => 'LF (Unix/Linux/Mac標準)',
                            ])
                            ->default('CRLF')
                            ->required()
                            ->native(false),

                        Toggle::make('include_header')
                            ->label('ヘッダー行を含む')
                            ->default(true),

                        Toggle::make('bom')
                            ->label('UTF-8 BOMを付与')
                            ->default(true)
                            ->visible(fn (Forms\Get $get) => in_array($get('encoding'), ['UTF-8', 'UTF-8-BOM'])),
                    ])
                    ->columns(2),

                Section::make('カラムマッピング (ヘッダー名設定)')
                    ->schema([
                        KeyValue::make('header_mapping')
                            ->label('ヘッダー名マッピング')
                            ->keyLabel('内部フィールド名')
                            ->valueLabel('出力時のヘッダー名')
                            ->addActionLabel('フィールドを追加')
                            ->reorderable(true)
                            ->columnSpanFull()
                            ->helperText('内部フィールド名 → 出力時のCSVヘッダー名 の対応を設定'),
                    ]),

                Section::make('フィールド必須/任意設定')
                    ->schema([
                        KeyValue::make('field_mapping')
                            ->label('フィールドマッピング')
                            ->keyLabel('分類')
                            ->valueLabel('フィールド名 (カンマ区切り)')
                            ->addActionLabel('分類を追加')
                            ->reorderable(true)
                            ->columnSpanFull()
                            ->default([
                                'required' => 'date,debit_account_code,credit_account_code,amount,tax_code',
                                'optional' => 'debit_sub_account_code,debit_department_code,debit_tag_codes,credit_sub_account_code,credit_department_code,credit_tag_codes,description',
                            ])
                            ->helperText('required: 必須フィールド, optional: 任意フィールド。カンマ区切りで指定'),
                    ]),

                Section::make('税区分コードマッピング')
                    ->schema([
                        KeyValue::make('tax_code_mapping')
                            ->label('税区分コード変換 (標準 → ソフト固有)')
                            ->keyLabel('標準コード')
                            ->valueLabel('会計ソフト用コード')
                            ->addActionLabel('マッピングを追加')
                            ->reorderable(true)
                            ->columnSpanFull()
                            ->helperText('標準税区分コードを各会計ソフトのコードに変換'),
                    ]),

                Section::make('部門・タグ・補助科目マッピング (上級者向け)')
                    ->schema([
                        KeyValue::make('department_mapping')
                            ->label('部門マッピング')
                            ->keyLabel('内部部門コード')
                            ->valueLabel('会計ソフト用部門コード')
                            ->addActionLabel('追加')
                            ->reorderable(true)
                            ->columnSpanFull(),

                        KeyValue::make('tag_mapping')
                            ->label('タグマッピング')
                            ->keyLabel('内部タグコード')
                            ->valueLabel('会計ソフト用タグコード')
                            ->addActionLabel('追加')
                            ->reorderable(true)
                            ->columnSpanFull(),

                        KeyValue::make('sub_account_mapping')
                            ->label('補助科目マッピング')
                            ->keyLabel('内部補助科目コード')
                            ->valueLabel('会計ソフト用補助科目コード')
                            ->addActionLabel('追加')
                            ->reorderable(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('デフォルト値設定')
                    ->schema([
                        KeyValue::make('default_values')
                            ->label('デフォルト値 (摘要テンプレート等)')
                            ->keyLabel('キー')
                            ->valueLabel('値')
                            ->addActionLabel('追加')
                            ->reorderable(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('ステータス')
                    ->schema([
                        Toggle::make('is_default')
                            ->label('デフォルトプロファイル')
                            ->default(false)
                            ->helperText('この会計ソフト種類でのデフォルトとして使用されます'),

                        Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('備考')
                    ->schema([
                        Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('施設')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('プロファイル名')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\BadgeColumn::make('software_type')
                    ->label('会計ソフト')
                    ->colors([
                        'primary' => AccountingExportProfile::SOFTWARE_FREEE,
                        'success' => AccountingExportProfile::SOFTWARE_MF,
                        'warning' => AccountingExportProfile::SOFTWARE_YAYOI,
                        'danger' => AccountingExportProfile::SOFTWARE_KANJOBUGYO,
                        'gray' => AccountingExportProfile::SOFTWARE_CUSTOM,
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        AccountingExportProfile::SOFTWARE_FREEE => 'freee',
                        AccountingExportProfile::SOFTWARE_MF => 'MFクラウド',
                        AccountingExportProfile::SOFTWARE_YAYOI => '弥生会計',
                        AccountingExportProfile::SOFTWARE_KANJOBUGYO => '勘定奉行',
                        AccountingExportProfile::SOFTWARE_CUSTOM => 'カスタム',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('encoding')
                    ->label('エンコーディング')
                    ->badge()
                    ->colors([
                        'primary' => 'UTF-8',
                        'success' => 'SJIS',
                        'warning' => 'CP932',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_format')
                    ->label('日付形式')
                    ->fontFamily('mono')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('include_header')
                    ->label('ヘッダー')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('bom')
                    ->label('BOM')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('デフォルト')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(['facility_id', 'software_type', 'name'])
            ->filters([
                Tables\Filters\SelectFilter::make('facility_id')
                    ->label('施設')
                    ->relationship('facility', 'name')
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('software_type')
                    ->label('会計ソフト')
                    ->options([
                        AccountingExportProfile::SOFTWARE_FREEE => 'freee',
                        AccountingExportProfile::SOFTWARE_MF => 'MFクラウド会計',
                        AccountingExportProfile::SOFTWARE_YAYOI => '弥生会計',
                        AccountingExportProfile::SOFTWARE_KANJOBUGYO => '勘定奉行',
                        AccountingExportProfile::SOFTWARE_CUSTOM => 'カスタム',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('デフォルト')
                    ->boolean()
                    ->trueLabel('デフォルトのみ')
                    ->falseLabel('デフォルト以外')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ')
                    ->native(false),
            ])
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
            'index' => Pages\ListAccountingExportProfiles::route('/'),
            'create' => Pages\CreateAccountingExportProfile::route('/create'),
            'edit' => Pages\EditAccountingExportProfile::route('/{record}/edit'),
        ];
    }

    /**
     * 施設管理者は自施設のみ表示
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if ($user && $user->isFacilityAdmin() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        return $query;
    }
}