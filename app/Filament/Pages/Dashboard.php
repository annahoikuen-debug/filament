<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\Facility;
use App\Services\InvoiceCalculationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\IconPosition;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Dashboard extends BaseDashboard implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'ダッシュボード';
    protected static ?string $title = 'ダッシュボード';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.dashboard';

    public ?string $currentYearMonth = null;
    public array $billingStats = [];
    public array $unpaidResidents = [];
    public array $quickStats = [];
    public ?Facility $facility = null;
    public array $yearMonthOptions = [];

    public function mount(): void
    {
        $this->currentYearMonth = now()->format('Y-m');
        $this->facility = $this->getUserFacility();
        $this->loadYearMonthOptions();
        $this->loadData();
    }

    protected function loadYearMonthOptions(): void
    {
        $query = MonthlyInvoice::query();
        if ($this->facility) {
            $query->where('facility_id', $this->facility->id);
        }
        
        $months = $query->distinct()->orderBy('billing_year_month', 'desc')->pluck('billing_year_month')->toArray();
        
        // 過去12ヶ月＋未来3ヶ月を追加
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            if (!in_array($month, $months)) {
                $months[] = $month;
            }
        }
        for ($i = 1; $i <= 3; $i++) {
            $month = now()->addMonths($i)->format('Y-m');
            if (!in_array($month, $months)) {
                $months[] = $month;
            }
        }
        
        rsort($months);
        $this->yearMonthOptions = array_combine($months, array_map(fn($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('Y年m月'), $months));
    }

    public function updatedCurrentYearMonth(): void
    {
        $this->loadData();
    }

    protected function getUserFacility(): ?Facility
    {
        $user = Auth::user();
        if ($user && $user->isFacilityAdmin() && $user->facility_id) {
            return Facility::find($user->facility_id);
        }
        return null;
    }

    protected function loadData(): void
    {
        $this->loadBillingStats();
        $this->loadUnpaidResidents();
        $this->loadQuickStats();
    }

    protected function loadBillingStats(): void
    {
        $query = MonthlyInvoice::query();
        if ($this->facility) {
            $query->where('facility_id', $this->facility->id);
        }

        $total = (clone $query)->where('billing_year_month', $this->currentYearMonth)->count();
        $billed = (clone $query)->where('billing_year_month', $this->currentYearMonth)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])->count();
        $unbilled = (clone $query)->where('billing_year_month', $this->currentYearMonth)
            ->where('status', InvoiceStatus::Unbilled)->count();
        $paid = (clone $query)->where('billing_year_month', $this->currentYearMonth)
            ->where('status', InvoiceStatus::Paid)->count();

        $this->billingStats = [
            'total' => $total,
            'billed' => $billed,
            'unbilled' => $unbilled,
            'paid' => $paid,
            'progress' => $total > 0 ? round(($billed / $total) * 100) : 0,
        ];
    }

    protected function loadUnpaidResidents(): void
    {
        $query = MonthlyInvoice::query()
            ->where('billing_year_month', $this->currentYearMonth)
            ->whereIn('status', [InvoiceStatus::Unbilled, InvoiceStatus::Billed])
            ->with(['resident' => fn($q) => $q->select('id', 'room_number', 'name', 'facility_id')]);

        if ($this->facility) {
            $query->where('facility_id', $this->facility->id);
        }

        $this->unpaidResidents = $query
            ->orderBy('total_amount', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'room_number' => $invoice->resident?->room_number ?? '不明',
                    'name' => $invoice->resident?->name ?? '不明',
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status->value,
                    'status_label' => $invoice->status->label(),
                ];
            })
            ->toArray();
    }

    protected function loadQuickStats(): void
    {
        $residentQuery = Resident::query()->where('status', \App\Enums\ResidentStatus::Active);
        $invoiceQuery = MonthlyInvoice::query();

        if ($this->facility) {
            $residentQuery->where('facility_id', $this->facility->id);
            $invoiceQuery->where('facility_id', $this->facility->id);
        }

        $totalResidents = $residentQuery->count();
        $totalBilledAmount = (clone $invoiceQuery)
            ->where('billing_year_month', $this->currentYearMonth)
            ->sum('total_amount');
        $totalPaidAmount = (clone $invoiceQuery)
            ->where('billing_year_month', $this->currentYearMonth)
            ->where('status', InvoiceStatus::Paid)
            ->sum('total_amount');
        $totalUnpaidAmount = (clone $invoiceQuery)
            ->where('billing_year_month', $this->currentYearMonth)
            ->whereIn('status', [InvoiceStatus::Unbilled, InvoiceStatus::Billed])
            ->sum('total_amount');

        // 平均回収日数（入金済みの請求書について）
        $avgCollectionDays = (clone $invoiceQuery)
            ->where('billing_year_month', $this->currentYearMonth)
            ->where('status', InvoiceStatus::Paid)
            ->whereNotNull('paid_at')
            ->get()
            ->map(function ($invoice) {
                $billingDate = Carbon::createFromFormat('Y-m', $this->currentYearMonth)->startOfMonth();
                return $billingDate->diffInDays(Carbon::parse($invoice->paid_at));
            })
            ->average() ?? 0;

        $this->quickStats = [
            'total_residents' => $totalResidents,
            'occupancy_rate' => $this->calculateOccupancyRate(),
            'total_billed' => $totalBilledAmount,
            'total_paid' => $totalPaidAmount,
            'total_unpaid' => $totalUnpaidAmount,
            'collection_rate' => $totalBilledAmount > 0 ? round(($totalPaidAmount / $totalBilledAmount) * 100, 1) : 0,
            'avg_collection_days' => round($avgCollectionDays, 1),
        ];
    }

    protected function calculateOccupancyRate(): float
    {
        if (!$this->facility) {
            return 0;
        }

        // 施設の定員情報があれば使用、なければ入居者数ベースで概算
        $capacity = $this->facility->billing['capacity'] ?? null;
        $currentResidents = Resident::where('facility_id', $this->facility->id)
            ->where('status', \App\Enums\ResidentStatus::Active)
            ->count();

        if ($capacity && $capacity > 0) {
            return round(($currentResidents / $capacity) * 100, 1);
        }

        return $currentResidents > 0 ? 100 : 0; // 定員不明時は概算
    }

    public function getActions(): array
    {
        return [
            Action::make('generateBilling')
                ->label('今月の請求を生成')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->size('lg')
                ->iconPosition(IconPosition::Before)
                ->requiresConfirmation()
                ->modalHeading('今月の請求データを生成します')
                ->modalDescription('翌月1日以降の請求データを一括生成します。日々の自費記録が揃っていることを確認してから実行してください。')
                ->modalSubmitActionLabel('生成する')
                ->action(function () {
                    $this->generateBilling();
                })
                ->visible(fn() => $this->canGenerateBilling()),

            Action::make('refreshStats')
                ->label('更新')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->loadData()),
        ];
    }

    protected function canGenerateBilling(): bool
    {
        // 翌月以降のみ生成可能（当月は生成済みの可能性があるため）
        $currentMonth = now()->format('Y-m');
        return $this->currentYearMonth >= $currentMonth;
    }

    protected function generateBilling(): void
    {
        try {
            $service = app(InvoiceCalculationService::class);
            $stats = $service->generateForMonth(
                $this->currentYearMonth,
                forceUpdate: false,
                facilityId: $this->facility?->id
            );

            Notification::make()
                ->title('請求生成が完了しました')
                ->body("作成: {$stats['created']}件、更新: {$stats['updated']}件、スキップ: {$stats['skipped']}件、競合: {$stats['conflicts']}件")
                ->success()
                ->send();

            $this->loadData();
        } catch (\Exception $e) {
            Notification::make()
                ->title('請求生成に失敗しました')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function markAsPaid(int $invoiceId): void
    {
        $invoice = MonthlyInvoice::find($invoiceId);
        if (!$invoice) {
            Notification::make()->title('請求データが見つかりません')->danger()->send();
            return;
        }

        $invoice->markAsPaid(\App\Enums\PaymentMethod::BankTransfer);

        Notification::make()
            ->title("入金消込が完了しました (領収書番号: {$invoice->receipt_number})")
            ->success()
            ->send();

        $this->loadData();
    }

    public function getViewData(): array
    {
        return [
            'currentYearMonth' => $this->currentYearMonth,
            'billingStats' => $this->billingStats,
            'unpaidResidents' => $this->unpaidResidents,
            'quickStats' => $this->quickStats,
            'facility' => $this->facility,
        ];
    }

    public function getYearMonthOptions(): array
    {
        return $this->yearMonthOptions;
    }
}