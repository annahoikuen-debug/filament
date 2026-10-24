<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatbotFaqSuggestionResource\Pages;
use App\Models\ChatbotFaq;
use App\Models\ChatbotFaqSuggestion;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChatbotFaqSuggestionResource extends Resource
{
    protected static ?string $model = ChatbotFaqSuggestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'チャットボット';

    protected static ?string $navigationLabel = 'FAQ候補（自動提案）';

    protected static ?string $modelLabel = 'FAQ候補';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('representative_message')
                    ->label('代表質問')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('occurrence_count')
                    ->label('発生件数')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('representative_message')
                    ->label('代表質問')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('occurrence_count')
                    ->label('件数')
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('last_occurred_at')
                    ->label('最終発生')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('状態')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => '承認待ち',
                        'approved' => '承認済み',
                        'dismissed' => '却下',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'dismissed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('検出日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'pending' => '承認待ち',
                        'approved' => '承認済み',
                        'dismissed' => '却下',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('承認してFAQ作成')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ChatbotFaqSuggestion $record) => $record->status === 'pending')
                    ->form([
                        Textarea::make('question')
                            ->label('FAQの質問文')
                            ->required()
                            ->default(fn (ChatbotFaqSuggestion $record) => $record->representative_message)
                            ->columnSpanFull(),
                        Textarea::make('answer')
                            ->label('FAQの回答文')
                            ->required()
                            ->columnSpanFull(),
                        Hidden::make('suggestion_id'),
                    ])
                    ->action(function (array $data, ChatbotFaqSuggestion $record) {
                        $faq = ChatbotFaq::create([
                            'question' => $data['question'],
                            'answer' => $data['answer'],
                            'category' => '自動提案',
                            'facility_id' => $record->facility_id,
                            'is_active' => true,
                            'is_public' => false,
                        ]);

                        $record->update([
                            'status' => 'approved',
                            'approved_faq_id' => $faq->id,
                        ]);

                        Notification::make()
                            ->title('FAQを作成しました')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('dismiss')
                    ->label('却下')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (ChatbotFaqSuggestion $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('この候補を却下しますか？同一クラスタの再提案は抑止されます。')
                    ->action(function (ChatbotFaqSuggestion $record) {
                        $record->update(['status' => 'dismissed']);

                        Notification::make()
                            ->title('候補を却下しました')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('occurrence_count', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderByDesc('occurrence_count');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatbotFaqSuggestions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // 自動生成のみ
    }
}
