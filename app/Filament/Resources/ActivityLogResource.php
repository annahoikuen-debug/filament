<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = '監査ログ';

    protected static ?string $modelLabel = 'アクティビティログ';

    protected static ?string $pluralModelLabel = '監査ログ一覧';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationGroup = 'システム管理';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isCorporateAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('ログ詳細')
                    ->schema([
                        Forms\Components\TextInput::make('log_name')
                            ->label('ログ名')
                            ->disabled(),

                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->disabled()
                            ->rows(3),

                        Forms\Components\TextInput::make('subject_type')
                            ->label('対象モデル')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject_id')
                            ->label('対象ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('causer_type')
                            ->label('実行者モデル')
                            ->disabled(),

                        Forms\Components\TextInput::make('causer_id')
                            ->label('実行者ID')
                            ->disabled(),

                        Forms\Components\KeyValue::make('properties')
                            ->label('変更内容')
                            ->disabled()
                            ->keyLabel('フィールド')
                            ->valueLabel('値')
                            ->addActionLabel('追加')
                            ->deleteActionLabel('削除'),

                        Forms\Components\TextInput::make('event')
                            ->label('イベント')
                            ->disabled(),

                        Forms\Components\TextInput::make('batch_uuid')
                            ->label('バッチUUID')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('作成日時')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('updated_at')
                            ->label('更新日時')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('日時')
                    ->dateTime('Y/m/d H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('ログ名')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('説明')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('対象モデル')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('対象ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer_type')
                    ->label('実行者')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge()
                    ->color('success')
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer_id')
                    ->label('実行者ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('イベント')
                    ->badge()
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                        'info' => 'status_changed',
                    ])
                    ->searchable(),

                Tables\Columns\TextColumn::make('batch_uuid')
                    ->label('バッチUUID')
                    ->limit(20)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('ログ名')
                    ->options([
                        'monthly_invoice' => '月次請求',
                        'resident' => '入居者',
                        'charge_item' => '請求品目',
                        'facility' => '施設',
                        'tax_setting' => '税率設定',
                        'user' => 'ユーザー',
                        'daily_charge' => '日々の自費',
                        'accounting_export_profile' => '会計連携プロファイル',
                    ])
                    ->searchable(),

                Tables\Filters\SelectFilter::make('event')
                    ->label('イベント')
                    ->options([
                        'created' => '作成',
                        'updated' => '更新',
                        'deleted' => '削除',
                        'status_changed' => 'ステータス変更',
                    ])
                    ->searchable(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('開始日'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('終了日'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('facility_id')
                    ->label('施設で絞り込み')
                    ->form([
                        Forms\Components\Select::make('facility_id')
                            ->label('施設')
                            ->relationship('facility', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['facility_id'],
                            fn (Builder $query, $facilityId): Builder => $query->whereHas('subject', function ($q) use ($facilityId) {
                                $q->where('facility_id', $facilityId);
                            }),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('監査ログがありません')
            ->emptyStateDescription('システム操作の履歴がここに表示されます。');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['subject', 'causer'])
            ->when(
                auth()->user()?->isFacilityAdmin() && auth()->user()?->facility_id,
                fn (Builder $query) => $query->whereHas('subject', function ($q) {
                    $q->where('facility_id', auth()->user()->facility_id);
                }),
            );
    }
}
