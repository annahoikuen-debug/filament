# 日々課金自動再計算・通知機能 実装計画書

**作成日**: 2026-10-13  
**対象**: 高齢者施設請求管理システム  
**ステータス**: 計画フェーズ  

---

## 1. 概要

本計画書は、介護職員による日々課金（DailyCharge）入力が未請求月の請求データに自動反映される仕組みと、請求確定済み月への追加入力時に適切なアラートを通知する機能の実装手順と、リグレッション防止のためのテスト戦略を定義する。

| ID | 課題 | 深刻度 | 目標完了 |
|----|------|--------|----------|
| DAILY-01 | 日々課金入力後の手動再計算漏れ | High | Phase 1 |
| DAILY-02 | 請求確定後のデータ乖離検知遅延 | Medium | Phase 1 |

---

## 2. 実装方針

- **イベント駆動アーキテクチャ**: Laravelのモデルイベントとオブザーバーを使用して、DailyChargeの作成・更新・削除を検知
- **自動再計算**: 未請求（unbilled）のMonthlyInvoiceに対しては、InvoiceCalculationServiceを使って即座に再計算を実行
- **通知機能**: 請求済み（billed）または入金済み（paid）のMonthlyInvoiceに対しては、Filament Notificationを使って管理者に警告を通知
- **トランザクション安全性**: データ整合性を保つため、DBトランザクション内で処理を実行
- **パフォーマンス考慮**: 必要最小限のクエリで対象住民と請求月を特定

---

## 3. 実装手順

### 3.1 DailyChargeObserver の作成

```php
// app/Observers/DailyChargeObserver.php
namespace App\Observers;

use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DailyChargeAddedToBilledInvoice; // 後で作成
use Illuminate\Support\Facades\Log;

class DailyChargeObserver
{
    protected $calculationService;

    public function __construct(InvoiceCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * DailyCharge 作成後の処理
     */
    public function created(DailyCharge $dailyCharge): void
    {
        $this->handleDailyChargeEvent($dailyCharge, 'created');
    }

    /**
     * DailyCharge 更新後の処理
     */
    public function updated(DailyCharge $dailyCharge): void
    {
        $this->handleDailyChargeEvent($dailyCharge, 'updated');
    }

    /**
     * DailyCharge 削除後の処理
     */
    public function deleted(DailyCharge $dailyCharge): void
    {
        $this->handleDailyChargeEvent($dailyCharge, 'deleted');
    }

    /**
     * 共通処理: 日々課金イベントハンドラー
     */
    private function handleDailyChargeEvent(DailyCharge $dailyCharge, string $eventType): void
    {
        try {
            // トランザクション内で処理を実行（データ整合性のため）
            \DB::transaction(function () use ($dailyCharge, $eventType) {
                // 1. 該当する入居者を取得
                $resident = Resident::find($dailyCharge->resident_id);
                if (! $resident) {
                    Log::warning("DailyCharge observer: Resident not found for ID {$dailyCharge->resident_id}");
                    return;
                }

                // 2. 請求年月を決定（DailyChargeのdateからY-m形式で取得）
                $billingYearMonth = $dailyCharge->date->format('Y-m');

                // 3. 対象のMonthlyInvoiceを取得（未作成の場合はnull）
                $invoice = MonthlyInvoice::where('resident_id', $resident->id)
                    ->where('billing_year_month', $billingYearMonth)
                    ->first();

                if (! $invoice) {
                    // 請求データがまだ存在しない場合は何もしない
                    // （月次バッチ処理で作成されるのを待つ）
                    return;
                }

                // 4. ステータス別処理
                if ($invoice->status === 'unbilled') {
                    // 未請求の場合: 自動再計算
                    $this->calculationService->calculate($invoice);
                    $invoice->save(); // バージョンインクリメント等のためsave()を呼ぶ

                    Log::info("DailyCharge observer: Auto-recalculated invoice for resident {$resident->id}, month {$billingYearMonth}");
                } elseif ($invoice->status === 'billed' || $invoice->status === 'paid') {
                    // 請求済みまたは入金済みの場合: 通知を送信
                    Notification::route('mail', 'admin@example.jp') // 実際の管理者メールは設定から取得
                        ->notify(new DailyChargeAddedToBilledInvoice(
                            $resident,
                            $invoice,
                            $dailyCharge,
                            $eventType
                        ));

                    Log::warning("DailyCharge observer: Notification sent for {$eventType} DailyCharge to billed/paid invoice for resident {$resident->id}, month {$billingYearMonth}");
                }
            });
        } catch (\Exception $e) {
            Log::error("DailyCharge observer failed: {$e->getMessage()}", [
                'resident_id' => $dailyCharge->resident_id ?? null,
                'date' => $dailyCharge->date ?? null,
                'event' => $eventType,
                'trace' => $e->getTraceAsString()
            ]);
            // 例外はそのまま投げずにログのみ記録（本処理の失敗で主要機能を止めない）
        }
    }
}
```

### 3.2 通知クラスの作成

```php
// app/Notifications/DailyChargeAddedToBilledInvoice.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Models\DailyCharge;

class DailyChargeAddedToBilledInvoice extends Notification implements ShouldQueue
{
    use Queueable;

    protected $resident;
    protected $invoice;
    protected $dailyCharge;
    protected $eventType;

    public function __construct(Resident $resident, MonthlyInvoice $invoice, DailyCharge $dailyCharge, string $eventType)
    {
        $this->resident = $resident;
        $this->invoice = $invoice;
        $this->dailyCharge = $dailyCharge;
        $this->eventType = $eventType;
    }

    public function via($notifiable)
    {
        return ['mail', 'database']; // メールとデータベース（Filament通知）両方に送信
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('請求確定済み月への日々課金追加警告')
            ->line('入居者『' . $this->resident->name . '』（' . $this->resident->room_number . '号室）の'
                . $this->invoice->billing_year_month . '月分請求データに、'
                . $this->eventType . 'された日々課金が追加されました。')
            ->line('日々課金詳細: ')
            ->line('- 品目: ' . $this->dailyCharge->chargeItem->name)
            ->line('- 日付: ' . $this->dailyCharge->date)
            ->line('- 金額: ' . number_format($this->dailyCharge->unit_price * $this->dailyCharge->quantity) . '円')
            ->action('請求データを確認', url('/admin/monthly-invoices/' . $this->invoice->id))
            ->line('この月分の請求はすでに確定済みのため、'
                . '手動で再計算を行うか、次月分への繰越しをご検討ください。');
    }

    public function toArray($notifiable)
    {
        return [
            'resident_id' => $this->resident->id,
            'resident_name' => $this->resident->name,
            'room_number' => $this->resident->room_number,
            'invoice_id' => $this->invoice->id,
            'billing_year_month' => $this->invoice->billing_year_month,
            'daily_charge_id' => $this->dailyCharge->id,
            'charge_item_name' => $this->dailyCharge->chargeItem->name,
            'date' => $this->dailyCharge->date->toDateString(),
            'amount' => $this->dailyCharge->unit_price * $this->dailyCharge->quantity,
            'event_type' => $this->eventType,
            'message' => '請求確定済み月への日々課金が追加されました。手動再計算または次月繰越しをご検討ください。',
        ];
    }
}
```

### 3.3 Observer の登録

```php
// app/Providers/AppServiceProvider.php の boot メソッドに追加
use App\Observers\DailyChargeObserver;
use App\Models\DailyCharge;

public function boot()
{
    DailyCharge::observe(DailyChargeObserver::class);
    
    // ... 既存のブート処理
}
```

### 3.4 設定ファイルの追加（管理者通知先等）

```php
// config/daily_charge.php
return [

    /*
    |--------------------------------------------------------------------------
    | DailyCharge Observer 設定
    |--------------------------------------------------------------------------
    |
    | 以下の設定でオブザーバーの動作を調整できます。
    |
    */

    'notification' => [
        /*
        |--------------------------------------------------------------------------
        | 通知先メールアドレス
        |--------------------------------------------------------------------------
        |
        | DailyChargeが請求確定済み月に追加された際の通知送信先。
        | 複数指定可能（配列）。
        |
        */
        'mail_to' => env('DAILY_CHARGE_NOTIFICATION_MAIL_TO', ['admin@example.jp']),

        /*
        |--------------------------------------------------------------------------
        | 通知チャネル
        |--------------------------------------------------------------------------
        |
        | 通知を送信するチャネル（mail, database等）。
        |
        */
        'channels' => ['mail', 'database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | ログレベル
    |--------------------------------------------------------------------------
    |
    | オブザーバー内部でのログ出力レベル。
    |
    */
    'log_level' => env('DAILY_CHARGE_OBSERVER_LOG_LEVEL', 'info'),
];
```

### 3.5 環境変数の追加（.env.example）

```env
# DailyCharge Observer Settings
DAILY_CHARGE_NOTIFICATION_MAIL_TO="admin@care-himawari.example.jp"
DAILY_CHARGE_OBSERVER_LOG_LEVEL="info"
```

---

## 4. テスト計画

リグレッション防止のため、以下のテストを実装する。

### 4.1 ユニットテスト

**tests/Unit/Observers/DailyChargeObserverTest.php**

| テストケース | 期待結果 |
|--------------|----------|
| 未請求月へのDailyCharge作成時 | 自動再計算が実行され、invoiceが保存される |
| 請求済み月へのDailyCharge作成時 | 通知が送信される（Notification::fake()でモック） |
| 入金済み月へのDailyCharge作成時 | 通知が送信される |
| 請求データが存在しない月へのDailyCharge作成時 | 何もしない（ログのみ） |
| 存在しない住民IDのDailyCharge | 警告ログが出力される |
| トランザクションロールバック時の挙動 | 例外が発生しても処理が途中で停止しない |

### 4.2 フィーチャーテスト

**tests/Feature/DailyChargeObserverTest.php**

| テストケース | 期待結果 |
|--------------|----------|
| 未請求月のDailyCharge追加後、インボイスが自動更新される | 金額が正しく再計算される |
| 請求済み月のDailyCharge追加後、管理者に通知が送信される | データベース通知とメールが送信される |
| 複数のDailyChargeが連続で追加された場合 | それぞれに対して適切な処理が行われる |
| DailyChargeの更新・削除時にも同様の処理が適用される | 作成時と同じロジックが適用される |
| 日付が月跨ぎの場合（例: 1月31日の記録が2月分の請求に影響） | 正しい請求年月で処理される |

### 4.3 既存テストへの影響確認

- `tests/Feature/InvoiceCalculationServiceMonthlyFilterTest.php` など、既存の請求計算関連テストが引き続きパスすることを確認
- `tests/Unit/DailyChargeModelTest.php` など、DailyCharge自体のテストに影響がないことを確認

---

## 5. 共通テスト基盤・運用

### 5.1 テスト実行コマンド
```bash
# DailyCharge Observer関連テスト実行
php artisan test --filter=DailyCharge

# 特定テストクラス
php artisan test tests/Unit/Observers/DailyChargeObserverTest.php
php artisan test tests/Feature/DailyChargeObserverTest.php
```

### 5.2 CI/CD 統合（GitHub Actions 例）
既存のCIに追加する場合の例：
```yaml
# .github/workflows/php-tests.yml の test ステップに追加
- run: php artisan test --filter=DailyCharge --parallel
```

### 5.3 手動検証チェックリスト（リリース前）

| 項目 | 確認方法 | 担当 |
|------|----------|------|
| 未請求月へのDailyCharge追加で自動再計算 | テストデータ作成後、インボイス金額変化を確認 | Backend |
| 請求済み月へのDailyCharge追加で通知送信 | 管理者メールとFilament通知を確認 | QA |
| ログ出力の確認 | storage/logs/laravel.log で適切なレベルのログ出力確認 | DevOps |
| 例外時の挙動確認 | DB接続エラー等を人為的に発生させ、ログ記録と処理継続を確認 | QA |
| パフォーマンス確認 | 大量のDailyCharge作成時に応答時間が極端に遅延しないことを確認 | Performance |

---

## 6. リスク・ロールバック計画

| リスク | 影響 | 対策 | ロールバック |
|--------|------|------|-------------|
| Observerによるパフォーマンス劣化 | DailyCharge入力時のレスポンス遅延 | 必要最小限のクエリに最適化・インデックス確認 | AppServiceProviderからObserver登録をコメントアウト |
| 通知によるメールスパーム | 同一月に多数の追加で通知が多発 | 必要に応じて通知の間引き機能や集約機能を後追加 | 通知機能を一時無効化（env変数で制御） |
| ロジックミスによる再計算漏れ | 未請求月でも手動再計算が必要になる状況 | 充実したユニットテストとシナリオテスト | 手動での `billing:generate-monthly --force` 実行で対応 |
| 通知先設定ミス | 通知が届かず気づきにくい | 送信テスト機能を管理画面に追加・環境変数でテストモード切替 | 管理者へ直接連絡で代替 |

---

## 7. スケジュール目安

| フェーズ | 作業 | 工数目安 |
|----------|------|----------|
| Phase 1-1 | DailyChargeObserver 実装 | 0.5日 |
| Phase 1-2 | 通知クラス・設定ファイル作成 | 0.5日 |
| Phase 1-3 | Observer登録・設定反映 | 0.25日 |
| Phase 1-4 | ユニットテスト作成・実行 | 0.5日 |
| Phase 1-5 | フィーチャーテスト作成・実行 | 0.5日 |
| Phase 1-6 | 既存テストへの影響確認・修正 | 0.25日 |
| Phase 1-7 | 手動検証・レビュー | 0.5日 |
| **合計** | | **約 3日** |

---

## 8. 完了基準

- [ ] 未請求月へのDailyCharge追加時に自動再計算が正常に動作すること
- [ ] 請求済み/入金済み月へのDailyCharge追加時に適切な通知が送信されること
- [ ] 全ユニットテスト・フィーチャーテストがパスすること
- [ ] 既存の請求計算関連テストに後退がないこと
- [ ] 手動検証チェックリストの全項目がクリアできること
- [ ] エラー時の適切なログ出力と処理継動が確認できること

---

## 9. 参考資料

- [Laravel Eloquent: Observers](https://laravel.com/docs/eloquent#observers)
- [Laravel Notifications](https://laravel.com/docs/notifications)
- [Filament Notifications](https://filamentphp.com/docs/2.x/admin/notifications)
- [Laravel Database Transactions](https://laravel.com/docs/database#database-transactions)
