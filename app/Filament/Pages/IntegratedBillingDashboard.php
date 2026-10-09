<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class IntegratedBillingDashboard extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = '統合請求管理';

    protected static ?string $title = '統合請求管理ダッシュボード';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = '請求管理';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'billing_year_month' => now()->format('Y-m'),
            'facility_id' => Auth::user()?->isCorporateAdmin() ? null : Auth::user()?->facility_id,
        ];
        $this->tableFilters = $this->filters;
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('billing_year_month')
                ->label('請求年月')
                ->options(fn () => MonthlyInvoice::query()
                    ->distinct()
                    ->orderBy('billing_year_month', 'desc')
                    ->pluck('billing_year_month', 'billing_year_month')
                    ->toArray())
                ->default(now()->format('Y-m'))
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->resetTable()),

            Select::make('facility_id')
                ->label('施設')
                ->relationship('facility', 'name')
                ->default(fn () => Auth::user()?->isCorporateAdmin() ? null : Auth::user()?->facility_id)
                ->visible(fn () => Auth::user()?->isCorporateAdmin())
                ->required()
                ->native(false)
                ->placeholder('全施設')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTable()),
        ];
    }

    public function table(Table $table): Table
    {
        $yearMonth = $this->filters['billing_year_month'] ?? now()->format('Y-m');
        $facilityId = $this->filters['facility_id'];

        return $table
            ->query($this->getTableQuery($yearMonth, $facilityId))
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters())
            ->headerActions($this->getHeaderActions())
            ->defaultSort('room_number')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function getTableQuery(string $yearMonth, ?int $facilityId): Builder
    {
        return Resident::query()
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId))
            ->where(function ($q) use ($yearMonth) {
                $startDate = \Carbon\Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
                $endDate = \Carbon\Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

                $q->where(function ($qq) use ($startDate) {
                    $qq->whereNull('move_out_date')
                        ->orWhere('move_out_date', '>=', $startDate);
                })
                    ->where(function ($qq) use ($endDate) {
                        $qq->whereNull('move_in_date')
                            ->orWhere('move_in_date', '<=', $endDate);
                    });
            })
            ->with([
                'monthlyInvoices' => fn ($q) => $q->where('billing_year_month', $yearMonth),
                'serviceInvoices' => fn ($q) => $q->where('billing_year_month', $yearMonth)
                    ->where('status', '!=', 'draft'),
            ])
            ->orderBy('room_number');
    }

    protected function getTableColumns(): array
    {
        $serviceTypes = ServiceType::getOrderedCases();
        $serviceLabels = [];
        foreach ($serviceTypes as $st) {
            $serviceLabels[$st->value] = $st->getLabel();
        }

        $columns = [
            TextColumn::make('room_number')
                ->label('部屋番号')
                ->badge()
                ->color('primary')
                ->sortable()
                ->weight('bold'),

            TextColumn::make('name')
                ->label('入居者名')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            TextColumn::make('monthlyInvoices.0.rent_subtotal')
                ->label('家賃')
                ->money('JPY')
                ->getStateUsing(fn (Resident $record) => $record->monthlyInvoices->first()?->rent_subtotal ?? 0)
                ->summarize(Sum::make()->money('JPY')->label('家賃計')),

            TextColumn::make('monthlyInvoices.0.management_fee_subtotal')
                ->label('管理費')
                ->money('JPY')
                ->getStateUsing(fn (Resident $record) => $record->monthlyInvoices->first()?->management_fee_subtotal ?? 0)
                ->summarize(Sum::make()->money('JPY')->label('管理費計')),

            TextColumn::make('monthlyInvoices.0.service_subtotal')
                ->label('自費')
                ->money('JPY')
                ->getStateUsing(fn (Resident $record) => $record->monthlyInvoices->first()?->service_subtotal ?? 0)
                ->summarize(Sum::make()->money('JPY')->label('自費計')),
        ];

        // 介護サービス種別ごとの列を動的追加
        foreach ($serviceLabels as $type => $label) {
            $columns[] = TextColumn::make("service_invoices_sum.{$type}")
                ->label($label)
                ->money('JPY')
                ->getStateUsing(function (Resident $record) use ($type) {
                    return $record->serviceInvoices
                        ->where('service_type', $type)
                        ->sum('total_with_tax');
                })
                ->summarize(Sum::make()->money('JPY')->label($label . '計'))
                ->toggleable();
        }

        $columns[] = TextColumn::make('monthlyInvoices.0.total_with_tax')
            ->label('住居費計(税込)')
            ->money('JPY')
            ->weight('bold')
            ->color('primary')
            ->getStateUsing(fn (Resident $record) => $record->monthlyInvoices->first()?->total_with_tax ?? 0)
            ->summarize(Sum::make()->money('JPY')->label('住居費総計'));

        $columns[] = TextColumn::make('care_total')
            ->label('介護計(税込)')
            ->money('JPY')
            ->weight('bold')
            ->color('warning')
            ->getStateUsing(fn (Resident $record) => $record->serviceInvoices->sum('total_with_tax'))
            ->summarize(Sum::make()->money('JPY')->label('介護総計'));

        $columns[] = TextColumn::make('grand_total')
            ->label('総合計(税込)')
            ->money('JPY')
            ->weight('bold')
            ->color('danger')
            ->getStateUsing(function (Resident $record) {
                $housing = $record->monthlyInvoices->first()?->total_with_tax ?? 0;
                $care = $record->serviceInvoices->sum('total_with_tax');
                return $housing + $care;
            })
            ->summarize(Sum::make()->money('JPY')->label('総合計'));

        $columns[] = TextColumn::make('monthlyInvoices.0.status')
            ->label('請求ステータス')
            ->badge()
            ->colors([
                'gray' => 'unbilled',
                'info' => 'billed',
                'success' => 'paid',
            ])
            ->getStateUsing(fn (Resident $record) => $record->monthlyInvoices->first()?->status?->value ?? 'unbilled');

        $columns[] = TextColumn::make('service_invoices_status')
            ->label('介護請求状況')
            ->badge()
            ->getStateUsing(function (Resident $record) {
                $statuses = $record->serviceInvoices->pluck('status')->unique()->map->getLabel()->implode(', ');
                return $statuses ?: 'なし';
            });

        return $columns;
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('請求ステータス')
                ->options(InvoiceStatus::class)
                ->query(fn (Builder $query, array $data) => $query->whereHas('monthlyInvoices', fn ($q) => $q->where('billing_year_month', $this->filters['billing_year_month'])->whereIn('status', $data['values']))),

            SelectFilter::make('care_status')
                ->label('介護請求ステータス')
                ->options(ServiceInvoiceStatus::class)
                ->query(fn (Builder $query, array $data) => $query->whereHas('serviceInvoices', fn ($q) => $q->where('billing_year_month', $this->filters['billing_year_month'])->whereIn('status', $data['values']))),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Tables\Actions\Action::make('exportCsv')
                ->label('CSVエクスポート')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    private function exportCsv(): void
    {
        $yearMonth = $this->filters['billing_year_month'] ?? now()->format('Y-m');
        $facilityId = $this->filters['facility_id'];

        $residents = $this->getTableQuery($yearMonth, $facilityId)->get();

        $serviceTypes = ServiceType::getOrderedCases();
        $serviceLabels = [];
        foreach ($serviceTypes as $st) {
            $serviceLabels[$st->value] = $st->getLabel();
        }

        $rows = [];
        $rows[] = ['部屋番号', '入居者名', '家賃', '管理費', '自費'] + array_values($serviceLabels) + ['住居費計(税込)', '介護計(税込)', '総合計(税込)', '請求ステータス', '介護請求状況'];

        foreach ($residents as $resident) {
            $invoice = $resident->monthlyInvoices->first();
            $housingTotal = $invoice?->total_with_tax ?? 0;
            $careTotal = $resident->serviceInvoices->sum('total_with_tax');

            $row = [
                $resident->room_number,
                $resident->name,
                $invoice?->rent_subtotal ?? 0,
                $invoice?->management_fee_subtotal ?? 0,
                $invoice?->service_subtotal ?? 0,
            ];

            foreach ($serviceLabels as $type => $label) {
                $row[] = $resident->serviceInvoices
                    ->where('service_type', $type)
                    ->sum('total_with_tax');
            }

            $row[] = $housingTotal;
            $row[] = $careTotal;
            $row[] = $housingTotal + $careTotal;
            $row[] = $invoice?->status?->getLabel() ?? '未請求';
            $row[] = $resident->serviceInvoices->pluck('status')->unique()->map->getLabel()->implode(', ') ?: 'なし';

            $rows[] = $row;
        }

        $bom = "\xEF\xBB\xBF";
        $csv = $bom;
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
        }

        $filename = "統合請求管理_{$yearMonth}" . ($facilityId ? "_" . \App\Models\Facility::find($facilityId)?->name : '_全施設') . ".csv";

        response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        )->send();

        Notification::make()
            ->title('CSVエクスポート完了')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('filament.pages.integrated-billing-dashboard');
    }
}