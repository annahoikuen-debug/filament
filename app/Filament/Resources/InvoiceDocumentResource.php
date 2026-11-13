<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceDocumentResource\Pages;
use App\Models\InvoiceDocument;
use App\Models\MonthlyInvoice;
use Carbon\Carbon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * 電子帳簿保存法対応：発行済み帳票の検索・照会リソース。
 *
 * 請求書・領収書の発行記録を、取引年月・宛名・金額で検索でき、
 * 電帳法の検索要件（3項目）を満たす。
 */
class InvoiceDocumentResource extends Resource
{
    protected static ?string $model = InvoiceDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '帳票発行履歴';

    protected static ?string $modelLabel = '帳票発行記録';

    protected static ?string $pluralModelLabel = '帳票発行履歴';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = '出力・監査';

    // 読み取り専用（作成・編集・削除を禁止。記録はPDF発行時に自動生成される）
    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('発行日')
                    ->date('Y/m/d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('種別')
                    ->formatStateUsing(fn (string $state): string => InvoiceDocument::TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('issued_to')
                    ->label('宛先（入居者名）')
                    ->searchable(),

                Tables\Columns\TextColumn::make('monthly_invoice.resident.room_number')
                    ->label('部屋番号')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('合計金額')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_hash')
                    ->label('ファイルハッシュ(SHA-256)')
                    ->limit(30)
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('記録日時')
                    ->dateTime('Y/m/d H:i')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('種別')
                    ->options(InvoiceDocument::TYPES),

                Tables\Filters\SelectFilter::make('monthly_invoice_id')
                    ->label('請求データ')
                    ->options(fn () => MonthlyInvoice::query()
                        ->with('resident')
                        ->get()
                        ->mapWithKeys(fn ($inv) => [$inv->id => $inv->resident->name.' ('.$inv->billing_year_month.')']))
                    ->searchable(),

                Tables\Filters\Filter::make('issued_at_range')
                    ->label('発行日範囲')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('開始日')
                            ->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('終了日')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('issued_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('issued_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('ダウンロード')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (InvoiceDocument $record): string => route('invoice-documents.download', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('verify')
                    ->label('改ざん検証')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->action(fn (InvoiceDocument $record) => $record->verifyHash(storage_path('app/'.$record->file_path))
                        ? \Filament\Notifications\Notification::make()->title('ハッシュ一致（改ざんなし）')->success()->send()
                        : \Filament\Notifications\Notification::make()->title('ハッシュ不一致（改ざんの可能性）')->danger()->send()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->disabled(), // 監査記録のため削除禁止
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceDocuments::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // 施設スコープ：施設管理者は自施設のみ
        $user = Auth::user();
        if ($user && $user->requiresFacilityScope()) {
            $query->whereHas('monthlyInvoices.resident', fn ($q) => $q->where('facility_id', $user->facility_id));
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false; // 自動生成のみ
    }

    public static function canEdit($record): bool
    {
        return false; // 監査記録のため編集禁止
    }

    public static function canDelete($record): bool
    {
        return false; // 監査記録のため削除禁止
    }
}
