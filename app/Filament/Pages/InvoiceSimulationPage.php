<?php

namespace App\Filament\Pages;

use App\Models\Resident;
use App\Services\Invoice\ProrationCalculator;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class InvoiceSimulationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = '請求シミュレーション';

    protected static ?string $title = '請求計算シミュレーター';

    protected static ?string $navigationGroup = '請求管理';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.invoice-simulation-page';

    public ?array $data = [];

    public int $resultLivingDays = 0;
    public int $resultDaysInMonth = 0;
    public int $resultRent = 0;
    public int $resultManagementFee = 0;
    public int $resultServiceTotal = 0;
    public int $resultTotalAmount = 0;
    public bool $hasCalculated = false;

    public function mount(): void
    {
        $this->form->fill([
            'year_month' => now()->format('Y-m'),
            'move_in_date' => now()->startOfMonth()->toDateString(),
            'move_out_date' => null,
            'base_rent' => 60000,
            'base_management_fee' => 30000,
            'service_total' => 0,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('シミュレーション条件')
                    ->schema([
                        TextInput::make('year_month')
                            ->label('対象年月')
                            ->placeholder('YYYY-MM')
                            ->default(now()->format('Y-m'))
                            ->required(),
                        DatePicker::make('move_in_date')
                            ->label('入居日')
                            ->native(false)
                            ->required(),
                        DatePicker::make('move_out_date')
                            ->label('退去日（任意）')
                            ->native(false),
                        TextInput::make('base_rent')
                            ->label('月額家賃')
                            ->numeric()
                            ->prefix('¥')
                            ->default(60000)
                            ->required(),
                        TextInput::make('base_management_fee')
                            ->label('月額管理費')
                            ->numeric()
                            ->prefix('¥')
                            ->default(30000)
                            ->required(),
                        TextInput::make('service_total')
                            ->label('自費サービス想定額')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    public function simulate(): void
    {
        $data = $this->form->getState();
        [$year, $month] = explode('-', $data['year_month']);
        $year = (int) $year;
        $month = (int) $month;

        $calc = new ProrationCalculator();
        $this->resultDaysInMonth = Carbon::create($year, $month)->daysInMonth;

        $mockResident = new Resident([
            'move_in_date' => $data['move_in_date'],
            'move_out_date' => $data['move_out_date'],
            'base_rent' => (int) $data['base_rent'],
            'base_management_fee' => (int) $data['base_management_fee'],
        ]);

        $this->resultLivingDays = $calc->calculateLivingDays($mockResident, $year, $month);
        $this->resultRent = $calc->calculateProratedAmount((int) $data['base_rent'], $year, $month, $this->resultLivingDays);
        $this->resultManagementFee = $calc->calculateProratedAmount((int) $data['base_management_fee'], $year, $month, $this->resultLivingDays);
        $this->resultServiceTotal = (int) ($data['service_total'] ?? 0);
        $this->resultTotalAmount = $this->resultRent + $this->resultManagementFee + $this->resultServiceTotal;

        $this->hasCalculated = true;
    }
}
