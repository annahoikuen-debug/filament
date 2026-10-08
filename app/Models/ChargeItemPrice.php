<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeItemPrice extends Model
{
    use HasFactory;

    protected $table = 'charge_item_prices';

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    /**
     * 品目
     */
    public function chargeItem(): BelongsTo
    {
        return $this->belongsTo(ChargeItem::class);
    }

    /**
     * 現在有効な価格のみ取得するスコープ
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('effective_from', '<=', Carbon::today())
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', Carbon::today());
            });
    }

    /**
     * 指定日の有効な価格を取得するスコープ
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            });
    }
}