<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCharge extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'unit_price' => 'integer',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyCharge $charge) {
            // charge_item_id が指定されていて unit_price が未設定（または0）の場合、価格履歴から自動取得
            if ($charge->charge_item_id && (empty($charge->unit_price) || $charge->unit_price === 0)) {
                $chargeItem = ChargeItem::find($charge->charge_item_id);
                if ($chargeItem && $charge->date) {
                    $charge->unit_price = $chargeItem->getPriceForDate($charge->date) ?? 0;
                }
            }
        });

        static::saving(function (DailyCharge $charge) {
            // 入居者が指定されている場合、利用日が入居期間内であるか検証
            if ($charge->resident_id && $charge->date) {
                $resident = $charge->resident ?? Resident::find($charge->resident_id);
                if ($resident && ! $resident->isLivingAt($charge->date)) {
                    $moveIn = $resident->move_in_date?->format('Y/m/d') ?? '未設定';
                    $moveOut = $resident->move_out_date?->format('Y/m/d') ?? '退去日未定';
                    throw new DomainException(
                        "利用日 ({$charge->date->format('Y/m/d')}) が入居者の在籍期間外です。(入居日: {$moveIn} 〜 退去日: {$moveOut})"
                    );
                }
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
     * 自費サービス品目
     */
    public function chargeItem(): BelongsTo
    {
        return $this->belongsTo(ChargeItem::class);
    }

    /**
     * 小計（単価 × 数量）アクセサ
     */
    public function getSubtotalAttribute(): int
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * 指定年月の記録を絞り込むスコープ (例: 2026-10)
     */
    public function scopeForYearMonth(Builder $query, string $yearMonth): Builder
    {
        return $query->whereBetween('date', [
            "{$yearMonth}-01",
            date('Y-m-t', strtotime("{$yearMonth}-01")),
        ]);
    }

    /**
     * 施設でフィルタするスコープ
     */
    public function scopeForFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('facility_id', $facilityId);
    }
}