<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiTokenResource\Pages;
use App\Models\ApiToken;
use App\Models\Facility;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ApiTokenResource extends Resource
{
    protected static ?string $model = ApiToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'APIトークン';

    protected static ?string $modelLabel = 'APIトークン';

    protected static ?string $pluralModelLabel = 'APIトークン';

    protected static ?string $navigationGroup = 'システム設定';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('トークン情報')
                    ->schema([
                        Select::make('facility_id')
                            ->label('施設')
                            ->options(fn () => Facility::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(fn () => Auth::user()?->facility_id),

                        TextInput::make('name')
                            ->label('トークン名')
                            ->required()
                            ->maxLength(100)
                            ->helperText('連携先の外部システム名などを入力してください'),

                        CheckboxList::make('abilities')
                            ->label('権限')
                            ->options([
                                'invoices.read' => '請求データの読み取り (invoices.read)',
                                'invoices.write' => '請求データの書き込み (invoices.write)',
                            ])
                            ->required()
                            ->columns(2)
                            ->helperText('外部システムに必要な最低限の権限のみ付与してください'),

                        DateTimePicker::make('expires_at')
                            ->label('有効期限')
                            ->native(false)
                            ->nullable()
                            ->helperText('空欄の場合は無期限で有効です'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('facility.name')
                    ->label('施設')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('トークン名')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('abilities')
                    ->label('権限')
                    ->formatStateUsing(fn ($state) => collect(is_array($state) ? $state : json_decode((string) $state, true) ?? [])
                        ->implode(', ')),

                TextColumn::make('last_used_at')
                    ->label('最終使用')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('未使用'),

                TextColumn::make('expires_at')
                    ->label('有効期限')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('無期限'),

                TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListApiTokens::route('/'),
            'create' => Pages\CreateApiToken::route('/create'),
            'edit' => Pages\EditApiToken::route('/{record}/edit'),
        ];
    }
}
