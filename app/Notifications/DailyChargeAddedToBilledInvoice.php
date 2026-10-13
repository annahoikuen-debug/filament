<?php

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

    /**
     * Create a new notification instance.
     */
    public function __construct(Resident $resident, MonthlyInvoice $invoice, DailyCharge $dailyCharge, string $eventType)
    {
        $this->resident = $resident;
        $this->invoice = $invoice;
        $this->dailyCharge = $dailyCharge;
        $this->eventType = $eventType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('請求確定済み月への日々課金追加警告')
            ->line('入居者「' . $this->resident->name . '」（' . $this->resident->room_number . '号室）の'
                . $this->invoice->billing_year_month . '月分請求データに、'
                . $this->eventType . 'された日々課金が追加されました。')
            ->line('日々課金詳細: ')
            ->line('- 品目: ' . $this->dailyCharge->chargeItem->name)
            ->line('- 日付: ' . $this->dailyCharge->date->format('Y-m-d'))
            ->line('- 金額: ' . number_format($this->dailyCharge->unit_price * $this->dailyCharge->quantity) . '円')
            ->action('請求データを確認', url('/admin/monthly-invoices/' . $this->invoice->id))
            ->line('この月分の請求はすでに確定済みのため、')
            ->line('手動で再計算を行うか、次月分への繰越しをご検討ください。');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
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