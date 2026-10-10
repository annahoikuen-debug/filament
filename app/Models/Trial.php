<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'facility_type',
        'resident_capacity',
        'score',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'facility_id',
        'trial_config',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_config' => 'array',
        'score' => 'integer',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->trial_ends_at && $this->trial_ends_at->isPast());
    }

    /**
     * 本契約移行・見積書送信用のトークン検証（タイミング攻撃対策済み）
     */
    public function isValidConversionToken(?string $token): bool
    {
        $expected = (string) ($this->trial_config['conversion_token'] ?? '');

        if ($expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function daysUntilExpiry(): int
    {
        if (! $this->trial_ends_at || $this->trial_ends_at->isPast()) {
            return 0;
        }

        // 日付単位での残り日数（時刻のミリ秒差の影響を排除）
        return (int) $this->trial_ends_at->copy()->startOfDay()
            ->diffInDays(now()->copy()->startOfDay(), true);
    }

    /**
     * 施設
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }
}
