<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Step 19: 月次締めステートマシン
 *
 * 状態遷移: unclosed → generated → approved → sent → closed
 * closed は当該月の自費・請求データ更新をガードする
 */
class MonthlyClosingState extends Model
{
    use LogsActivity;

    public const STATUS_UNCLOSED = 'unclosed';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT = 'sent';

    public const STATUS_CLOSED = 'closed';

    /** 正しい遷移順序 */
    public const TRANSITIONS = [
        self::STATUS_UNCLOSED,
        self::STATUS_GENERATED,
        self::STATUS_APPROVED,
        self::STATUS_SENT,
        self::STATUS_CLOSED,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'anomaly_report' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'closed_by', 'closed_at', 'reopened_by', 'reopen_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "MonthlyClosing {$eventName}: {$this->billing_year_month} ({$this->status})")
            ->useLogName('monthly_closing');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * 対象月・施設の締め状態を取得または新規作成
     */
    public static function findOrCreate(string $yearMonth, int $facilityId): self
    {
        return static::firstOrCreate(
            ['billing_year_month' => $yearMonth, 'facility_id' => $facilityId],
            ['status' => self::STATUS_UNCLOSED],
        );
    }

    /**
     * その月がロック済み（closed）か
     */
    public static function isClosed(string $yearMonth, ?int $facilityId = null): bool
    {
        $query = static::where('billing_year_month', $yearMonth)
            ->where('status', self::STATUS_CLOSED);

        if ($facilityId !== null) {
            $query->where('facility_id', $facilityId);
        }

        return $query->exists();
    }

    /**
     * 状態を1ステップ進める（不正遷移はDomainException）
     */
    public function advanceTo(string $newStatus): void
    {
        $currentIndex = array_search($this->status, self::TRANSITIONS, true);
        $newIndex = array_search($newStatus, self::TRANSITIONS, true);

        if ($newIndex === false) {
            throw new \DomainException("不明な締め状態: {$newStatus}");
        }

        if ($newIndex !== $currentIndex + 1) {
            throw new \DomainException(
                "不正な状態遷移です: {$this->status} から {$newStatus} へは直接遷移できません。"
            );
        }

        $this->status = $newStatus;

        if ($newStatus === self::STATUS_CLOSED) {
            $this->closed_by = auth()->id();
            $this->closed_at = now();
        }

        $this->save();
    }

    /**
     * 緊急差戻し（管理者権限）: closed以前の任意状態へ戻す
     */
    public function reopen(string $reason): void
    {
        $this->status = self::STATUS_UNCLOSED;
        $this->reopened_by = auth()->id();
        $this->reopened_at = now();
        $this->reopen_reason = $reason;
        $this->save();
    }
}
