<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdfTemplateSettingsResource\Pages;
use App\Models\PdfTemplateSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PdfTemplateSettingsResource extends Resource
{
    protected static ?string $model = PdfTemplateSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'PDFテンプレート設定';

    protected static ?string $modelLabel = 'テンプレート';

    protected static ?string $pluralModelLabel = 'PDFテンプレート設定';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本設定')
                    ->schema([
                        Forms\Components\Select::make('key')
                            ->label('テンプレート種別')
                            ->options([
                                'invoice' => '請求書',
                                'receipt' => '領収証',
                            ])
                            ->required()
                            ->native(false)
                            ->disabledOn('edit'),

                        Forms\Components\Select::make('locale')
                            ->label('ロケール')
                            ->options([
                                'ja' => '日本語',
                                'en' => 'English',
                            ])
                            ->default('ja')
                            ->required()
                            ->native(false)
                            ->disabledOn('edit'),

                        Forms\Components\Select::make('theme')
                            ->label('テーマ')
                            ->options(PdfTemplateSettings::getAvailableThemes())
                            ->default('standard')
                            ->required()
                            ->native(false)
                            ->disabledOn('edit'),

                        Forms\Components\TextInput::make('name')
                            ->label('表示名')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),

                        Forms\Components\Toggle::make('is_default')
                            ->label('デフォルト')
                            ->helperText('同じ種別・ロケール・テーマでデフォルトは1つのみ有効になります。')
                            ->default(false),

                        Forms\Components\TextInput::make('version')
                            ->label('バージョン')
                            ->numeric()
                            ->default(1)
                            ->disabled(),

                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(2)
                            ->maxLength(500),
                    ])->columns(2),

                Forms\Components\Section::make('用紙・余白設定')
                    ->schema([
                        Forms\Components\Select::make('paper_size')
                            ->label('用紙サイズ')
                            ->options([
                                'a4' => 'A4',
                                'a5' => 'A5',
                                'letter' => 'Letter',
                                'legal' => 'Legal',
                            ])
                            ->default('a4')
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('paper_orientation')
                            ->label('用紙向き')
                            ->options([
                                'portrait' => '縦',
                                'landscape' => '横',
                            ])
                            ->default('portrait')
                            ->native(false)
                            ->required(),

                        Forms\Components\TextInput::make('margin_top')
                            ->label('上余白 (mm)')
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\TextInput::make('margin_right')
                            ->label('右余白 (mm)')
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\TextInput::make('margin_bottom')
                            ->label('下余白 (mm)')
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\TextInput::make('margin_left')
                            ->label('左余白 (mm)')
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->maxValue(100),
                    ])->columns(4),

                Forms\Components\Section::make('フォント設定')
                    ->schema([
                        Forms\Components\TextInput::make('font_family')
                            ->label('フォントファミリ (CSS)')
                            ->default("'Yu Mincho', 'YuMincho', 'Yu Gothic', 'YuGothic', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif")
                            ->maxLength(255),

                        Forms\Components\TextInput::make('font_size')
                            ->label('フォントサイズ (pt)')
                            ->numeric()
                            ->step(0.5)
                            ->default(10.5)
                            ->minValue(6)
                            ->maxValue(24),

                        Forms\Components\TextInput::make('line_height')
                            ->label('行間倍率')
                            ->numeric()
                            ->step(0.1)
                            ->default(1.6)
                            ->minValue(1.0)
                            ->maxValue(3.0),
                    ])->columns(3),

                Forms\Components\Section::make('カラー設定 (HEX)')
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('プライマリカラー')
                            ->default('#1e3a8a'),

                        Forms\Components\ColorPicker::make('secondary_color')
                            ->label('セカンダリカラー')
                            ->default('#374151'),

                        Forms\Components\ColorPicker::make('accent_color')
                            ->label('アクセントカラー')
                            ->default('#dc2626'),

                        Forms\Components\ColorPicker::make('background_color')
                            ->label('背景色')
                            ->default('#ffffff'),

                        Forms\Components\ColorPicker::make('text_color')
                            ->label('テキスト色')
                            ->default('#111827'),

                        Forms\Components\ColorPicker::make('border_color')
                            ->label('罫線色')
                            ->default('#e5e7eb'),

                        Forms\Components\ColorPicker::make('header_bg_color')
                            ->label('ヘッダー背景色')
                            ->default('#f9fafb'),

                        Forms\Components\ColorPicker::make('total_bg_color')
                            ->label('合計欄背景色')
                            ->default('#fef3c7'),

                        Forms\Components\ColorPicker::make('tax_table_header_bg')
                            ->label('税内訳テーブルヘッダー背景')
                            ->default('#f3f4f6'),

                        Forms\Components\ColorPicker::make('table_header_bg')
                            ->label('テーブルヘッダー背景')
                            ->default('#f8fafc'),

                        Forms\Components\ColorPicker::make('table_row_even_bg')
                            ->label('テーブル偶数行背景')
                            ->default('#ffffff'),

                        Forms\Components\ColorPicker::make('table_row_odd_bg')
                            ->label('テーブル奇数行背景')
                            ->default('#fafafa'),

                        Forms\Components\ColorPicker::make('table_border_color')
                            ->label('テーブル罫線色')
                            ->default('#e5e7eb'),
                    ])->columns(4),

                Forms\Components\Section::make('表示項目制御')
                    ->schema([
                        Forms\Components\Toggle::make('show_facility_logo')
                            ->label('施設ロゴ表示')
                            ->default(false),

                        Forms\Components\FileUpload::make('facility_logo_path')
                            ->label('ロゴ画像')
                            ->image()
                            ->directory('pdf-logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->imagePreviewHeight('100')
                            ->visible(fn (Forms\Get $get) => $get('show_facility_logo')),

                        Forms\Components\Toggle::make('show_facility_info')
                            ->label('施設情報表示')
                            ->default(true),

                        Forms\Components\Toggle::make('show_tax_breakdown')
                            ->label('税内訳表示')
                            ->default(true),

                        Forms\Components\Toggle::make('show_daily_charges_detail')
                            ->label('日々明細表示')
                            ->default(true),

                        Forms\Components\Toggle::make('show_qr_code')
                            ->label('QRコード表示')
                            ->default(false),

                        Forms\Components\Textarea::make('qr_code_data')
                            ->label('QRコードデータテンプレート')
                            ->rows(3)
                            ->visible(fn (Forms\Get $get) => $get('show_qr_code'))
                            ->helperText('QRコード生成用のデータテンプレート（変数展開可能）'),

                        Forms\Components\Toggle::make('show_page_numbers')
                            ->label('ページ番号表示')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('カスタムHTML')
                    ->schema([
                        Forms\Components\Textarea::make('header_html')
                            ->label('カスタムヘッダーHTML')
                            ->rows(5)
                            ->helperText('Bladeディレクティブ使用可。$data, $template, $facility 等が利用可能'),

                        Forms\Components\Textarea::make('footer_html')
                            ->label('カスタムフッターHTML')
                            ->rows(5)
                            ->helperText('Bladeディレクティブ使用可。$data, $template, $facility 等が利用可能'),

                        Forms\Components\Textarea::make('custom_css')
                            ->label('カスタムCSS')
                            ->rows(8)
                            ->helperText('カスタムCSS（SCSS記法も可）。テンプレートのスタイルを上書きできます。'),

                        Forms\Components\KeyValue::make('translations')
                            ->label('翻訳文字列')
                            ->keyLabel('翻訳キー')
                            ->valueLabel('翻訳値')
                            ->addActionLabel('翻訳を追加')
                            ->helperText('多言語翻訳文字列。キーは "ja.key" や "en.key" の形式で指定。Blade内で $template->translate("key") で呼び出し可能'),
                    ])->columns(2),

                Forms\Components\Section::make('テーマ設定 (JSON)')
                    ->schema([
                        Forms\Components\Textarea::make('theme_config')
                            ->label('テーマ固有設定 (JSON)')
                            ->rows(10)
                            ->helperText('テーマのデフォルト設定を上書きするJSON。例: {"primary_color": "#ff0000", "font_size": 12}'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('種別')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'invoice' => 'primary',
                        'receipt' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'invoice' => '請求書',
                        'receipt' => '領収証',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('locale')
                    ->label('ロケール')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ja' => 'info',
                        'en' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ja' => '日本語',
                        'en' => 'English',
                        default => strtoupper($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('theme')
                    ->label('テーマ')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => PdfTemplateSettings::getAvailableThemes()[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('表示名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('デフォルト')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('version')
                    ->label('バージョン')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->defaultSort(['key' => 'asc', 'locale' => 'asc', 'theme' => 'asc', 'is_default' => 'desc', 'version' => 'desc'])
            ->filters([
                Tables\Filters\SelectFilter::make('key')
                    ->label('種別')
                    ->options([
                        'invoice' => '請求書',
                        'receipt' => '領収証',
                    ])
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('locale')
                    ->label('ロケール')
                    ->options([
                        'ja' => '日本語',
                        'en' => 'English',
                    ])
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('theme')
                    ->label('テーマ')
                    ->options(PdfTemplateSettings::getAvailableThemes())
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効/無効')
                    ->placeholder('すべて')
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('デフォルト')
                    ->placeholder('すべて')
                    ->trueLabel('デフォルトのみ')
                    ->falseLabel('デフォルト以外'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('複製')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function (PdfTemplateSettings $record) {
                        $newRecord = $record->replicate();
                        $newRecord->name = $record->name . ' (コピー)';
                        $newRecord->is_default = false;
                        $newRecord->version = 1;
                        $newRecord->save();

                        \Filament\Notifications\Notification::make()
                            ->title('テンプレートを複製しました')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PdfTemplateSettings $record) => !$record->is_default)
                    ->before(function (PdfTemplateSettings $record) {
                        if ($record->is_default) {
                            \Filament\Notifications\Notification::make()
                                ->title('デフォルトテンプレートは削除できません')
                                ->danger()
                                ->send();
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modifyQueryUsing(fn ($query) => $query->where('is_default', false))
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPdfTemplateSettings::route('/'),
            'create' => Pages\CreatePdfTemplateSettings::route('/create'),
            'edit' => Pages\EditPdfTemplateSettings::route('/{record}/edit'),
        ];
    }
}