<?php

namespace App\Filament\Pages;

use App\Models\ChargeItem;
use App\Models\Facility;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class OnboardingWizard extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'セットアップウィザード';
    protected static ?string $title = 'セットアップウィザード';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.onboarding-wizard';

    public int $currentStep = 1;
    public array $formData = [];
    public array $presetItems = [];
    public ?array $csvPreview = null;
    public int $totalSteps = 5;

    public function mount(): void
    {
        $this->formData = [
            'facility_name' => '',
            'operator' => '',
            'invoice_registration_number' => '',
            'phone' => '',
            'email' => '',
            'postal_code' => '',
            'address' => '',
            'bank_name' => '',
            'bank_branch' => '',
            'bank_account_type' => '普通',
            'bank_account_number' => '',
            'bank_account_holder' => '',
            'capacity' => 0,
            'preset_items' => [],
            'accounting_software' => 'freee',
            'accounting_profile_name' => '',
        ];

        $this->presetItems = $this->getPresetItems();
    }

    protected function getPresetItems(): array
    {
        return [
            ['name' => 'おむつ（紙おむつ）', 'default_price' => 200, 'tax_type' => 'reduced', 'category' => '日用品'],
            ['name' => '理美容（散髪）', 'default_price' => 3000, 'tax_type' => 'standard', 'category' => 'サービス'],
            ['name' => '受診付き添い', 'default_price' => 1500, 'tax_type' => 'standard', 'category' => 'サービス'],
            ['name' => 'おむつ（布おむつ）', 'default_price' => 100, 'tax_type' => 'reduced', 'category' => '日用品'],
            ['name' => 'リハビリ補助', 'default_price' => 2000, 'tax_type' => 'standard', 'category' => 'サービス'],
            ['name' => '外出付き添い', 'default_price' => 2500, 'tax_type' => 'standard', 'category' => 'サービス'],
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('formData')
            ->schema([
                // Step 1: Facility Settings
                Section::make('step1')
                    ->heading('施設情報の設定')
                    ->description('施設の基本情報を入力してください')
                    ->schema([
                        TextInput::make('facility_name')
                            ->label('施設名')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('例：社会福祉法人○○会 特別養護老人ホーム'),
                        TextInput::make('operator')
                            ->label('運営法人名')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('例：社会福祉法人○○会'),
                        TextInput::make('invoice_registration_number')
                            ->label('適格請求書登録番号')
                            ->placeholder('T1234567890123')
                            ->maxLength(14)
                            ->helperText('T + 13桁数字（インボイス制度対応）'),
                        TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('postal_code')
                            ->label('郵便番号')
                            ->maxLength(10),
                        TextInput::make('address')
                            ->label('住所')
                            ->maxLength(255),
                        TextInput::make('capacity')
                            ->label('入居定員')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                // Step 2: Bank Account
                Section::make('step2')
                    ->heading('口座情報の設定')
                    ->description('請求書に表示する口座情報を入力してください')
                    ->schema([
                        TextInput::make('bank_name')
                            ->label('金融機関名')
                            ->maxLength(100)
                            ->placeholder('例：○○銀行'),
                        TextInput::make('bank_branch')
                            ->label('支店名')
                            ->maxLength(100)
                            ->placeholder('例：○○支店'),
                        Select::make('bank_account_type')
                            ->label('口座種別')
                            ->options([
                                '普通' => '普通預金',
                                '当座' => '当座預金',
                                '貯蓄' => '貯蓄預金',
                            ])
                            ->default('普通'),
                        TextInput::make('bank_account_number')
                            ->label('口座番号')
                            ->maxLength(20),
                        TextInput::make('bank_account_holder')
                            ->label('口座名義')
                            ->maxLength(255)
                            ->placeholder('例：ｼｬｶｲﾌｸｼﾎｳｼﾞﾝ○○ｶｲ'),
                    ])->columns(2),

                // Step 3: Charge Items
                Section::make('step3')
                    ->heading('品目マスタの設定')
                    ->description('日々の自費記録で使用する品目を選択してください')
                    ->schema([
                        \Filament\Forms\Components\CheckboxList::make('preset_items')
                            ->label('プリセット品目')
                            ->options(fn() => collect($this->presetItems)->pluck('name', 'name')->toArray())
                            ->descriptions(fn() => collect($this->presetItems)->pluck('default_price', 'name')->map(fn($p) => "¥{$p}")->toArray())
                            ->columns(2),
                    ]),

                // Step 4: Accounting Software
                Section::make('step4')
                    ->heading('会計ソフト連携')
                    ->description('会計ソフトへのCSV出力設定を行います')
                    ->schema([
                        Select::make('accounting_software')
                            ->label('会計ソフト')
                            ->options([
                                'freee' => 'freee',
                                'mf' => 'MFクラウド会計',
                                'yayoi' => '弥生会計',
                                'kanjobugyo' => '勘定奉行',
                            ])
                            ->default('freee')
                            ->required(),
                        TextInput::make('accounting_profile_name')
                            ->label('プロファイル名')
                            ->maxLength(255)
                            ->placeholder('例：本番用'),
                    ])->columns(2),
            ]);
    }

    public function getActions(): array
    {
        return [
            Action::make('next')
                ->label($this->currentStep === $this->totalSteps ? '完了' : '次へ')
                ->icon('heroicon-o-arrow-right')
                ->color('primary')
                ->action(fn() => $this->nextStep()),

            Action::make('back')
                ->label('戻る')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->visible(fn() => $this->currentStep > 1)
                ->action(fn() => $this->previousStep()),

            Action::make('skip')
                ->label('スキップ')
                ->color('gray')
                ->visible(fn() => $this->currentStep < $this->totalSteps)
                ->action(fn() => $this->skipStep()),
        ];
    }

    protected function nextStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'formData.facility_name' => 'required|string|max:255',
                'formData.operator' => 'required|string|max:255',
            ]);
            $this->saveFacility();
        }

        if ($this->currentStep === 2) {
            $this->saveBankInfo();
        }

        if ($this->currentStep === 3) {
            $this->saveChargeItems();
        }

        if ($this->currentStep === 4) {
            $this->saveAccountingProfile();
        }

        if ($this->currentStep === $this->totalSteps) {
            $this->completeOnboarding();
            return;
        }

        $this->currentStep++;
    }

    protected function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    protected function skipStep(): void
    {
        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    protected function saveFacility(): void
    {
        $data = $this->formData;

        $facility = Facility::updateOrCreate(
            ['id' => Auth::user()?->facility_id],
            [
                'name' => $data['facility_name'],
                'operator' => $data['operator'],
                'invoice_registration_number' => $data['invoice_registration_number'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'postal_code' => $data['postal_code'],
                'address' => $data['address'],
                'is_active' => true,
                'billing' => [
                    'capacity' => (int) $data['capacity'],
                ],
            ]
        );

        // ユーザーに施設IDを紐付け
        if (Auth::user() && !Auth::user()->facility_id) {
            Auth::user()->update(['facility_id' => $facility->id]);
        }

        Notification::make()
            ->title('施設情報を保存しました')
            ->success()
            ->send();
    }

    protected function saveBankInfo(): void
    {
        $data = $this->formData;
        $facilityId = Auth::user()?->facility_id;

        if (!$facilityId) {
            Notification::make()->title('施設情報を先に保存してください')->danger()->send();
            return;
        }

        $facility = Facility::find($facilityId);
        if ($facility) {
            $facility->update([
                'bank' => [
                    'name' => $data['bank_name'],
                    'branch_name' => $data['bank_branch'],
                    'account_type' => $data['bank_account_type'],
                    'account_number' => $data['bank_account_number'],
                    'account_holder' => $data['bank_account_holder'],
                ],
            ]);
        }

        Notification::make()
            ->title('口座情報を保存しました')
            ->success()
            ->send();
    }

    protected function saveChargeItems(): void
    {
        $selectedItems = $this->formData['preset_items'] ?? [];
        $facilityId = Auth::user()?->facility_id;

        foreach ($this->presetItems as $item) {
            if (in_array($item['name'], $selectedItems)) {
                ChargeItem::updateOrCreate(
                    [
                        'name' => $item['name'],
                        'facility_id' => $facilityId,
                    ],
                    [
                        'default_price' => $item['default_price'],
                        'tax_type' => $item['tax_type'],
                        'category' => $item['category'],
                        'is_active' => true,
                    ]
                );
            }
        }

        Notification::make()
            ->title('品目マスタを設定しました')
            ->success()
            ->send();
    }

    protected function saveAccountingProfile(): void
    {
        $data = $this->formData;
        $facilityId = Auth::user()?->facility_id;

        if (!$facilityId || empty($data['accounting_profile_name'])) {
            return;
        }

        \App\Models\AccountingExportProfile::updateOrCreate(
            [
                'facility_id' => $facilityId,
                'software_type' => $data['accounting_software'],
                'name' => $data['accounting_profile_name'],
            ],
            [
                'is_active' => true,
                'settings' => [],
            ]
        );

        Notification::make()
            ->title('会計ソフト連携を設定しました')
            ->success()
            ->send();
    }

    protected function completeOnboarding(): void
    {
        Notification::make()
            ->title('セットアップが完了しました')
            ->body('これで請求管理を開始できます。ダッシュボードから今月の請求を生成してください。')
            ->success()
            ->send();

        redirect()->route('filament.admin.pages.dashboard')->send();
    }

    public function getViewData(): array
    {
        return [
            'currentStep' => $this->currentStep,
            'totalSteps' => $this->totalSteps,
            'presetItems' => $this->presetItems,
            'formData' => $this->formData,
        ];
    }
}