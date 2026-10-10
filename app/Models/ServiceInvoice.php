<?php

namespace App\Models;

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;

class ServiceInvoice extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => ServiceInvoiceStatus::Draft,
    ];

    protected $fillable = [
        'facility_id',
        'resident_id',
        'billing_year_month',
        'service_type',
        'service_type_label',
        'external_system_name',
        'external_invoice_number',
        'amount',
        'tax_amount',
        'tax_rate',
        'pdf_path',
        'pdf_original_name',
        'status',
        'sent_at',
        'sent_via',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'tax_amount' => 'integer',
        'tax_rate' => 'decimal:2',
        'sent_at' => 'datetime',
        'status' => ServiceInvoiceStatus::class,
        'service_type' => ServiceType::class,
    ];

    /**
     * 施設リレーション
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * 入居者リレーション
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * 税込み合計金額
     */
    public function getTotalWithTaxAttribute(): int
    {
        return $this->amount + $this->tax_amount;
    }

    /**
     * PDFファイルのフルパス取得
     */
    public function getPdfFullPathAttribute(): ?string
    {
        if (! $this->pdf_path) {
            return null;
        }

        return storage_path('app/'.$this->pdf_path);
    }

    /**
     * PDFが存在するか確認
     */
    public function hasPdf(): bool
    {
        return $this->pdf_path && File::exists($this->pdf_full_path);
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

    /**
     * 入居者でフィルタするスコープ
     */
    public function scopeForResident(Builder $query, int $residentId): Builder
    {
        return $query->where('resident_id', $residentId);
    }

    /**
     * サービス種別でフィルタするスコープ
     */
    public function scopeForServiceType(Builder $query, string $serviceType): Builder
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * ステータスでフィルタするスコープ
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * 確定済みのスコープ
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', ServiceInvoiceStatus::Confirmed);
    }

    /**
     * 送信済みのスコープ
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', ServiceInvoiceStatus::Sent);
    }

    /**
     * PDFありのスコープ
     */
    public function scopeWithPdf(Builder $query): Builder
    {
        return $query->whereNotNull('pdf_path');
    }

    /**
     * 送信済みマーク
     */
    public function markAsSent(string $via = 'manual'): void
    {
        $this->update([
            'status' => ServiceInvoiceStatus::Sent,
            'sent_at' => now(),
            'sent_via' => $via,
        ]);
    }

    /**
     * 確定マーク
     */
    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => ServiceInvoiceStatus::Confirmed,
        ]);
    }

    /**
     * 下書きに戻す
     */
    public function markAsDraft(): void
    {
        $this->update([
            'status' => ServiceInvoiceStatus::Draft,
            'sent_at' => null,
            'sent_via' => null,
        ]);
    }
}
