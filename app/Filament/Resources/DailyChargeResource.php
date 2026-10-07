<?php

namespace App\Filament\Resources;

use App\Enums\ResidentStatus;
use App\Filament\Resources\DailyChargeResource\Pages;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Resident;
use Carbon\Carbon;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyChargeResource extends Resource
{
    protected static ?string $model = DailyCharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = '日々の自費記録';

    protected static ?string $modelLabel = '自費記録';

    protected static ?string $pluralModelLabel = '日々の自費記録一覧';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('記録情報')
                    ->description('現場の介護・立替実績を入力します')
                    ->schema([
                        // 1. 日付（入居期間内バリデーション付き）
                        Forms\Components\DatePicker::make('date')
                            ->label('日付')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->closeOnDateSelection()
                            ->required()
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    $residentId = $get('resident_id');
                                    if ($residentId && $value) {
                                        $resident = Resident::find($residentId);
                                        if ($resident && ! $resident->isLivingAt($value)) {
                                            $moveIn = $resident->move_in_date?->format('Y/m/d') ?? '未設定';
                                            $moveOut = $resident->move_out_date?->format('Y/m/d') ?? '退去日未定';
                                            $fail("利用日が入居者の在籍期間外です (入居日: {$moveIn} 〜 退去日: {$moveOut})。");
                                        }
                                    }
                                },
                            ]),

                        // 2. 入居者
                        Forms\Components\Select::make('resident_id')
                            ->label('入居者')
                            ->relationship(
                                name: 'resident',
                                modifyQueryUsing: fn (Builder $query) => $query->where('status', ResidentStatus::Active)->orderBy('room_number')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Resident $record) => $record->full_title)
                            ->searchable(['room_number', 'name', 'name_kana'])
                            ->preload()
                            ->live()
                            ->required()
                            ->native(false),

                        // 3. 品目
                        Forms\Components\Select::make('charge_item_id')
                            ->label('品目')
                            ->relationship(
                                name: 'chargeItem',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $item = ChargeItem::find($state);
                                    if ($item) {
                                        $set('unit_price', $item->default_price);
                                    }
                                }
                            })
                            ->required(),

                        // 4. 単価
                        Forms\Components\TextInput::make('unit_price')
                            ->label('単価')
                            ->numeric()
                            ->inputMode('numeric')
                            ->prefix('¥')
                            ->live(onBlur: true)
                            ->required()
                            ->helperText('品目選択時に自動反映されます。変更も可能です。'),

                        // 5. 数量
                        Forms\Components\TextInput::make('quantity')
                            ->label('数量')
                            ->numeric()
                            ->inputMode('numeric')
                            ->default(1)
                            ->minValue(1)
                            ->live()
                            ->required(),

                        // 小計プレビュー
                        Forms\Components\Placeholder::make('subtotal_preview')
                            ->label('小計金額')
                            ->content(function (Get $get): string {
                                $price = (int) $get('unit_price');
                                $qty = (int) $get('quantity');

                                return '¥'.number_format($price * $qty);
                            })
                            ->extraAttributes(['class' => 'text-lg font-bold text-primary-600']),

                        // 6. 備考
                        Forms\Components\Textarea::make('note')
                            ->label('備考')
                            ->placeholder('例: 夜間交換分、理美容代立替、家族持参分など')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns([
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
                    ->label('日付')
                    ->date('m/d (D)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resident.full_title')
                    ->label('入居者')
                    ->searchable(['room_number', 'name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('chargeItem.name')
                    ->label('品目')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('単価')
                    ->money('JPY')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('数量')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('小計')
                    ->money('JPY')
                    ->state(fn (DailyCharge $record): int => $record->subtotal)
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("(unit_price * quantity) {$direction}")),

                Tables\Columns\TextColumn::make('note')
                    ->label('備考')
                    ->limit(20)
                    ->tooltip(fn (DailyCharge $record): ?string => $record->note),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->label('対象年月')
                    ->options(function () {
                        $options = [];
                        for ($i = 0; $i < 12; $i++) {
                            $date = Carbon::now()->subMonths($i);
                            $options[$date->format('Y-m')] = $date->format('Y年m月');
                        }

                        return $options;
                    })
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['value'])) {
                            $query->forYearMonth($data['value']);
                        }
                    })
                    ->default(Carbon::now()->format('Y-m')),

                Tables\Filters\SelectFilter::make('resident_id')
                    ->label('入居者')
                    ->options(fn () => Resident::where('status', ResidentStatus::Active)->orderBy('room_number')->get()->pluck('full_title', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('charge_item_id')
                    ->label('品目')
                    ->options(fn () => ChargeItem::where('is_active', true)->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyCharges::route('/'),
            'create' => Pages\CreateDailyCharge::route('/create'),
            'edit' => Pages\EditDailyCharge::route('/{record}/edit'),
        ];
    }
}
