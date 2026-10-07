<?php

namespace App\Models;

use App\Enums\ResidentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'room_number',
        'name',
        'name_kana',
        'base_rent',
        'base_management_fee',
        'status',
        'move_in_date',
        'move_out_date',
    ];

    protected $casts = [
        'base_rent' => 'integer',
        'base_management_fee' => 'integer',
        'status' => ResidentStatus::class,
        'move_in_date' => 'date',
        'move_out_date' => 'date',
    ];

    /**
     * 施設リレーション
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * 日々の自費利用明細一覧
     */
    public function dailyCharges(): HasMany
    {
        return $this->hasMany(DailyCharge::class);
    }

    /**
     * 月次請求データ一覧
     */
    public function monthlyInvoices(): HasMany
    {
        return $this->hasMany(MonthlyInvoice::class);
    }

    /**
     * 入居中のみのスコープ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ResidentStatus::Active);
    }

    /**
     * 施設でフィルタするスコープ
     */
    public function scopeForFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * 指定された日付に入居中（在籍中）であるかを判定する
     */
    public function isLivingAt(string|Carbon $date): bool
    {
        $targetDate = Carbon::parse($date)->toDateString();

        if ($this->move_in_date && $targetDate < $this->move_in_date->toDateString()) {
            return false;
        }

        if ($this->move_out_date && $targetDate > $this->move_out_date->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * 表示用フルタイトル（部屋番号 + 氏名）
     */
    public function getFullTitleAttribute(): string
    {
        return "[{$this->room_number}] {$this->name}";
    }

    /**
     * 毎月の固定費用（基本家賃 + 基本管理費）
     */
    public function getBaseMonthlyTotalAttribute(): int
    {
        return ($this->base_rent ?? 0) + ($this->base_management_fee ?? 0);
    }

    /**
     * 指定年の月の在籍日数を計算する
     */
    public function getLivingDaysInMonth(int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $moveIn = $this->move_in_date ? Carbon::parse($this->move_in_date) : null;
        $moveOut = $this->move_out_date ? Carbon::parse($this->move_out_date) : null;

        // 期間外の場合は0日
        if ($moveIn && $moveIn > $end) {
            return 0;
        }
        if ($moveOut && $moveOut < $start) {
            return 0;
        }

        $effectiveStart = $moveIn ? max($start, $moveIn) : $start;
        $effectiveEnd = $moveOut ? min($end, $moveOut) : $end;

        return $effectiveStart->diffInDays($effectiveEnd) + 1; // 両端を含む
    }

    /**
     * 指定年の月の日割り計算を行う
     */
    public function getProratedAmount(int $year, int $month, int $monthlyAmount): int
    {
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $livingDays = $this->getLivingDaysInMonth($year, $month);

        if ($daysInMonth === 0) {
            return 0;
        }

        return (int) round(($monthlyAmount / $daysInMonth) * $livingDays);
    }
}
