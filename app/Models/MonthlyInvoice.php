<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyInvoice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'version' => 0,
    ];

    protected $casts = [
        'rent_subtotal' => 'integer',
        'management_fee_subtotal' => 'integer',
        'service_subtotal' => 'integer',
        'total_amount' => 'integer',
        'status' => InvoiceStatus::class,
        'paid_at' => 'date',
        'payment_method' => PaymentMethod::class,
        'receipt_issued_at' => 'datetime',
        'taxable_amount' => 'integer',
        'tax_amount' => 'integer',
        'tax_rate' => 'integer',
        'version' => 'integer',
    ];

    protected $appends = [
        'non_taxable_amount',
        'taxable_amount',
        'tax_amount',
        'total_with_tax',
    ];

    /**
     * モデル起動時のイベント設定（Observerとの二重安全策）
     */
    protected static function booted(): void
    {
        static::saving(function (MonthlyInvoice $invoice) {
            // 合計金額の計算
            $invoice->total_amount = (int) $invoice->rent_subtotal
                + (int) $invoice->management_fee_subtotal
                + (int) $invoice->service_subtotal;

            // 消費税関連の計算（設定から税率を取得、デフォルト10%）
            $taxRate = config('tax.standard_rate', 10);
            $invoice->tax_rate = $taxRate;
            $invoice->taxable_amount = (int) $invoice->management_fee_subtotal
                + (int) $invoice->service_subtotal;
            $invoice->tax_amount = (int) round($invoice->taxable_amount * ($taxRate / 100));

            // 楽観ロック: 更新時のみバージョンをインクリメント
            // $invoice->exists は新規作成時は false、既存レコード更新時は true
            if ($invoice->exists && $invoice->isDirty()) {
                $invoice->version = ($invoice->version ?? 0) + 1;
            }
        });
    }

    /**
     * 入居者
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * 非課税額（家賃）を取得する
     */
    public function getNonTaxableAmountAttribute(): int
    {
        return $this->rent_subtotal;
    }

    /**
     * 課税対象額（管理費＋自費）を取得する
     */
    public function getTaxableAmountAttribute(): int
    {
        return $this->management_fee_subtotal + $this->service_subtotal;
    }

    /**
     * 消費税額を取得する
     */
    public function getTaxAmountAttribute(): int
    {
        return (int) round($this->taxable_amount * ($this->tax_rate / 100));
    }

    /**
     * 税込み合計額を取得する
     */
    public function getTotalWithTaxAttribute(): int
    {
        return $this->rent_subtotal + $this->taxable_amount + $this->tax_amount;
    }

    /**
     * 入金処理を行い、領収書番号を発行する
     */
    public function markAsPaid(PaymentMethod $method, ?string $paidAt = null): void
    {
        $paidDate = $paidAt ? Carbon::parse($paidAt)->toDateString() : now()->toDateString();

        $this->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $paidDate,
            'payment_method' => $method,
            'receipt_number' => $this->receipt_number ?? sprintf('REC-%s-%04d', str_replace('-', '', $this->billing_year_month), $this->resident_id),
            'receipt_issued_at' => $this->receipt_issued_at ?? now(),
        ]);
    }

    /**
     * 請求年月スコープ
     */
    public function scopeForYearMonth(Builder $query, string $yearMonth): Builder
    {
        return $query->where('billing_year_month', $yearMonth);
    }
}
