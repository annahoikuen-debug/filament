<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyChargePresetResource\Pages;
use App\Models\ChargeItem;
use App\Models\DailyChargePreset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Step 10: 自費プリセット管理画面
 * プリセットごとの構成品目・数量をリピーター形式で登録・編集
 */
class DailyChargePresetResource extends Resource
{
    protected static ?string $model = DailyChargePreset::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = '請求管理';

    protected static ?string $navigationLabel = '自費プリセット';

    protected static ?string $modelLabel = 'プリセット';

    protected static ?string $pluralModelLabel = '自費プリセット';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('プリセット名')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('例: デイサービス利用パック'),
                Forms\Components\Textarea::make('description')
                    ->label('説明')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('有効')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('並び順')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Forms\Components\Repeater::make('items')
                    ->label('構成品目')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('charge_item_id')
                            ->label('品目')
                            ->options(fn () => ChargeItem::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('default_quantity')
                            ->label('既定数量')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('並び順')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(3)
                    ->itemLabel(fn (array $state) => ChargeItem::find($state['charge_item_id'])?->name ?? '品目未選択')
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('プリセット名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('説明')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('品目数')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items.charge_item.name')
                    ->label('構成品目')
                    ->badge()
                    ->limitList(3),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('並び順')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyChargePresets::route('/'),
            'create' => Pages\CreateDailyChargePreset::route('/create'),
            'edit' => Pages\EditDailyChargePreset::route('/{record}/edit'),
        ];
    }
}
