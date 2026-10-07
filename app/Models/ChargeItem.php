<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargeItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'default_price' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * この品目が使われた日々の自費記録一覧
     */
    public function dailyCharges(): HasMany
    {
        return $this->hasMany(DailyCharge::class);
    }

    /**
     * 実効価格を取得する（デフォルト単価が0より大きい場合のみ有効）
     */
    public function getEffectivePrice(): ?int
    {
        return $this->default_price > 0 ? $this->default_price : null;
    }

    /**
     * 現在有効な品目のみ取得するスコープ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
