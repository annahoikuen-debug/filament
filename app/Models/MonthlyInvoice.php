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
        'tax_rate' => 'decimal:2',
        'tax_breakdown' => 'array',
        'version' => 'integer',
    ];

    protected $appends = [
        'non_taxable_amount',
        'taxable_amount',
        'tax_amount',
        'total_with_tax',
    ];

    /**
     * モデル起動時のイベント設定
     * 計算ロジックは InvoiceCalculationService に委譲。
     * ここでは合計金額の再計算と楽観ロックのバージョン管理のみ行う。
     */
    protected static function booted(): void
    {
        static::saving(function (MonthlyInvoice $invoice) {
            // 合計金額の計算（税抜き小計）
            $invoice->total_amount = (int) $invoice->rent_subtotal
                + (int) $invoice->management_fee_subtotal
                + (int) $invoice->service_subtotal;

            // 楽観ロック: 更新時のみバージョンをインクリメント
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
     * 施設
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
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
     * tax_breakdown がある場合はそこから算出、なければ従来通り
     */
    public function getTaxableAmountAttribute(): int
    {
        if ($this->tax_breakdown && isset($this->tax_breakdown['standard']['taxable_amount'], $this->tax_breakdown['reduced']['taxable_amount'])) {
            return (int) $this->tax_breakdown['standard']['taxable_amount'] + (int) $this->tax_breakdown['reduced']['taxable_amount'];
        }
        return $this->taxable_amount ?? ($this->management_fee_subtotal + $this->service_subtotal);
    }

    /**
     * 消費税額を取得する
     * tax_breakdown がある場合はそこから算出、なければ従来通り
     */
    public function getTaxAmountAttribute(): int
    {
        if ($this->tax_breakdown && isset($this->tax_breakdown['standard']['tax_amount'], $this->tax_breakdown['reduced']['tax_amount'])) {
            return (int) $this->tax_breakdown['standard']['tax_amount'] + (int) $this->tax_breakdown['reduced']['tax_amount'];
        }
        return $this->tax_amount ?? (int) round($this->getTaxableAmountAttribute() * (($this->tax_rate ?? 10) / 100));
    }

    /**
     * 税込み合計額を取得する
     */
    public function getTotalWithTaxAttribute(): int
    {
        return $this->rent_subtotal + $this->getTaxableAmountAttribute() + $this->getTaxAmountAttribute();
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

    /**
     * 施設でフィルタするスコープ
     */
    public function scopeForFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('facility_id', $facilityId);
    }
}