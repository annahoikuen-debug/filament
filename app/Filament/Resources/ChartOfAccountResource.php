<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChartOfAccountResource\Pages;
use App\Models\ChartOfAccount;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '勘定科目マスタ';

    protected static ?string $modelLabel = '勘定科目';

    protected static ?string $pluralModelLabel = '勘定科目マスタ';

    protected static ?int $navigationSort = 10;

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

                        Select::make('item_type')
                            ->label('品目タイプ')
                            ->options([
                                ChartOfAccount::ITEM_TYPE_RENT => '家賃',
                                ChartOfAccount::ITEM_TYPE_MANAGEMENT_FEE => '管理費',
                                ChartOfAccount::ITEM_TYPE_SERVICE => '自費サービス',
                                ChartOfAccount::ITEM_TYPE_ADVANCE_PAYMENT => '立替金',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('account_side')
                            ->label('借方/貸方')
                            ->options([
                                ChartOfAccount::ACCOUNT_SIDE_DEBIT => '借方',
                                ChartOfAccount::ACCOUNT_SIDE_CREDIT => '貸方',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->columns(3),

                Section::make('勘定科目情報')
                    ->schema([
                        TextInput::make('account_code')
                            ->label('科目コード')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('例: 1100'),

                        TextInput::make('account_name')
                            ->label('科目名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: 売掛金'),

                        TextInput::make('sub_account_code')
                            ->label('補助科目コード')
                            ->maxLength(20)
                            ->placeholder('任意'),

                        TextInput::make('sub_account_name')
                            ->label('補助科目名')
                            ->maxLength(100)
                            ->placeholder('任意'),
                    ])
                    ->columns(2),

                Section::make('税区分・部門・タグ')
                    ->schema([
                        TextInput::make('tax_code')
                            ->label('税区分コード')
                            ->maxLength(50)
                            ->placeholder('例: tax_exempt, taxable_10, taxable_8')
                            ->helperText('freee: 0/1/2, MF: 対象外/課税10%/課税8%, 弥生: 対象外/課税仕入10% 等'),

                        TextInput::make('department_code')
                            ->label('部門コード')
                            ->maxLength(20)
                            ->placeholder('任意'),

                        TextInput::make('department_name')
                            ->label('部門名')
                            ->maxLength(100)
                            ->placeholder('任意'),

                        TextInput::make('tag_codes')
                            ->label('タグコード (カンマ区切り)')
                            ->maxLength(255)
                            ->placeholder('tag1,tag2,tag3')
                            ->helperText('複数のタグをカンマ区切りで指定'),
                    ])
                    ->columns(2),

                Section::make('ステータス・順序')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
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

                Tables\Columns\BadgeColumn::make('item_type')
                    ->label('品目')
                    ->colors([
                        'primary' => ChartOfAccount::ITEM_TYPE_RENT,
                        'success' => ChartOfAccount::ITEM_TYPE_MANAGEMENT_FEE,
                        'warning' => ChartOfAccount::ITEM_TYPE_SERVICE,
                        'danger' => ChartOfAccount::ITEM_TYPE_ADVANCE_PAYMENT,
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        ChartOfAccount::ITEM_TYPE_RENT => '家賃',
                        ChartOfAccount::ITEM_TYPE_MANAGEMENT_FEE => '管理費',
                        ChartOfAccount::ITEM_TYPE_SERVICE => '自費サービス',
                        ChartOfAccount::ITEM_TYPE_ADVANCE_PAYMENT => '立替金',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('account_side')
                    ->label('借/貸')
                    ->colors([
                        'primary' => ChartOfAccount::ACCOUNT_SIDE_DEBIT,
                        'success' => ChartOfAccount::ACCOUNT_SIDE_CREDIT,
                    ])
                    ->formatStateUsing(fn ($state) => $state === ChartOfAccount::ACCOUNT_SIDE_DEBIT ? '借方' : '貸方')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account_code')
                    ->label('科目コード')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('account_name')
                    ->label('科目名')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('sub_account_code')
                    ->label('補助科目コード')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('tax_code')
                    ->label('税区分')
                    ->searchable()
                    ->toggleable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('department_code')
                    ->label('部門コード')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily('mono'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('順序')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(
                fn (Builder $query) => $query->orderBy('facility_id')
                    ->orderBy('item_type')
                    ->orderBy('account_side')
                    ->orderBy('sort_order')
            )
            ->filters([
                Tables\Filters\SelectFilter::make('facility_id')
                    ->label('施設')
                    ->relationship('facility', 'name')
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('item_type')
                    ->label('品目タイプ')
                    ->options([
                        ChartOfAccount::ITEM_TYPE_RENT => '家賃',
                        ChartOfAccount::ITEM_TYPE_MANAGEMENT_FEE => '管理費',
                        ChartOfAccount::ITEM_TYPE_SERVICE => '自費サービス',
                        ChartOfAccount::ITEM_TYPE_ADVANCE_PAYMENT => '立替金',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('account_side')
                    ->label('借方/貸方')
                    ->options([
                        ChartOfAccount::ACCOUNT_SIDE_DEBIT => '借方',
                        ChartOfAccount::ACCOUNT_SIDE_CREDIT => '貸方',
                    ])
                    ->multiple(),

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
            'index' => Pages\ListChartOfAccounts::route('/'),
            'create' => Pages\CreateChartOfAccount::route('/create'),
            'edit' => Pages\EditChartOfAccount::route('/{record}/edit'),
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
