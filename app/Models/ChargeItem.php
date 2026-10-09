<?php

namespace App\Models;

use App\Enums\TaxType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChargeItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'default_price',
        'tax_type',
        'category',
        'is_active',
        'facility_id',
    ];

    protected $casts = [
        'default_price' => 'integer',
        'is_active' => 'boolean',
        'tax_type' => TaxType::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'facility_id',
                'name',
                'display_name',
                'description',
                'default_price',
                'tax_type',
                'category',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "ChargeItem {$eventName}")
            ->useLogName('charge_item');
    }

    /**
     * 施設リレーション
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * この品目が使われた日々の自費記録一覧
     */
    public function dailyCharges(): HasMany
    {
        return $this->hasMany(DailyCharge::class);
    }

    /**
     * 単価履歴一覧
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ChargeItemPrice::class)->orderBy('effective_from', 'desc');
    }

    /**
     * 現在有効な価格を取得
     */
    public function currentPrice(): ?int
    {
        $current = $this->priceHistory()->current()->first();
        return $current?->price ?? $this->default_price;
    }

    /**
     * 指定日の有効な価格を取得
     */
    public function getPriceForDate(Carbon $date): ?int
    {
        $price = $this->priceHistory()->forDate($date)->orderBy('effective_from', 'desc')->first();
        return $price?->price ?? $this->default_price;
    }

    /**
     * 実効価格を取得する（デフォルト単価が0より大きい場合のみ有効）
     */
    public function getEffectivePrice(): ?int
    {
        // デフォルト単価が0以下の場合は無効（無料品目として扱う）
        if ($this->default_price <= 0) {
            return null;
        }
        return $this->currentPrice() > 0 ? $this->currentPrice() : null;
    }

    /**
     * 現在有効な品目のみ取得するスコープ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}