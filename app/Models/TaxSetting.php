<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
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

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($taxSetting) {
            // Only validate if the tax setting is being activated
            if ($taxSetting->is_active) {
                $taxSetting->validateNoOverlap();
            }
        });
    }

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

    /**
     * Validate that the tax setting's date range does not overlap with any other active tax setting.
     *
     * @throws ValidationException
     */
    protected function validateNoOverlap(): void
    {
        $query = self::where('is_active', true);

        // If model exists (update), exclude the current record
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        // Check for overlapping date ranges
        $effectiveFrom = $this->effective_from instanceof Carbon ? $this->effective_from->format('Y-m-d') : $this->effective_from;
        $effectiveUntil = $this->effective_until instanceof Carbon ? $this->effective_until->format('Y-m-d') : $this->effective_until;

        // A and B overlap if:
        // (A.from <= B.until or B.until is null) and (A.until >= B.from or A.until is null)
        $overlap = $query->where(function ($q) use ($effectiveFrom, $effectiveUntil) {
            // Existing setting starts before/at new setting's end (or new setting has no end)
            if ($effectiveUntil !== null) {
                $q->where('effective_from', '<=', $effectiveUntil);
            }

            // Existing setting ends after/at new setting's start (or existing setting has no end)
            $q->where(function ($sub) use ($effectiveFrom) {
                if ($effectiveFrom !== null) {
                    $sub->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $effectiveFrom);
                }
            });
        })->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => 'The selected date range overlaps with an existing active tax setting.',
                'effective_until' => 'The selected date range overlaps with an existing active tax setting.',
            ]);
        }
    }
}
