<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatbotFaqResource\Pages;
use App\Models\ChatbotFaq;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChatbotFaqResource extends Resource
{
    protected static ?string $model = ChatbotFaq::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'チャットボットFAQ';

    protected static ?string $modelLabel = 'FAQ';

    protected static ?string $pluralModelLabel = 'チャットボットFAQ';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationGroup = '設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('FAQ情報')
                    ->description('チャットボットの自動応答内容')
                    ->schema([
                        Forms\Components\TextInput::make('question')
                            ->label('質問')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('例: 利用料の支払い方法を教えてください'),

                        Forms\Components\TagsInput::make('keywords')
                            ->label('キーワード')
                            ->placeholder('例: 支払い, 振込, 入金')
                            ->helperText('質問文から抽出される語と一致するキーワードを登録'),

                        Forms\Components\Textarea::make('answer')
                            ->label('回答')
                            ->required()
                            ->rows(5)
                            ->placeholder('自動応答として返す文章'),

                        Forms\Components\TextInput::make('category')
                            ->label('カテゴリ')
                            ->maxLength(50)
                            ->default('一般'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),

                        Forms\Components\Toggle::make('is_public')
                            ->label('公開サイトに表示')
                            ->default(false)
                            ->helperText('公開サイトのチャットボットからの参照を許可します'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Select::make('facility_id')
                            ->label('施設（未選択=全施設共通）')
                            ->relationship('facility', 'name')
                            ->nullable()
                            ->visible(fn () => Auth::user()?->isCorporateAdmin())
                            ->helperText('施設管理者は自施設のFAQのみ作成できます'),
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
                Tables\Columns\TextColumn::make('question')
                    ->label('質問')
                    ->searchable()
                    ->weight('bold')
                    ->limit(50),

                Tables\Columns\TextColumn::make('category')
                    ->label('カテゴリ')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('施設')
                    ->badge()
                    ->toggleable()
                    ->visible(fn () => Auth::user()?->isCorporateAdmin()),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('公開')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効/無効')
                    ->placeholder('すべて')
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ'),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('公開/非公開')
                    ->placeholder('すべて')
                    ->trueLabel('公開のみ')
                    ->falseLabel('非公開のみ'),

                Tables\Filters\SelectFilter::make('category')
                    ->label('カテゴリ')
                    ->options(fn () => ChatbotFaq::distinct()->pluck('category', 'category')->filter()->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->isFacilityAdmin() && $user->facility_id) {
            $query->where(fn (Builder $q) => $q
                ->whereNull('facility_id')
                ->orWhere('facility_id', $user->facility_id));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatbotFaqs::route('/'),
            'create' => Pages\CreateChatbotFaq::route('/create'),
            'edit' => Pages\EditChatbotFaq::route('/{record}/edit'),
        ];
    }
}
