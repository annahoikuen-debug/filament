<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyChargeResource\Pages;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Resident;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DailyChargeResource extends Resource
{
    protected static ?string $model = DailyCharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = '日々の自費記録';

    protected static ?string $modelLabel = '自費記録';

    protected static ?string $pluralModelLabel = '日々の自費記録';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = '請求管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->description('自費サービスの利用記録')
                    ->schema([
                        Forms\Components\Select::make('resident_id')
                            ->label('入居者')
                            ->relationship('resident', 'full_title', fn (Builder $query) => $query->when(
                                Auth::user()?->isFacilityAdmin() && Auth::user()?->facility_id,
                                fn ($q) => $q->where('facility_id', Auth::user()->facility_id)
                            ))
                            ->searchable(['room_number', 'name'])
                            ->getOptionLabelFromRecordUsing(fn (Resident $record) => $record->full_title)
                            ->required()
                            ->preload(),

                        Forms\Components\Select::make('charge_item_id')
                            ->label('品目')
                            ->relationship('chargeItem', 'name', fn (Builder $query) => $query
                                ->where('is_active', true)
                                ->when(
                                    Auth::user()?->isFacilityAdmin() && Auth::user()?->facility_id,
                                    fn ($q) => $q->where(function ($qq) {
                                        $qq->whereNull('facility_id')
                                            ->orWhere('facility_id', Auth::user()->facility_id);
                                    })
                                ))
                            ->searchable(['name'])
                            ->getOptionLabelFromRecordUsing(fn (ChargeItem $record) => "{$record->name} (¥{$record->default_price})")
                            ->required()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $item = ChargeItem::find($state);
                                if ($item && $item->default_price > 0) {
                                    $set('unit_price', $item->default_price);
                                }
                            }),

                        Forms\Components\DatePicker::make('date')
                            ->label('利用日')
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('単価')
                            ->numeric()
                            ->inputMode('numeric')
                            ->prefix('¥')
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('数量')
                            ->numeric()
                            ->inputMode('numeric')
                            ->default(1)
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('subtotal')
                            ->label('小計')
                            ->numeric()
                            ->prefix('¥')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('単価 × 数量で自動計算'),
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
                Tables\Columns\TextColumn::make('date')
                    ->label('利用日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('resident.room_number')
                    ->label('部屋番号')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resident.name')
                    ->label('入居者氏名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resident.facility.name')
                    ->label('施設')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->visible(fn () => Auth::user()?->isCorporateAdmin()),

                Tables\Columns\TextColumn::make('chargeItem.name')
                    ->label('品目')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('単価')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('数量')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('小計')
                    ->money('JPY')
                    ->weight('bold'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('resident_id')
                    ->label('入居者')
                    ->relationship('resident', 'name')
                    ->searchable(['room_number', 'name'])
                    ->preload()
                    ->multiple()
                    ->modifyQueryUsing(fn (Builder $query) => $query->when(
                        Auth::user()?->isFacilityAdmin() && Auth::user()?->facility_id,
                        fn ($q) => $q->where('facility_id', Auth::user()->facility_id)
                    )),

                Tables\Filters\SelectFilter::make('charge_item_id')
                    ->label('品目')
                    ->relationship('chargeItem', 'name')
                    ->preload()
                    ->multiple(),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('開始日')->native(false)->displayFormat('Y/m/d'),
                        Forms\Components\DatePicker::make('until')->label('終了日')->native(false)->displayFormat('Y/m/d'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
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
            'index' => Pages\ListDailyCharges::route('/'),
            'create' => Pages\CreateDailyCharge::route('/create'),
            'edit' => Pages\EditDailyCharge::route('/{record}/edit'),
        ];
    }

    /**
     * 施設フィルタに基づいてクエリを絞り込み
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
