<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdfTemplateSettingResource\Pages;
use App\Models\PdfTemplateSetting;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PdfTemplateSettingResource extends Resource
{
    protected static ?string $model = PdfTemplateSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'PDFテンプレート';

    protected static ?string $modelLabel = 'テンプレート設定';

    protected static ?string $pluralModelLabel = 'PDFテンプレート';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'システム設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('基本情報')
                    ->schema([
                        Select::make('key')
                            ->label('テンプレートキー')
                            ->required()
                            ->options([
                                'invoice' => '請求書',
                                'receipt' => '領収書',
                                'invoice_bulk' => '一括請求書',
                            ])
                            ->native(false)
                            ->helperText('このテンプレートを適用する帳票種別'),

                        TextInput::make('name')
                            ->label('表示名')
                            ->required()
                            ->maxLength(100),

                        Textarea::make('description')
                            ->label('説明')
                            ->rows(2)
                            ->maxLength(500),

                        Select::make('paper_size')
                            ->label('用紙サイズ')
                            ->required()
                            ->options([
                                'a4' => 'A4',
                                'a5' => 'A5',
                                'letter' => 'Letter',
                                'legal' => 'Legal',
                            ])
                            ->native(false)
                            ->default('a4'),

                        Select::make('paper_orientation')
                            ->label('用紙向き')
                            ->required()
                            ->options([
                                'portrait' => '縦',
                                'landscape' => '横',
                            ])
                            ->native(false)
                            ->default('portrait'),
                    ])
                    ->columns(2),

                Section::make('余白設定 (mm)')
                    ->schema([
                        TextInput::make('margin_top')
                            ->label('上')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(15)
                            ->suffix('mm'),

                        TextInput::make('margin_right')
                            ->label('右')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(15)
                            ->suffix('mm'),

                        TextInput::make('margin_bottom')
                            ->label('下')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(15)
                            ->suffix('mm'),

                        TextInput::make('margin_left')
                            ->label('左')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(15)
                            ->suffix('mm'),
                    ])
                    ->columns(4),

                Section::make('フォント設定')
                    ->schema([
                        TextInput::make('font_family')
                            ->label('フォントファミリ')
                            ->required()
                            ->maxLength(200)
                            ->default('YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif')
                            ->helperText('CSS font-family 形式で指定（カンマ区切り）'),

                        TextInput::make('font_size')
                            ->label('基本フォントサイズ')
                            ->required()
                            ->numeric()
                            ->minValue(8)
                            ->maxValue(18)
                            ->default(11)
                            ->suffix('pt'),

                        TextInput::make('line_height')
                            ->label('行間倍率')
                            ->required()
                            ->numeric()
                            ->step(0.1)
                            ->minValue(1.0)
                            ->maxValue(3.0)
                            ->default(1.6)
                            ->helperText('1.0 = 標準, 1.5 = 1.5倍, 2.0 = 2倍'),
                    ])
                    ->columns(3),

                Section::make('カラー設定 (HEX)')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('プライマリ')
                            ->default('#1f2937'),

                        ColorPicker::make('secondary_color')
                            ->label('セカンダリ')
                            ->default('#4b5563'),

                        ColorPicker::make('accent_color')
                            ->label('アクセント')
                            ->default('#dc2626'),

                        ColorPicker::make('background_color')
                            ->label('背景色')
                            ->default('#ffffff'),

                        ColorPicker::make('text_color')
                            ->label('テキスト色')
                            ->default('#111827'),

                        ColorPicker::make('border_color')
                            ->label('罫線色')
                            ->default('#d1d5db'),

                        ColorPicker::make('header_bg_color')
                            ->label('ヘッダー背景')
                            ->default('#f9fafb'),

                        ColorPicker::make('total_bg_color')
                            ->label('合計欄背景')
                            ->default('#fef3c7'),

                        ColorPicker::make('tax_table_header_bg')
                            ->label('税内訳テーブルヘッダー')
                            ->default('#f3f4f6'),

                        ColorPicker::make('table_header_bg')
                            ->label('テーブルヘッダー背景')
                            ->default('#f9fafb'),

                        ColorPicker::make('table_row_even_bg')
                            ->label('テーブル偶数行背景')
                            ->default('#ffffff'),

                        ColorPicker::make('table_row_odd_bg')
                            ->label('テーブル奇数行背景')
                            ->default('#f9fafb'),

                        ColorPicker::make('table_border_color')
                            ->label('テーブル罫線色')
                            ->default('#e5e7eb'),
                    ])
                    ->columns(4),

                Section::make('表示項目制御')
                    ->schema([
                        Toggle::make('show_facility_logo')
                            ->label('施設ロゴ表示')
                            ->default(false),

                        FileUpload::make('facility_logo_path')
                            ->label('ロゴ画像')
                            ->image()
                            ->directory('pdf-logos')
                            ->visibility('public')
                            ->maxSize(1024)
                            ->helperText('推奨: 幅200px以内, PNG/SVG'),

                        Toggle::make('show_facility_info')
                            ->label('施設情報表示')
                            ->default(true),

                        Toggle::make('show_tax_breakdown')
                            ->label('税内訳表示')
                            ->default(true),

                        Toggle::make('show_daily_charges_detail')
                            ->label('日々明細表示')
                            ->default(true),

                        Toggle::make('show_qr_code')
                            ->label('QRコード表示')
                            ->default(false),

                        Textarea::make('qr_code_data')
                            ->label('QRコードデータテンプレート')
                            ->rows(2)
                            ->placeholder('例: https://example.com/verify?no={receipt_number}&date={paid_at}')
                            ->helperText('変数: {receipt_number}, {paid_at}, {total_amount}, {facility_name}'),
                    ])
                    ->columns(2),

                Section::make('カスタムHTML')
                    ->schema([
                        Textarea::make('header_html')
                            ->label('カスタムヘッダーHTML')
                            ->rows(3)
                            ->placeholder('<div style="text-align:center">独自ヘッダー</div>')
                            ->helperText('ページ上部に挿入されるHTML（CSS変数使用可）'),

                        Textarea::make('footer_html')
                            ->label('カスタムフッターHTML')
                            ->rows(3)
                            ->placeholder('<div style="text-align:center; font-size:8pt">Page {page} of {pages}</div>')
                            ->helperText('ページ下部に挿入されるHTML（{page}, {pages} 等の置換可能）'),
                    ])
                    ->columns(2),

                Section::make('テーブルスタイル')
                    ->schema([
                        ColorPicker::make('table_header_bg')
                            ->label('テーブルヘッダー背景')
                            ->default('#f9fafb'),

                        ColorPicker::make('table_row_even_bg')
                            ->label('テーブル偶数行背景')
                            ->default('#ffffff'),

                        ColorPicker::make('table_row_odd_bg')
                            ->label('テーブル奇数行背景')
                            ->default('#f9fafb'),

                        ColorPicker::make('table_border_color')
                            ->label('テーブル罫線色')
                            ->default('#e5e7eb'),
                    ])
                    ->columns(4),

                Section::make('ステータス・メタ')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('有効')
                            ->default(true)
                            ->helperText('無効にするとこのテンプレートは使用されません'),

                        Toggle::make('is_default')
                            ->label('デフォルト')
                            ->default(false)
                            ->helperText('同じキーで複数ある場合、これが優先されます'),

                        TextInput::make('version')
                            ->label('バージョン')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),

                        Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('キー')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'invoice' => 'primary',
                        'receipt' => 'success',
                        'invoice_bulk' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('名前')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('paper_size')
                    ->label('用紙')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('paper_orientation')
                    ->label('向き')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'landscape' ? 'warning' : 'gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('既定')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Ver')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('key')
            ->defaultSort('is_default', 'desc')
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
            'index' => Pages\ListPdfTemplateSettings::route('/'),
            'create' => Pages\CreatePdfTemplateSetting::route('/create'),
            'edit' => Pages\EditPdfTemplateSetting::route('/{record}/edit'),
        ];
    }
}