<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatbotSynonymResource\Pages;
use App\Models\ChatbotSynonym;
use App\Services\Chatbot\SynonymExpander;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChatbotSynonymResource extends Resource
{
    protected static ?string $model = ChatbotSynonym::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationLabel = 'チャットボット同義語';

    protected static ?string $modelLabel = '同義語';

    protected static ?string $pluralModelLabel = 'チャットボット同義語';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationGroup = '設定';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isFacilityAdmin() || Auth::user()?->isCorporateAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('同義語情報')
                    ->description('職員が使いそうな表現を、チャットボットが理解できる標準語に変換します')
                    ->schema([
                        Forms\Components\TextInput::make('from_word')
                            ->label('変換前（入力されやすい語）')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: 引き落とし')
                            ->helperText('職員が入力しそうな言葉・言い回し'),

                        Forms\Components\TextInput::make('to_word')
                            ->label('変換後（標準語）')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: 口座振替')
                            ->helperText('チャットボットが理解できる標準的な言葉'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),

                        Forms\Components\Select::make('facility_id')
                            ->label('施設（未選択=全施設共通）')
                            ->relationship('facility', 'name')
                            ->nullable()
                            ->visible(fn () => Auth::user()?->isCorporateAdmin())
                            ->helperText('施設管理者は自施設の同義語のみ作成できます'),

                        Forms\Components\Placeholder::make('preview')
                            ->label('変換プレビュー')
                            ->content(function (Forms\Get $get) {
                                $from = (string) $get('from_word');
                                $to = (string) $get('to_word');

                                if ($from === '' || $to === '') {
                                    return '変換前・変換後の両方を入力すると、例文での変換結果が表示されます。';
                                }

                                $sample = "{$from}の請求額を教えてください";
                                $expander = new SynonymExpander([$from => $to]);

                                return "例文: {$sample}\n→ 変換後: {$expander->expand($sample)}";
                            }),
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
                Tables\Columns\TextColumn::make('from_word')
                    ->label('変換前')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('to_word')
                    ->label('変換後')
                    ->searchable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('施設')
                    ->badge()
                    ->placeholder('全施設共通')
                    ->toggleable()
                    ->visible(fn () => Auth::user()?->isCorporateAdmin()),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効/無効')
                    ->placeholder('すべて')
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ'),
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
        if ($user && $user->requiresFacilityScope()) {
            $query->where(fn (Builder $q) => $q
                ->whereNull('facility_id')
                ->orWhere('facility_id', $user->facility_id));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatbotSynonyms::route('/'),
            'create' => Pages\CreateChatbotSynonym::route('/create'),
            'edit' => Pages\EditChatbotSynonym::route('/{record}/edit'),
        ];
    }
}
