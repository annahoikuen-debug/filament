<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ChatbotAssistant;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\OnboardingWizard;
use App\Filament\Widgets\TrialOverview;
use App\Filament\Widgets\UnpaidInvoicesAlert;
use App\Filament\Widgets\UnprocessedDailyChargesWidget;
use App\Filament\Widgets\PendingInvoicesWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\QuickActionsWidget;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;

// Resource imports for navigation
use App\Filament\Resources\ResidentResource;
use App\Filament\Resources\FacilityResource;
use App\Filament\Resources\DailyChargeResource;
use App\Filament\Resources\DailyChargePresetResource;
use App\Filament\Resources\MonthlyInvoiceResource;
use App\Filament\Resources\ServiceInvoiceResource;
use App\Filament\Resources\ItemMasterResource;
use App\Filament\Resources\TaxSettingResource;
use App\Filament\Resources\ChartOfAccountResource;
use App\Filament\Resources\ChargeItemResource;
use App\Filament\Resources\AccountingExportProfileResource;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\ApiTokenResource;
use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\ChatbotFaqResource;
use App\Filament\Resources\ChatbotFaqSuggestionResource;
use App\Filament\Resources\ChatbotSynonymResource;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('隲区ｱらｮ｡逅・す繧ｹ繝・Β')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                OnboardingWizard::class,
                ChatbotAssistant::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                TrialOverview::class,
                UnpaidInvoicesAlert::class,
                UnprocessedDailyChargesWidget::class,
                PendingInvoicesWidget::class,
                RecentActivityWidget::class,
                QuickActionsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function navigation(Panel $panel): array
    {
        return [
            NavigationGroup::make()
                ->label('業務準備')
                ->items([
                    NavigationItem::make()
                        ->label('居住者')
                        ->url(ResidentResource::getUrl('index'))
                        ->icon('heroicon-o-user-group'),
                    NavigationItem::make()
                        ->label('施設')
                        ->url(FacilityResource::getUrl('index'))
                        ->icon('heroicon-o-building'),
                ]),
            NavigationGroup::make()
                ->label('日次業務')
                ->items([
                    NavigationItem::make()
                        ->label('日次課金')
                        ->url(DailyChargeResource::getUrl('index'))
                        ->icon('heroicon-o-calendar'),
                    NavigationItem::make()
                        ->label('日次課金プリセット')
                        ->url(DailyChargePresetResource::getUrl('index'))
                        ->icon('heroicon-o-clock'),
                ]),
            NavigationGroup::make()
                ->label('請求業務')
                ->items([
                    NavigationItem::make()
                        ->label('月次請求書')
                        ->url(MonthlyInvoiceResource::getUrl('index'))
                        ->icon('heroicon-o-document-text'),
                    NavigationItem::make()
                        ->label('サービス請求書')
                        ->url(ServiceInvoiceResource::getUrl('index'))
                        ->icon('heroicon-o-document-text'),
                ]),
            NavigationGroup::make()
                ->label('マスタ管理')
                ->items([
                    NavigationItem::make()
                        ->label('品目マスタ')
                        ->url(ItemMasterResource::getUrl('index'))
                        ->icon('heroicon-o-document'),
                    NavigationItem::make()
                        ->label('税金設定')
                        ->url(TaxSettingResource::getUrl('index'))
                        ->icon('heroicon-o-percent'),
                    NavigationItem::make()
                        ->label('勘定科目')
                        ->url(ChartOfAccountResource::getUrl('index'))
                        ->icon('heroicon-o-collection'),
                    NavigationItem::make()
                        ->label('課金項目')
                        ->url(ChargeItemResource::getUrl('index'))
                        ->icon('heroicon-o-plus-circle'),
                ]),
            NavigationGroup::make()
                ->label('出力・監査')
                ->items([
                    NavigationItem::make()
                        ->label('会計エクスポート')
                        ->url(AccountingExportProfileResource::getUrl('index'))
                        ->icon('heroicon-o-arrow-train'),
                    NavigationItem::make()
                        ->label('活動ログ')
                        ->url(ActivityLogResource::getUrl('index'))
                        ->icon('heroicon-o-chart-bar'),
                    NavigationItem::make()
                        ->label('APIトークン')
                        ->url(ApiTokenResource::getUrl('index'))
                        ->icon('heroicon-o-key'),
                    NavigationItem::make()
                        ->label('フォーム送信')
                        ->url(FormSubmissionResource::getUrl('index'))
                        ->icon('heroicon-o-document-duplicate'),
                    NavigationItem::make()
                        ->label('チャットボットFAQ')
                        ->url(ChatbotFaqResource::getUrl('index'))
                        ->icon('heroicon-o-question-mark-circle'),
                    NavigationItem::make()
                        ->label('チャットボットFAQ提案')
                        ->url(ChatbotFaqSuggestionResource::getUrl('index'))
                        ->icon('heroicon-o-light-bulb'),
                    NavigationItem::make()
                        ->label('チャットボットシノニム')
                        ->url(ChatbotSynonymResource::getUrl('index'))
                        ->icon('heroicon-o-arrows-merge'),
                ]),
        ];
    }
}