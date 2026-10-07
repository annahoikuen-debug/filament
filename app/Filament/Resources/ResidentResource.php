<?php

namespace App\Filament\Resources;

use App\Enums\ResidentStatus;
use App\Filament\Resources\ResidentResource\Pages;
use App\Models\Resident;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResidentResource extends Resource
{
    protected static ?string $model = Resident::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = '入居者マスタ';

    protected static ?string $modelLabel = '入居者';

    protected static ?string $pluralModelLabel = '入居者一覧';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->description('入居者の居室および基本契約情報')
                    ->schema([
                        Forms\Components\TextInput::make('room_number')
                            ->label('部屋番号')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('例: 101')
                            ->autofocus(),

                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options(ResidentStatus::class)
                            ->default(ResidentStatus::Active)
                            ->native(false)
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('氏名')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('例: 山田 太郎'),

                        Forms\Components\TextInput::make('name_kana')
                            ->label('フリガナ')
                            ->maxLength(255)
                            ->placeholder('例: ヤマダ タロウ'),

                        Forms\Components\DatePicker::make('move_in_date')
                            ->label('入居開始日')
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->default(now()->startOfYear())
                            ->required(),

                        Forms\Components\DatePicker::make('move_out_date')
                            ->label('退去日 (未定は空欄)')
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->nullable()
                            ->rule(function (Forms\Get $get) {
                                return $get('move_in_date')
                                    ? 'after_or_equal:move_in_date'
                                    : null;
                            }),
                    ])->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),

                Forms\Components\Section::make('月額基本料金')
                    ->description('毎月の請求基本額（税込み）')
                    ->schema([
                        Forms\Components\TextInput::make('base_rent')
                            ->label('基本家賃(月額)')
                            ->numeric()
                            ->inputMode('numeric')
                            ->prefix('¥')
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('base_management_fee')
                            ->label('基本管理費(月額)')
                            ->numeric()
                            ->inputMode('numeric')
                            ->prefix('¥')
                            ->default(0)
                            ->required(),
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
                Tables\Columns\TextColumn::make('room_number')
                    ->label('部屋番号')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('氏名')
                    ->description(fn (Resident $record): ?string => $record->name_kana)
                    ->searchable(['name', 'name_kana']),

                Tables\Columns\TextColumn::make('move_in_date')
                    ->label('入居日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('base_rent')
                    ->label('家賃')
                    ->money('JPY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('base_management_fee')
                    ->label('管理費')
                    ->money('JPY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('base_monthly_total')
                    ->label('月額固定計')
                    ->money('JPY')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('ステータス')
                    ->badge(),
            ])
            ->defaultSort('room_number', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options(ResidentStatus::class)
                    ->default(ResidentStatus::Active->value),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResidents::route('/'),
            'create' => Pages\CreateResident::route('/create'),
            'edit' => Pages\EditResident::route('/{record}/edit'),
        ];
    }
}
