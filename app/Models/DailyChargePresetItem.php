<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * プリセット構成品目（Step 9）
 */
class DailyChargePresetItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'default_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function preset(): BelongsTo
    {
        return $this->belongsTo(DailyChargePreset::class, 'daily_charge_preset_id');
    }

    public function chargeItem(): BelongsTo
    {
        return $this->belongsTo(ChargeItem::class);
    }
}
