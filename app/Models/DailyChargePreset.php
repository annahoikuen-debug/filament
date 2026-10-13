<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * 自費サービスプリセットマスター（Step 9）
 *
 * 例: 「デイサービス利用パック（昼食+おやつ+入浴介助）」「標準日用品パック」
 */
class DailyChargePreset extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['facility_id', 'name', 'description', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "DailyChargePreset {$eventName}: {$this->name}")
            ->useLogName('daily_charge_preset');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyChargePresetItem::class, 'daily_charge_preset_id')->orderBy('sort_order');
    }

    /**
     * スコープ: 対象施設（または共通プリセット）かつ有効
     */
    public function scopeAvailableFor($query, ?int $facilityId)
    {
        return $query->where(function ($q) use ($facilityId) {
            $q->whereNull('facility_id');
            if ($facilityId !== null) {
                $q->orWhere('facility_id', $facilityId);
            }
        })->where('is_active', true)->orderBy('sort_order');
    }
}
