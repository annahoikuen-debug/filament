<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxSettingResource\Pages;
use App\Models\TaxSetting;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaxSettingResource extends Resource
{
    protected static ?string $model = TaxSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = '消費税率設定';

    protected static ?string $modelLabel = '税率設定';

    protected static ?string $pluralModelLabel = '消費税率設定';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'システム設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('税率設定')
                    ->schema([
                        TextInput::make('standard_rate')
                            ->label('標準税率（%）')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(10)
                            ->step(1)
                            ->helperText('標準的な商品・サービスに適用される税率'),

                        TextInput::make('reduced_rate')
                            ->label('軽減税率（%）')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(8)
                            ->step(1)
                            ->helperText('軽減税率対象品目（飲食料品等）に適用される税率'),
                    ])
                    ->columns(2),

                Section::make('適用期間')
                    ->schema([
                        DatePicker::make('effective_from')
                            ->label('適用開始日')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->helperText('この税率が適用される最初の日'),

                        DatePicker::make('effective_until')
                            ->label('適用終了日')
                            ->nullable()
                            ->native(false)
                            ->helperText('空欄の場合は無期限で適用されます'),
                    ])
                    ->columns(2),

                Section::make('適用範囲・ステータス')
                    ->schema([
                        TextInput::make('scope')
                            ->label('適用範囲')
                            ->required()
                            ->default('all')
                            ->maxLength(50)
                            ->helperText('all: 全品目共通, specific: 特定品目のみ 等'),

                        Toggle::make('is_active')
                            ->label('有効')
                            ->default(true)
                            ->helperText('無効にするとこの税率設定は使用されません'),
                    ])
                    ->columns(2),

                Section::make('備考')
                    ->schema([
                        Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('税率改正の経緯や備考を記入'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('standard_rate')
                    ->label('標準税率')
                    ->numeric()
                    ->suffix('%')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reduced_rate')
                    ->label('軽減税率')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label('適用開始')
                    ->date('Y/m/d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_until')
                    ->label('適用終了')
                    ->date('Y/m/d')
                    ->sortable()
                    ->placeholder('無期限'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_from', 'desc')
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
            'index' => Pages\ListTaxSettings::route('/'),
            'create' => Pages\CreateTaxSetting::route('/create'),
            'edit' => Pages\EditTaxSetting::route('/{record}/edit'),
        ];
    }
}