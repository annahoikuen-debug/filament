<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringCharge extends Model
{
    use HasFactory;

    protected $table = 'recurring_charges';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

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
     * 品目
     */
    public function chargeItem(): BelongsTo
    {
        return $this->belongsTo(ChargeItem::class);
    }

    /**
     * 有効な定期課金のみ取得するスコープ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', Carbon::today())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::today());
            });
    }

    /**
     * 指定月で有効な定期課金を取得するスコープ
     */
    public function scopeForYearMonth(Builder $query, string $yearMonth): Builder
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        return $query->where('is_active', true)
            ->where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate);
            });
    }

    /**
     * 指定月に適用される日数を計算（月額の場合は満月、日額の場合は在籍日数）
     */
    public function getApplicableDays(string $yearMonth, ?Carbon $moveInDate = null, ?Carbon $moveOutDate = null): int
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $effectiveStart = $this->start_date > $startDate ? $this->start_date : $startDate;
        if ($moveInDate && $moveInDate > $effectiveStart) {
            $effectiveStart = $moveInDate;
        }

        $effectiveEnd = $this->end_date ?? $endDate;
        if ($moveOutDate && $moveOutDate < $effectiveEnd) {
            $effectiveEnd = $moveOutDate;
        }

        if ($effectiveStart > $effectiveEnd) {
            return 0;
        }

        if ($this->frequency === 'monthly') {
            return $startDate->daysInMonth;
        }

        return $effectiveStart->diffInDays($effectiveEnd) + 1;
    }
}
