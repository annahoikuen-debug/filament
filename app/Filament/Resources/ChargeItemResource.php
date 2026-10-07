<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChargeItemResource\Pages;
use App\Models\ChargeItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChargeItemResource extends Resource
{
    protected static ?string $model = ChargeItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = '自費品目マスタ';

    protected static ?string $modelLabel = '品目';

    protected static ?string $pluralModelLabel = '品目一覧';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('品目名')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('例: 紙おむつ、理美容代、立替金'),
                Forms\Components\TextInput::make('default_price')
                    ->label('デフォルト単価')
                    ->numeric()
                    ->prefix('¥')
                    ->default(0)
                    ->required()
                    ->helperText(function (Get $get) {
                        $name = $get('name') ?? '';
                        if (str_contains($name, '立替金') || str_contains($name, '日用品')) {
                            return '立替金・日用品の場合は0とし、実際の金額は日々の記録で入力してください。';
                        }

                        return '日々の記録入力時に自動セットされる基準単価です（後から変更可能）。';
                    }),
                Forms\Components\Toggle::make('is_active')
                    ->label('有効')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('品目名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('default_price')
                    ->label('デフォルト単価')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChargeItems::route('/'),
            'create' => Pages\CreateChargeItem::route('/create'),
            'edit' => Pages\EditChargeItem::route('/{record}/edit'),
        ];
    }
}
