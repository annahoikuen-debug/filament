<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaxSetting extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'tax_settings';

    protected $fillable = [
        'standard_rate',
        'reduced_rate',
        'effective_from',
        'effective_until',
        'scope',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'standard_rate' => 'integer',
        'reduced_rate' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'standard_rate',
                'reduced_rate',
                'effective_from',
                'effective_until',
                'scope',
                'is_active',
                'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "TaxSetting {$eventName}")
            ->useLogName('tax_setting');
    }

    /**
     * 現在有効な税率設定を取得（日付範囲で判定）
     */
    public static function current(): ?self
    {
        return self::where('is_active', true)
            ->where('effective_from', '<=', Carbon::today())
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', Carbon::today());
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * config/tax.php 互換の配列を返す
     */
    public function toConfigArray(): array
    {
        return [
            'standard_rate' => $this->standard_rate,
            'reduced_rate' => $this->reduced_rate,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
            'scope' => $this->scope,
        ];
    }

    /**
     * 指定日の標準税率を取得（過去・未来の税率確認用）
     */
    public static function getRateForDate(Carbon $date): int
    {
        $setting = self::where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->first();

        return $setting?->standard_rate ?? 10; // デフォルト10%
    }

    /**
     * 現在の標準税率を取得（デフォルト10%）
     */
    public static function currentStandardRate(): int
    {
        return self::current()?->standard_rate ?? 10;
    }

    /**
     * 現在の軽減税率を取得（デフォルト8%）
     */
    public static function currentReducedRate(): int
    {
        return self::current()?->reduced_rate ?? 8;
    }
}
