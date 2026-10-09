<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemMasterResource\Pages;
use App\Models\ChargeItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ItemMasterResource extends Resource
{
    protected static ?string $model = ChargeItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = '品目マスタ';

    protected static ?string $modelLabel = '品目';

    protected static ?string $pluralModelLabel = '品目マスタ';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = '請求管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('品目情報')
                    ->description('自費サービス品目の基本設定')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('品目名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: diaper, haircut, advance_payment'),

                        Forms\Components\TextInput::make('display_name')
                            ->label('表示名（請求書・領収書用）')
                            ->maxLength(100)
                            ->placeholder('例: おむつ代、理美容代、立替金')
                            ->helperText('請求書や領収書に表示される名称。未入力時は品目名が使用されます'),

                        Forms\Components\Textarea::make('description')
                            ->label('説明・備考')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('品目の詳細説明、用途、注意事項など')
                            ->helperText('内部管理用の備考欄です。請求書には表示されません'),

                        Forms\Components\TextInput::make('default_price')
                            ->label('デフォルト単価')
                            ->numeric()
                            ->inputMode('numeric')
                            ->prefix('¥')
                            ->default(0)
                            ->required()
                            ->helperText('自費記録作成時に自動入力される単価（0の場合は手入力）'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('有効（利用可能）')
                            ->default(true)
                            ->helperText('無効にすると新規自費記録で選択できなくなります'),
                    ])->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('品目名')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('表示名')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('default_price')
                    ->label('デフォルト単価')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('daily_charges_count')
                    ->label('使用回数')
                    ->counts('dailyCharges')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効/無効')
                    ->placeholder('すべて')
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('有効にする')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('無効にする')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('bulkUpdatePrice')
                        ->label('単価一括更新')
                        ->icon('heroicon-o-currency-yen')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('default_price')
                                ->label('新しい単価')
                                ->numeric()
                                ->inputMode('numeric')
                                ->prefix('¥')
                                ->required()
                                ->helperText('選択した品目の単価を一括で更新します'),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each->update(['default_price' => $data['default_price']]);
                            Notification::make()
                                ->title("{$records->count()}件の品目単価を更新しました")
                                ->success()
                                ->send();
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
            'index' => Pages\ListItemMasters::route('/'),
            'create' => Pages\CreateItemMaster::route('/create'),
            'edit' => Pages\EditItemMaster::route('/{record}/edit'),
        ];
    }
}