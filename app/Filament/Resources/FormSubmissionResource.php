<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormSubmissionResource\Pages;
use App\Models\FormSubmission;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'フォーム受信';

    protected static ?string $modelLabel = 'フォーム受信';

    protected static ?string $pluralModelLabel = 'フォーム受信一覧';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationGroup = '営業';

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('受信内容')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('type')
                            ->label('フォーム種別')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'catalog' => 'info',
                                'demo' => 'success',
                                'diagnosis' => 'warning',
                                'prospect' => 'gray',
                                'inquiry' => 'primary',
                                default => 'gray',
                            }),
                        \Filament\Infolists\Components\TextEntry::make('company')
                            ->label('施設・法人名')
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('お名前')
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('email')
                            ->label('メールアドレス')
                            ->copyable()
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('phone')
                            ->label('電話番号')
                            ->copyable()
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('受付日時')
                            ->dateTime('Y/m/d H:i'),
                    ])->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                \Filament\Infolists\Components\Section::make('詳細（フォーム入力内容）')
                    ->schema([
                        \Filament\Infolists\Components\KeyValueEntry::make('payload')
                            ->label('入力項目')
                            ->keyLabel('項目')
                            ->valueLabel('内容'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('種別')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'catalog' => '資料請求',
                        'demo' => '無料デモ',
                        'diagnosis' => '診断書',
                        'prospect' => '見込み客',
                        'inquiry' => 'お問い合わせ',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'catalog' => 'info',
                        'demo' => 'success',
                        'diagnosis' => 'warning',
                        'prospect' => 'gray',
                        'inquiry' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('company')
                    ->label('施設・法人名')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('name')
                    ->label('お名前')
                    ->searchable()
                    ->weight('bold')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable()
                    ->copyable()
                    ->limit(35)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('受付日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('フォーム種別')
                    ->options([
                        'catalog' => '資料請求',
                        'demo' => '無料デモ',
                        'diagnosis' => '診断書',
                        'prospect' => '見込み客',
                        'inquiry' => 'お問い合わせ',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('フォーム受信はまだありません')
            ->emptyStateDescription('コーポレートサイトのフォームから送信されると、ここに表示されます');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormSubmissions::route('/'),
            'view' => Pages\ViewFormSubmission::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
