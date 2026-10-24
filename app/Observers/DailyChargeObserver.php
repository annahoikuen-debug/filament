<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Notifications\DailyChargeAddedToBilledInvoice;
use App\Services\InvoiceCalculationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DailyChargeObserver
{
    protected $calculationService;

    public function __construct(InvoiceCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Handle the DailyCharge "created" event.
     */
    public function created(DailyCharge $dailyCharge): void
    {
        $this->handleDailyChargeEvent($dailyCharge, 'created');
    }

    /**
     * Handle the DailyCharge "updated" event.
     */
    public function updated(DailyCharge $dailyCharge): void
    {
        $this->handleDailyChargeEvent($dailyCharge, 'updated');
    }

    /**
     * Handle the DailyCharge "deleted" event.
     */
    public function deleted(DailyCharge $dailyCharge): void
    {
        $this->handleDailyChargeEvent($dailyCharge, 'deleted');
    }

    /**
     * Handle the DailyCharge event.
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
                if ($invoice->status === InvoiceStatus::Unbilled) {
                    // 未請求の場合: 自動再計算
                    $this->calculationService->calculate($invoice);
                    $invoice->save(); // バージョンインクリメント等のためsave()を呼ぶ

                    Log::info("DailyCharge observer: Auto-recalculated invoice for resident {$resident->id}, month {$billingYearMonth}");
                } elseif ($invoice->status === InvoiceStatus::Billed || $invoice->status === InvoiceStatus::Paid) {
                    // 請求済みまたは入金済みの場合: 通知を送信
                    Notification::route('mail', config('daily_charge.notification.mail_to'))
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
                'trace' => $e->getTraceAsString(),
            ]);
            // 例外はそのまま投げずにログのみ記録（本処理の失敗で主要機能を止めない）
        }
    }
}
